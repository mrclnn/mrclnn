<?php

namespace App\Jobs;

use simplehtmldom\HtmlNode;

class ParserNode
{
    private string $name;
    private string $selector;
    private HtmlNode $node;
    private array $attributes;

    public function setName(string $name): ParserNode
    {
        $this->name = $name;
        return $this;
    }
    public function setSelector(string $selector): ParserNode
    {
        $this->selector = $selector;
        return $this;
    }
    public function getName(): string
    {
        return $this->name;
    }
}