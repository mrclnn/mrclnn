<?php

namespace App\Console\Commands;

use App\lib\AmoHelper\Logger;
use Illuminate\Console\Command;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlNode;
use simplehtmldom\HtmlWeb;

class GavrishParser extends Command
{
    use Logger;
    protected $signature = 'gavrish:parser {filter=null}';
    private HtmlWeb $web;

    private string $siteDomain = 'https://semenagavrish.ru';

    public function handle()
    {

        $this->init();
        try{

            $this->logger->info(__METHOD__.' handling start...');

            $filter = $this->argument('filter');
            dump("We try to filter by word: $filter");
            $this->logger->debug("We try to filter by word: $filter");

            $html = '';
            $url = $this->siteDomain . "/shop/search/$filter/";
            $page = 1;
            do{
                try{

                    dump("start to loading page $page");
                    $this->logger->info("start to loading page $page");

                    $currentPageUrl = "$url?page=$page";

                    $currentPageHtml = file_get_contents($currentPageUrl);
//                    $currentPageHtml = $this->web->load($currentPageUrl);
                    $currentPageHtml = new HtmlDocument($currentPageHtml);


                    dump($currentPageUrl);
                    dump("page $page received successfully, start to process...");
                    $this->processPage($currentPageHtml);

                } catch (\Throwable $e){

                    dump("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
                    $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
                    $currentPageHtml = null;

                } finally {
                    sleep(1);
                    $page++;
                }

            } while ( ! $this->isEndOfPages($currentPageHtml));



        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }

    }

    private function processPage(HtmlDocument $html)
    {

        $items = $html->find('[itemtype="http://schema.org/Product"] div.image a');

        $links = array_map(function(HtmlNode $item){
            return $item->getAttribute('href');
        }, $items);
        foreach($links as $index => $link){
            try{
                dump("checking $index product...");
                $this->logger->info("checking $index product...");

                $currentPageHtml = file_get_contents($this->siteDomain.$link);
                $currentPageHtml = new HtmlDocument($currentPageHtml);
                $this->processProduct($currentPageHtml);

            } catch (\Throwable $e){
                dump("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
                $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
            } finally {
                sleep(1);
            }
        }

    }

    private function processProduct(HtmlDocument $htmlProduct)
    {
        $productName = collect($htmlProduct->find('article h1.product-name'))->first()->innertext();
        $productDescription = collect($htmlProduct->find('div#product-description p'))->first()->innertext();
        $productDetails = collect($htmlProduct->find('ul.tabs table.posadka'))->first()->innertext();

        dump($productName);
        dump($productDescription);
//        dump($productDetails);
    }

    private function isEndOfPages(?HtmlDocument $html): bool
    {
        $res = $html->find('div#product-list');
        if(empty($html)) return true;
        return str_contains(collect($res)->first()->innertext(), 'Не найдено ни одного товара.');
    }

    private function init()
    {
        $this->setLogger();
        $this->web = new HtmlWeb();
    }
}