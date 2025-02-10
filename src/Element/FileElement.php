<?php

namespace MosparoRpg\Element;

use MosparoRpg\Trait\ElementOptionTrait;
use MosparoRpg\Trait\KeyTrait;

class FileElement implements ElementInterface
{
    const KEY = 'file_element';

    use KeyTrait;
    use ElementOptionTrait;

    protected string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getExtension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    public function writeToFile(string $content, $append = true)
    {
        file_put_contents($this->getFilePath(), $content, ($append) ? FILE_APPEND : 0);
    }

    public function copyFromFile(FileElement $source, $append = true)
    {
        $mode = 'c+b';
        if (!$append) {
            $mode = 'wb';
        }

        $sourceHandle = fopen($source->getFilePath(), 'rb');
        $destHandle = fopen($this->getFilePath(), $mode);

        fseek($destHandle, 0, SEEK_END);

        while (($line = fgets($sourceHandle, 1024 * 1024)) !== false) {
            fwrite($destHandle, $line);
        }

        fclose($sourceHandle);
        fclose($destHandle);
    }
}