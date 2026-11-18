<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\Web\DownloadFileProcessor;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Uses file:// URLs so that no network access is needed.
 */
#[RequiresPhpExtension('curl')]
class DownloadFileProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testIsApplicable(): void
    {
        $processor = new DownloadFileProcessor();
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());

        $this->assertSame('download_file', $processor->getKey());
        $this->assertTrue($processor->isApplicable($ruleContext->setSource(new UrlElement('https://example.com'))));
        $this->assertFalse($processor->isApplicable($ruleContext->setSource(new FileElement('/a.txt'))));
        $this->assertFalse($processor->isApplicable(new RulePackageContext()));
    }

    public function testDownloadReplacesSourceWithTemporaryFile(): void
    {
        $remote = $this->tmpPath('remote.txt');
        file_put_contents($remote, "a.com\nb.com\n");

        $ruleContext = ContextFactory::ruleContext(ContextFactory::rulePackageContext($this->tmpDir));
        // prepareRuleContext() always sets the options, so we do the same here
        $ruleContext->setSource((new UrlElement('file://' . $remote))->setOptions([]));

        $this->assertTrue((new DownloadFileProcessor())->process($ruleContext, ContextFactory::mainProcessor()));

        $source = $ruleContext->getSource();
        $this->assertInstanceOf(TemporaryFileElement::class, $source);
        $this->assertSame("a.com\nb.com\n", file_get_contents($source->getFilePath()));
        $this->assertContains($source->getFilePath(), $ruleContext->getRulePackageContext()->getTemporaryFiles());
    }
}
