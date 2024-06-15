<?php

namespace App\Jobs;

use simplehtmldom\HtmlDocument;

class ParserPage
{
    private string $name;
    private string $uri;
    private HtmlDocument $document;
    private array $selectors;
    private array $nodes;

    public function setUri(string $uri){
        $this->uri = $uri;
    }
    public function addNode(ParserNode $node){
        $nodesName = $node->getName().'_list';
        if(!isset($this->nodes[$nodesName])) $this->nodes[$nodesName] = [];
        $this->nodes[$nodesName][] = $node;
    }

}