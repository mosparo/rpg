<?php

namespace MosparoRpg\Tests\Unit\Element;

use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;

class TemporaryFileElementTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testFileIsCreatedLazilyInTheTemporaryDirectory(): void
    {
        $dir = $this->createTemporaryDirectory();
        $rpContext = ContextFactory::rulePackageContext($dir);

        $element = new TemporaryFileElement($rpContext, 'downloaded');
        $this->assertSame([], $rpContext->getTemporaryFiles(), 'No file must be created before the path is requested.');

        $path = $element->getFilePath();

        $this->assertFileExists($path);
        $this->assertSame($dir, dirname($path));
        $this->assertStringStartsWith('mosparo_rpg_downloaded_', basename($path));
        $this->assertSame([$path], $rpContext->getTemporaryFiles());
    }

    public function testPathIsStable(): void
    {
        $rpContext = ContextFactory::rulePackageContext($this->createTemporaryDirectory());
        $element = new TemporaryFileElement($rpContext, 'x');

        $this->assertSame($element->getFilePath(), $element->getFilePath());
        $this->assertCount(1, $rpContext->getTemporaryFiles());
    }

    public function testWriteToFile(): void
    {
        $rpContext = ContextFactory::rulePackageContext($this->createTemporaryDirectory());
        $element = new TemporaryFileElement($rpContext, 'x');

        $element->writeToFile('hello ');
        $element->writeToFile('world');

        $this->assertSame('hello world', file_get_contents($element->getFilePath()));
        $this->assertSame('temporary_file_element', $element->getKey());
    }
}
