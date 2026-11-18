<?php

namespace MosparoRpg\Tests\Unit\Cli;

use MosparoRpg\Cli\ProgressBar;
use MosparoRpg\Cli\ProgressBarFormat;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class ProgressBarTest extends TestCase
{
    public function testStepsFormat(): void
    {
        $output = new BufferedOutput();
        $progressBar = new ProgressBar($output, ProgressBarFormat::STEPS, 'Items', 2000);
        $progressBar->advance(1500);
        $progressBar->finish();

        $text = $output->fetch();
        $this->assertStringContainsString('Items (2,000 of 2,000)', $text);
        $this->assertStringContainsString('100%', $text);
    }

    public function testBytesFixedFormat(): void
    {
        $output = new BufferedOutput();
        $progressBar = new ProgressBar($output, ProgressBarFormat::BYTES_FIXED, 'Read', 2048);
        $progressBar->finish();

        $this->assertStringContainsString('Read (2 KiB of 2 KiB)', $output->fetch());
    }

    public function testBytesUnknownFormat(): void
    {
        $output = new BufferedOutput();
        $progressBar = new ProgressBar($output, ProgressBarFormat::BYTES_UNKNOWN, 'Download', 0);
        $progressBar->advance(3 * 1024 * 1024 + 512 * 1024);
        $progressBar->finish();

        $text = $output->fetch();
        $this->assertStringContainsString('Download (3.5 MiB)', $text);
        $this->assertStringContainsString('[ -- ]', $text);
    }

    public function testZeroBytes(): void
    {
        $output = new BufferedOutput();
        (new ProgressBar($output, ProgressBarFormat::BYTES_UNKNOWN, 'Download', 0))->finish();

        $this->assertStringContainsString('Download (0 Bytes)', $output->fetch());
    }

    public function testFinishWritesNewLine(): void
    {
        $output = new BufferedOutput();
        (new ProgressBar($output, ProgressBarFormat::STEPS, 'x', 1))->finish();

        $this->assertStringEndsWith(PHP_EOL, $output->fetch());
    }
}
