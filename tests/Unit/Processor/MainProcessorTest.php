<?php

namespace MosparoRpg\Tests\Unit\Processor;

use ArrayObject;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\RecordingProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MainProcessorTest extends TestCase
{
    public function testProcessorsRunInPriorityOrder(): void
    {
        $log = new ArrayObject();
        $mainProcessor = ContextFactory::mainProcessor([
            'a' => new RecordingProcessor('a', $log),
            'b' => new RecordingProcessor('b', $log),
            'c' => new RecordingProcessor('c', $log),
            'd' => new RecordingProcessor('d', $log),
        ]);

        // intentionally unsorted
        $mainProcessor->process(new RulePackageContext(), [
            900 => ['d'],
            100 => ['a', 'b'],
            500 => ['c'],
        ]);

        $this->assertSame(['a', 'b', 'c', 'd'], $log->getArrayCopy());
    }

    public function testNotApplicableProcessorsAreSkipped(): void
    {
        $log = new ArrayObject();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $mainProcessor = ContextFactory::mainProcessor([
            'a' => new RecordingProcessor('a', $log, applicable: false),
            'b' => new RecordingProcessor('b', $log),
        ], $output);

        $mainProcessor->process(new RulePackageContext(), [100 => ['a', 'b']]);

        $this->assertSame(['b'], $log->getArrayCopy());
        $this->assertStringContainsString('Processor "a" not applicable', $output->fetch());
    }

    public function testFailingProcessorAbortsTheRemainingProcessors(): void
    {
        $log = new ArrayObject();
        $mainProcessor = ContextFactory::mainProcessor([
            'a' => new RecordingProcessor('a', $log),
            'fail' => new RecordingProcessor('fail', $log, result: false),
            'b' => new RecordingProcessor('b', $log),
            'c' => new RecordingProcessor('c', $log),
        ]);

        $mainProcessor->process(new RulePackageContext(), [
            100 => ['a', 'fail', 'b'],
            200 => ['c'],
        ]);

        $this->assertSame(['a', 'fail'], $log->getArrayCopy());
    }

    public function testEmptyProcessorList(): void
    {
        ContextFactory::mainProcessor()->process(new RulePackageContext(), []);

        $this->addToAssertionCount(1);
    }
}
