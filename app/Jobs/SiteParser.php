<?php


namespace App\Jobs;


use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
//use PhpOffice\PhpSpreadsheet\IOFactory;
//use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
//use PhpOffice\PhpSpreadsheet\IOFactory;
//use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlNode;
use simplehtmldom\HtmlWeb;
use Throwable;

class SiteParser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LoggerAwareTrait;

    private $baseURI = 'https://www.arendator.ru/objects/office/office_class:2/';
    private $pagination = 'page:%s/';
    private $currentPage;
    private $currentAttempt;
    private $doc;

    public function __construct(int $page = 1, int $currentAttempt = 0)
    {
        $this->currentPage = $page;
        $this->currentAttempt = $currentAttempt;
    }
    public function handle(){

        $this->setLogger();
        $this->doc = new HtmlWeb();
        $delay = 5;
        $maxAttempts = 5;
        $needNext = true;

        $url = sprintf("$this->baseURI$this->pagination", $this->currentPage);
        $this->logger->debug("Loading page $url ...");
        $page = $this->doc->load($url);
        if($page !== null){
            $containers = $page->find('a.objects-list__box.object-box');
            $this->logger->debug(sprintf('Page loaded. Find %s containers.', count($containers)));
            $failedCount = 0;
            $data = [];
            foreach ($containers as $i => $container){
                $this->logger->debug(sprintf('Processed %s/%s containers. Failed: %s', $i+1, count($containers), $failedCount));
                try{
                    $data[] = $this->processContainer($container);
                } catch (Throwable $e){
                    $failedCount++;
                    $this->logger->error($e->getMessage().' at line '.$e->getLine());
                }
            }
            try{
                $this->writeToFile('data.xlsx', $data);
            } catch (Throwable $e){
                $this->logger->error($e->getMessage().' at line '. $e->getLine());
                return;
            }
            $needNext = $this->breakCondition($page);
            $this->currentPage++;
            $this->currentAttempt = 0;
        } else {
            $this->logger->warning(sprintf('[%s] Unable to load page %s',$this->currentAttempt, $url));
            if($this->currentAttempt > $maxAttempts){
                $this->currentPage++;
                $this->logger->warning(sprintf('Max attempts (%s) reached. Continue with ignoring page %s',$this->currentAttempt, $url));
            } else {
                $delay = 60;
                $this->currentAttempt++;
            }
        }

        if($needNext){
            self::dispatch($this->currentPage, $this->currentAttempt)->delay(Carbon::now()->addSeconds($delay));
        } else {
            $this->logger->info('No need continue. End work.');
        }


    }

    private function breakCondition(HtmlDocument $page) : bool
    {
        return $this->currentPage === 17;
    }

    private function processContainer(HtmlNode $container){
        $name = $container->find('div.object-box__title', 0)->plaintext;
        $address = $container->find('div.object-box-info__description p', 0)->plaintext;

        return [$name, $address];
    }

    private function writeToFile(string $fileName, array $data){
//        require_once '/home/u946280762/domains/mrclnn.com/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
//        require_once '/home/u946280762/domains/mrclnn.com/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php';
//        require_once '/home/u946280762/domains/mrclnn.com/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';
        $cellNames = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $spreadsheet = file_exists($fileName) ? IOFactory::load('hello world.xlsx') : new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->insertNewRowBefore(count($data));
        foreach($data as $rowIndex => $row){
            if(count($row) > count($cellNames)){
                $this->logger->error(sprintf('Received more cells (%s) than max supported (%s)', count($row), count($cellNames)));
                return;
            }
            foreach ($row as $cellIndex => $cellValue){
                $sheet->setCellValue($cellNames[$cellIndex].($rowIndex+1), $cellValue);
            }
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($fileName);
    }

    private function setLogger(){
        $this->logger = new Logger(
            'WorkSiteParser', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/WorkSiteParser.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}