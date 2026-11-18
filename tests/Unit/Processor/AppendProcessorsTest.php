<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Processor\Package\AppendRuleItemProcessor;
use MosparoRpg\Processor\Package\AppendRuleProcessor;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AppendProcessorsTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testIsApplicable(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());
        $itemContext = new RuleItemContext($ruleContext);

        $this->assertTrue((new AppendRuleProcessor())->isApplicable($ruleContext));
        $this->assertFalse((new AppendRuleProcessor())->isApplicable($itemContext));
        $this->assertTrue((new AppendRuleItemProcessor())->isApplicable($itemContext));
        $this->assertFalse((new AppendRuleItemProcessor())->isApplicable($ruleContext));

        $this->assertSame('append_rule', (new AppendRuleProcessor())->getKey());
        $this->assertSame('append_rule_item', (new AppendRuleItemProcessor())->getKey());
    }

    public function testRuleAndItemAreWrittenToTheTaskFile(): void
    {
        $path = $this->createTemporaryDirectory() . '/tasks';
        $destination = new TaskFileElement($path);
        $destination->resetTaskFile();

        $rpContext = ContextFactory::rulePackageContext($this->tmpDir, $destination);
        $ruleContext = ContextFactory::ruleContext($rpContext)
            ->setUuid($ruleUuid = Uuid::v4())
            ->setDestination($destination);
        $itemContext = (new RuleItemContext($ruleContext))
            ->setUuid($itemUuid = Uuid::v4())
            ->setType('text')
            ->setValue('example.com')
            ->setRating(2.0);

        $mainProcessor = ContextFactory::mainProcessor();
        $this->assertTrue((new AppendRuleProcessor())->process($ruleContext, $mainProcessor));
        $this->assertTrue((new AppendRuleItemProcessor())->process($itemContext, $mainProcessor));
        $destination->finalizeTasksFile();

        $tasks = iterator_to_array(new TaskFileElement($path), false);

        $this->assertEquals([
            new RuleTask((string) $ruleUuid, 'email', 'Test rule', null, 1.5),
            new RuleItemTask((string) $ruleUuid, (string) $itemUuid, 'text', 'example.com', 2.0),
        ], $tasks);
    }
}
