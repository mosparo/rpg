<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\File\ProcessCsvFileProcessor;
use MosparoRpg\Processor\File\ProcessListFileProcessor;
use MosparoRpg\Processor\Identification\ResolveUuidProcessor;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\Package\AppendRuleItemProcessor;
use MosparoRpg\Tests\Support\CollectingProcessor;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Type\Input\TableInputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests the list and CSV processors. The rule item processors are replaced with a collector,
 * so we can check which rule item contexts would be processed.
 */
class FileInputProcessorsTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private CollectingProcessor $collector;

    private MainProcessor $mainProcessor;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
        $this->collector = new CollectingProcessor();
        $this->mainProcessor = ContextFactory::mainProcessor([
            ResolveUuidProcessor::class => $this->collector,
            AppendRuleItemProcessor::class => new CollectingProcessor(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    private function ruleContextForFile(string $fileName, string $content, RuleContext $ruleContext): RuleContext
    {
        file_put_contents($this->tmpPath($fileName), $content);

        return $ruleContext->setSource(new FileElement($this->tmpPath($fileName)));
    }

    /**
     * @return array<int, array{string, string, float}>
     */
    private function collectedItems(): array
    {
        return array_map(
            static fn (RuleItemContext $c) => [$c->getType(), $c->getValue(), $c->getRating()],
            $this->collector->contexts
        );
    }

    public function testListIsApplicable(): void
    {
        $processor = new ProcessListFileProcessor();
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());

        $this->assertFalse($processor->isApplicable($ruleContext), 'No source set');
        $this->assertFalse($processor->isApplicable($ruleContext->setSource(new UrlElement('https://a'))));
        $this->assertTrue($processor->isApplicable($ruleContext->setSource(new FileElement('/a.txt'))));
        $this->assertFalse($processor->isApplicable(new RulePackageContext()));
    }

    public function testListCreatesOneItemPerLine(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext())
            ->setItemOptions(['type' => 'text', 'rating' => 4]);
        $ruleContext = $this->ruleContextForFile('list.txt', "  first.com \nsecond.com\r\nthird.com", $ruleContext);

        $this->assertTrue((new ProcessListFileProcessor())->process($ruleContext, $this->mainProcessor));

        $this->assertSame([
            ['text', 'first.com', 4.0],
            ['text', 'second.com', 4.0],
            ['text', 'third.com', 4.0],
        ], $this->collectedItems());

        foreach ($this->collector->contexts as $itemContext) {
            $this->assertSame($ruleContext, $itemContext->getRuleContext());
        }
    }

    public function testListUsesDefaultItemOptions(): void
    {
        $ruleContext = $this->ruleContextForFile('list.txt', "a\n", ContextFactory::ruleContext(new RulePackageContext()));

        (new ProcessListFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['', 'a', 1.0]], $this->collectedItems());
    }

    public function testListSkipsEmptyLines(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext())->setItemOptions(['type' => 'text']);
        $ruleContext = $this->ruleContextForFile('list.txt', "a\n\n   \nb\n", $ruleContext);

        (new ProcessListFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['text', 'a', 1.0], ['text', 'b', 1.0]], $this->collectedItems());
    }

    public function testListKeepsZeroValuesIncludingLastLineWithoutNewline(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext())->setItemOptions(['type' => 'text']);
        $ruleContext = $this->ruleContextForFile('list.txt', "a\n0\nb\n0", $ruleContext);

        (new ProcessListFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame(['a', '0', 'b', '0'], array_column($this->collectedItems(), 1));
    }

    public function testCsvIsApplicableOnlyForCsvFiles(): void
    {
        $processor = new ProcessCsvFileProcessor();
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());

        $this->assertTrue($processor->isApplicable($ruleContext->setSource(new FileElement('/a.csv'))));
        $this->assertFalse($processor->isApplicable($ruleContext->setSource(new FileElement('/a.txt'))));
        $this->assertFalse($processor->isApplicable($ruleContext->setSource(new UrlElement('https://a/a.csv'))));
    }

    public function testCsvWithDefaultMapping(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text, a.com ,2\nemail,b@c.d,5.5\n", $ruleContext);

        $this->assertTrue((new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor));

        $this->assertSame([
            ['text', 'a.com', 2.0],
            ['email', 'b@c.d', 5.5],
        ], $this->collectedItems());
    }

    public function testCsvWithMappingSeparatorAndSkipRows(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile(
            'table.csv',
            "rating;value;type\n3;x.com;text\n7;\"y;z\";email\n",
            $ruleContext
        );
        $ruleContext->getSource()->setOptions([
            'separator' => ';',
            'skipRows' => 1,
            'mapping' => ['type' => 2, 'value' => 1, 'rating' => 0],
        ]);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([
            ['text', 'x.com', 3.0],
            ['email', 'y;z', 7.0],
        ], $this->collectedItems());
    }

    public function testCsvItemsBelongToTheRule(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text,a,1\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertCount(1, $this->collector->contexts);
        $this->assertSame($ruleContext, $this->collector->contexts[0]->getRuleContext());
    }

    public function testCsvSkipsEmptyLines(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text,a,1\n\ntext,b,1\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['text', 'a', 1.0], ['text', 'b', 1.0]], $this->collectedItems());
    }

    public function testCsvSkipsRowsWithOnlyWhitespace(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text,a,1\n  ,  ,  \n,,\ntext,b,1\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['text', 'a', 1.0], ['text', 'b', 1.0]], $this->collectedItems());
    }

    public function testCsvRowWithoutRatingGetsDefaultRating(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text,a\ntext,b,\ntext,c,2\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['text', 'a', 1.0], ['text', 'b', 1.0], ['text', 'c', 2.0]], $this->collectedItems());
    }

    public function testCsvRowWithoutTypeOrValueIsIgnoredWithWarning(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $mainProcessor = ContextFactory::mainProcessor([
            ResolveUuidProcessor::class => $this->collector,
            AppendRuleItemProcessor::class => new CollectingProcessor(),
        ], $output);

        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text\n,a,1\ntext,b,1\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $mainProcessor);

        $this->assertSame([['text', 'b', 1.0]], $this->collectedItems());

        $log = $output->fetch();
        $this->assertStringContainsString('Row 1 in', $log);
        $this->assertStringContainsString('Row 2 in', $log);
        $this->assertStringContainsString('[warning]', $log);
    }

    public function testCsvKeepsZeroValues(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "text,0,0\n", $ruleContext);

        (new ProcessCsvFileProcessor())->process($ruleContext, $this->mainProcessor);

        $this->assertSame([['text', '0', 0.0]], $this->collectedItems());
    }

    public function testCsvRowNumbersInWarningsCountSkippedRows(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $mainProcessor = ContextFactory::mainProcessor([
            ResolveUuidProcessor::class => $this->collector,
            AppendRuleItemProcessor::class => new CollectingProcessor(),
        ], $output);

        $ruleContext = ContextFactory::ruleContext(new RulePackageContext(), new TableInputType());
        $ruleContext = $this->ruleContextForFile('table.csv', "type,value,rating\ntext,a,1\n,b,1\n", $ruleContext);
        $ruleContext->getSource()->setOptions(['skipRows' => 1]);

        (new ProcessCsvFileProcessor())->process($ruleContext, $mainProcessor);

        $this->assertSame([['text', 'a', 1.0]], $this->collectedItems());
        $this->assertStringContainsString('Row 3 in', $output->fetch());
    }

    public function testNonRuleContextIsIgnored(): void
    {
        $this->assertTrue((new ProcessListFileProcessor())->process(new RulePackageContext(), $this->mainProcessor));
        $this->assertTrue((new ProcessCsvFileProcessor())->process(new RulePackageContext(), $this->mainProcessor));
        $this->assertSame([], $this->collector->contexts);
    }
}
