<?php

namespace MosparoRpg\Element;

use MosparoRpg\Trait\ElementOptionTrait;
use MosparoRpg\Trait\KeyTrait;

class DirectoryElement implements ElementInterface
{
    const KEY = 'directory_element';

    use KeyTrait;
    use ElementOptionTrait;

    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}