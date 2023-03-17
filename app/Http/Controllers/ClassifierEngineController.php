<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use simplehtmldom\HtmlWeb;

class ClassifierEngineController extends Controller
{
    public function execute(){
        echo 'engine';

        $doc = new HtmlWeb();

        if(!empty($_GET['after_searching'])){

            $this->afterSearching();

            if($_GET['after_searching'] === 'false'){
                echo 'я кончила';
                $this->DIE_WITH_CRINGE();
            }

        }

        $this->handleNextRequest();



    }

    private function afterSearching(){

        $doc = new HtmlWeb();
        $wiki_page = $doc->load($_GET['link']);
        if($wiki_page !== null){
            $infobox = $wiki_page->find('table.infobox', 0);
            $firstWikiP = $infobox->nextSibling()->plaintext;

        }

    }

    private function handleNextRequest(){
        $doc = new HtmlWeb();

        $requestArray = explode(',', $_GET['param']);
        $request = $requestArray[0];
        unset($requestArray[0]);
        $after_searching = empty($requestArray) ? 'false' : 'true';
        $q = urlencode($request . ' википедия');
        $yandex_page = $doc->load('https://yandex.by/search/?text='. $q);
        $paramString = implode(',',$requestArray);
        $js = <<<JS
<script>
;window.onload = function(){
    var link9875 = document.querySelector('.serp-item a.link');

    var href9875 = encodeURI(link9875.getAttribute('href'));
    
    document.location.href = '/classifierEngine?link=' + href9875 + '&param=$paramString&after_searching=$after_searching' ;

}
</script>
JS;

        if($yandex_page !== null){
            echo $yandex_page->innertext . $js;
        }

    }
    private function DIE_WITH_CRINGE(){
        die;
    }
}
