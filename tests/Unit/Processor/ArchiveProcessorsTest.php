<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\File\GzippedFileProcessor;
use MosparoRpg\Processor\File\ZippedFileProcessor;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

#[RequiresPhpExtension('fileinfo')]
class ArchiveProcessorsTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private RuleContext $ruleContext;

    protected function setUp(): void
    {
        $dir = $this->createTemporaryDirectory();
        $this->ruleContext = ContextFactory::ruleContext(ContextFactory::rulePackageContext($dir));
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    private function content(): string
    {
        return implode("\n", array_map(static fn (int $i) => 'entry-' . $i . '.example', range(1, 500))) . "\n";
    }

    #[RequiresPhpExtension('zlib')]
    public function testGzipIsExtracted(): void
    {
        $path = $this->tmpPath('list.txt.gz');
        file_put_contents($path, gzencode($this->content()));
        $this->ruleContext->setSource(new FileElement($path));

        $processor = new GzippedFileProcessor();
        $this->assertSame('gzipped_file', $processor->getKey());
        $this->assertTrue($processor->isApplicable($this->ruleContext));
        $this->assertTrue($processor->process($this->ruleContext, ContextFactory::mainProcessor()));

        $source = $this->ruleContext->getSource();
        $this->assertInstanceOf(TemporaryFileElement::class, $source);
        $this->assertSame($this->content(), file_get_contents($source->getFilePath()));
        $this->assertContains($source->getFilePath(), $this->ruleContext->getRulePackageContext()->getTemporaryFiles());
    }

    #[RequiresPhpExtension('zlib')]
    public function testGzipIsNotApplicableForPlainFiles(): void
    {
        $path = $this->tmpPath('list.txt');
        file_put_contents($path, $this->content());

        $processor = new GzippedFileProcessor();
        $this->assertFalse($processor->isApplicable($this->ruleContext->setSource(new FileElement($path))));
        $this->assertFalse($processor->isApplicable($this->ruleContext->setSource(new UrlElement('https://a/x.gz'))));
        $this->assertFalse($processor->isApplicable(new RulePackageContext()));
    }

    #[RequiresPhpExtension('zip')]
    public function testZipIsExtracted(): void
    {
        $path = $this->tmpPath('list.zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('list.txt', $this->content());
        $zip->close();
        $this->ruleContext->setSource(new FileElement($path));

        $processor = new ZippedFileProcessor();
        $this->assertSame('zipped_file', $processor->getKey());
        $this->assertTrue($processor->isApplicable($this->ruleContext));
        $this->assertTrue($processor->process($this->ruleContext, ContextFactory::mainProcessor()));

        $source = $this->ruleContext->getSource();
        $this->assertInstanceOf(TemporaryFileElement::class, $source);
        $this->assertSame($this->content(), file_get_contents($source->getFilePath()));
    }

    #[RequiresPhpExtension('zip')]
    public function testZipWithMultipleFilesFails(): void
    {
        $path = $this->tmpPath('list.zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('a.txt', 'a');
        $zip->addFromString('b.txt', 'b');
        $zip->close();
        $this->ruleContext->setSource($source = new FileElement($path));

        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $result = (new ZippedFileProcessor())->process($this->ruleContext, ContextFactory::mainProcessor([], $output));

        $this->assertFalse($result);
        $this->assertSame($source, $this->ruleContext->getSource(), 'The source must not be replaced on error.');
        $this->assertStringContainsString('contains more than one file', $output->fetch());
    }

    #[RequiresPhpExtension('zip')]
    public function testInvalidZipFails(): void
    {
        $path = $this->tmpPath('broken.zip');
        file_put_contents($path, 'this is not a zip file');
        $this->ruleContext->setSource(new FileElement($path));

        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $this->assertFalse((new ZippedFileProcessor())->process($this->ruleContext, ContextFactory::mainProcessor([], $output)));
        $this->assertStringContainsString('Cannot open ZIP archive', $output->fetch());
    }

    #[RequiresPhpExtension('zip')]
    public function testZipIsNotApplicableForPlainFiles(): void
    {
        $path = $this->tmpPath('list.txt');
        file_put_contents($path, $this->content());

        $this->assertFalse((new ZippedFileProcessor())->isApplicable($this->ruleContext->setSource(new FileElement($path))));
    }
}
