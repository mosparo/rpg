<?php

namespace MosparoRpg\Tests\Unit\Helper;

use MosparoRpg\Cli\ProgressBar;
use MosparoRpg\Cli\ProgressBarFormat;
use MosparoRpg\Helper\CliOutputHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class CliOutputHelperTest extends TestCase
{
    public function testWritelnFormatsTagAndPadding(): void
    {
        $output = new BufferedOutput();
        $helper = new CliOutputHelper($output);

        $helper->writeln('input', 'Process input A', 'green');

        // 7 - strlen('input') = 2 spaces padding after the tag
        $this->assertSame(' [INPUT]   Process input A' . PHP_EOL, $output->fetch());
    }

    public function testWritelnWithLongKeyHasNoPadding(): void
    {
        $output = new BufferedOutput();
        (new CliOutputHelper($output))->writeln('verylongkey', 'text');

        $this->assertSame(' [VERYLONGKEY] text' . PHP_EOL, $output->fetch());
    }

    public function testStartProgressReturnsProgressBar(): void
    {
        $progressBar = (new CliOutputHelper(new BufferedOutput()))->startProgress(ProgressBarFormat::STEPS, 'Working', 10);

        $this->assertInstanceOf(ProgressBar::class, $progressBar);
    }
}
