<?php

namespace MosparoRpg\Cli;

use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressBar
{
    protected OutputInterface $section;

    protected SymfonyProgressBar $progressBar;

    protected ProgressBarFormat $format;

    protected string $message;

    protected int $max = 0;

    protected int $step = 0;

    public function __construct(OutputInterface $section, ProgressBarFormat $format, string $message, int $max = 0)
    {
        $this->section = $section;
        $this->format = $format;
        $this->message = $message;
        $this->max = $max;

        $this->start();
    }

    public function start(): void
    {
        $this->progressBar = new SymfonyProgressBar($this->section);

        if ($this->format === ProgressBarFormat::BYTES_UNKNOWN || !$this->max) {
            $format = ' %bar% [ -- ] [%elapsed:16s%] %message%';
        } else {
            $format = ' %bar% [%percent:3s%%] [%elapsed:16s%] %message% (%remaining%)';
        }
        $this->progressBar->setFormat($format);
        $this->progressBar->setMessage($this->message);
        $this->progressBar->start($this->max);
        $this->adjustProgressBar();

    }

    public function advance(int $step): void
    {
        $this->step = $step;
        $this->adjustProgressBar();
    }

    public function finish(): void
    {
        if ($this->max) {
            $this->advance($this->max);
        }
        $this->progressBar->finish();
        $this->section->writeln('');
    }

    protected function adjustProgressBar(): void
    {
        $message = 'Processing...';
        if ($this->format === ProgressBarFormat::STEPS) {
            $message = sprintf('%s (%s of %s)', $this->message, number_format($this->step), number_format($this->max));
        } else if ($this->format === ProgressBarFormat::BYTES_FIXED) {
            $message = sprintf('%s (%s of %s)', $this->message, $this->adjustSize($this->step, false), $this->adjustSize($this->max));
        } else if ($this->format === ProgressBarFormat::BYTES_UNKNOWN) {
            $message = sprintf('%s (%s)', $this->message, $this->adjustSize($this->step));
        }

        $this->progressBar->setMessage($message);
        $this->progressBar->setProgress($this->step);
    }

    protected function adjustSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 Bytes';
        }

        $div = 1024;
        $sizes = ['Bytes', 'KiB', 'MiB', 'GiB'];

        $size = floor(log($bytes) / log($div));

        return round($bytes / pow($div, $size), 2) . ' ' . $sizes[$size];
    }
}