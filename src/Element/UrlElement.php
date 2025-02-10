<?php

namespace MosparoRpg\Element;

use MosparoRpg\Trait\ElementOptionTrait;
use MosparoRpg\Trait\KeyTrait;

class UrlElement implements ElementInterface
{
    const KEY = 'url_element';

    use KeyTrait;
    use ElementOptionTrait;

    protected string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}