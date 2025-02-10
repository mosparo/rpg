<?php

namespace MosparoRpg\Processor;

use Symfony\Component\Filesystem\Path;

abstract class AbstractProcessor
{
    protected function buildAbsolutePath($path, $workingPath): string
    {
        if (Path::isAbsolute($path)) {
            return $path;
        }

        return Path::canonicalize($workingPath . '/' . $path);
    }

    protected function copyFileContent(string $sourceFile, string $destFile)
    {
        $sourceHandle = fopen($sourceFile, 'rb');
        $destHandle = fopen($destFile, 'c+b');

        fseek($destHandle, 0, SEEK_END);

        while (($line = fgets($sourceHandle, 1024 * 1024)) !== false) {
            fwrite($destHandle, $line);
        }

        fclose($sourceHandle);
        fclose($destHandle);
    }
}