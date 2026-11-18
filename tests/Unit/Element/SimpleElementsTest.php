<?php

namespace MosparoRpg\Tests\Unit\Element;

use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;

class SimpleElementsTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testKeys(): void
    {
        $this->assertSame('directory_element', (new DirectoryElement('/'))->getKey());
        $this->assertSame('file_element', (new FileElement('/a'))->getKey());
        $this->assertSame('url_element', (new UrlElement('https://a'))->getKey());
        $this->assertSame('task_file_element', (new TaskFileElement('/a'))->getKey());
    }

    public function testDirectoryElement(): void
    {
        $this->assertSame('/var/data', (new DirectoryElement('/var/data'))->getPath());
    }

    public function testUrlElement(): void
    {
        $this->assertSame('https://example.com/x.txt', (new UrlElement('https://example.com/x.txt'))->getUrl());
    }

    public function testFileElementPathAndExtension(): void
    {
        $element = new FileElement('/data/list.CSV');

        $this->assertSame('/data/list.CSV', $element->getFilePath());
        $this->assertSame('CSV', $element->getExtension());
        $this->assertSame('', (new FileElement('/data/noext'))->getExtension());
    }

    public function testFileElementWriteAppendsByDefault(): void
    {
        $path = $this->createTemporaryDirectory() . '/out.txt';
        $element = new FileElement($path);

        $element->writeToFile('a');
        $element->writeToFile('b');
        $this->assertSame('ab', file_get_contents($path));

        $element->writeToFile('c', false);
        $this->assertSame('c', file_get_contents($path));
    }

    public function testOptions(): void
    {
        $element = (new UrlElement('https://a'))
            ->setOptions(['user_agent' => 'test', 'nullable' => null])
            ->setOption('verify_ssl', false);

        $this->assertSame(['user_agent' => 'test', 'nullable' => null, 'verify_ssl' => false], $element->getOptions());
        $this->assertTrue($element->hasOption('user_agent'));
        $this->assertTrue($element->hasOption('verify_ssl'));
        $this->assertFalse($element->hasOption('missing'));
        // isset() semantics: null counts as missing
        $this->assertFalse($element->hasOption('nullable'));

        $this->assertSame('test', $element->getOption('user_agent', 'x'));
        $this->assertFalse($element->getOption('verify_ssl', true));
        $this->assertSame('fallback', $element->getOption('missing', 'fallback'));
        $this->assertSame('fallback', $element->getOption('nullable', 'fallback'));
    }

    public function testOptionLookupWorksBeforeOptionsWereSet(): void
    {
        $element = new FileElement('/a');

        $this->assertFalse($element->hasOption('x'));
        $this->assertSame('default', $element->getOption('x', 'default'));
    }
}
