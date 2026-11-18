<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Helper\UuidHelper;
use MosparoRpg\Processor\Identification\ResolveUuidProcessor;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Type\Output\FileOutputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ResolveUuidProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $uuidFile;

    protected function setUp(): void
    {
        $this->uuidFile = $this->createTemporaryDirectory() . '/index.rpuuids';
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    private function createProcessor(): ResolveUuidProcessor
    {
        return new ResolveUuidProcessor((new UuidHelper())->setUuidFilePath($this->uuidFile));
    }

    public function testIsApplicable(): void
    {
        $processor = $this->createProcessor();
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());

        $this->assertSame('resolve_uuid', $processor->getKey());
        $this->assertTrue($processor->isApplicable($ruleContext));
        $this->assertTrue($processor->isApplicable(new RuleItemContext($ruleContext)));
        $this->assertFalse($processor->isApplicable(new RulePackageContext()));
        $this->assertFalse($processor->isApplicable(new OutputContext(new RulePackageContext(), new FileOutputType())));
    }

    public function testAssignsNewUuids(): void
    {
        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());
        $itemContext = (new RuleItemContext($ruleContext))->setType('text')->setValue('a');

        $processor = $this->createProcessor();
        $this->assertTrue($processor->process($ruleContext, ContextFactory::mainProcessor()));
        $this->assertTrue($processor->process($itemContext, ContextFactory::mainProcessor()));

        $this->assertInstanceOf(Uuid::class, $ruleContext->getUuid());
        $this->assertInstanceOf(Uuid::class, $itemContext->getUuid());
        $this->assertNotEquals($ruleContext->getUuid(), $itemContext->getUuid());
    }

    public function testUuidsAreStableAcrossRuns(): void
    {
        // First run
        $helper = (new UuidHelper())->setUuidFilePath($this->uuidFile);
        $processor = new ResolveUuidProcessor($helper);

        $ruleContext = ContextFactory::ruleContext(new RulePackageContext());
        $itemContext = (new RuleItemContext($ruleContext))->setType('text')->setValue('a');
        $processor->process($ruleContext, ContextFactory::mainProcessor());
        $processor->process($itemContext, ContextFactory::mainProcessor());
        $helper->storeNewUuidIndex();

        // Second run with a fresh helper, same identifiers
        $processor2 = $this->createProcessor();
        $ruleContext2 = ContextFactory::ruleContext(new RulePackageContext());
        $itemContext2 = (new RuleItemContext($ruleContext2))->setType('text')->setValue('a');
        $processor2->process($ruleContext2, ContextFactory::mainProcessor());
        $processor2->process($itemContext2, ContextFactory::mainProcessor());

        $this->assertEquals($ruleContext->getUuid(), $ruleContext2->getUuid());
        $this->assertEquals($itemContext->getUuid(), $itemContext2->getUuid());
    }
}
