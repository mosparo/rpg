<?php

namespace MosparoRpg\Helper;

use MosparoRpg\Cli\ProgressBar;
use MosparoRpg\Cli\ProgressBarFormat;
use Symfony\Component\Console\Output\OutputInterface;

class CliOutputHelper
{
    protected OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function writeln(string $key, string $text, string $keyColor = 'bright-blue'): void
    {
        $requiredSpaces = 7 - strlen($key);
        $tag = sprintf('[<fg=%s>%s</>]', $keyColor, strtoupper($key)) . str_repeat(' ', ($requiredSpaces > 0) ? $requiredSpaces : 0);
        $this->output->writeln(sprintf(' %s %s', $tag, $text));
    }

    public function startProgress(ProgressBarFormat $format, string $message, int $steps = 100): ProgressBar
    {
        return new ProgressBar($this->output, $format, $message, $steps);
    }
}
