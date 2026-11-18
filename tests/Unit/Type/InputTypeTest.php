<?php

namespace MosparoRpg\Tests\Unit\Type;

use Exception;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\File\GzippedFileProcessor;
use MosparoRpg\Processor\File\ProcessCsvFileProcessor;
use MosparoRpg\Processor\File\ProcessListFileProcessor;
use MosparoRpg\Processor\File\ZippedFileProcessor;
use MosparoRpg\Processor\Identification\ResolveUuidProcessor;
use MosparoRpg\Processor\Package\AppendRuleItemProcessor;
use MosparoRpg\Processor\Package\AppendRuleProcessor;
use MosparoRpg\Processor\Web\DownloadFileProcessor;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Type\Input\ListInputType;
use MosparoRpg\Type\Input\TableInputType;
use MosparoRpg\Type\InputTypeInterface;
use PHPUnit\Framework\TestCase;

class InputTypeTest extends TestCase
{
    public function testKeys(): void
    {
        $this->assertSame('list', (new ListInputType())->getKey());
        $this->assertSame('table', (new TableInputType())->getKey());
    }

    public function testListProcessors(): void
    {
        $processors = (new ListInputType())->getProcessors();

        $this->assertSame([DownloadFileProcessor::class], $processors[InputTypeInterface::PRIORITY_ORGANIZE]);
        $this->assertSame([GzippedFileProcessor::class, ZippedFileProcessor::class], $processors[InputTypeInterface::PRIORITY_EXTRACT]);
        $this->assertSame([ResolveUuidProcessor::class, AppendRuleProcessor::class], $processors[InputTypeInterface::PRIORITY_PREPARE]);
        $this->assertSame([ProcessListFileProcessor::class], $processors[InputTypeInterface::PRIORITY_MAIN]);
    }

    public function testTableProcessors(): void
    {
        $processors = (new TableInputType())->getProcessors();

        $this->assertSame([ProcessCsvFileProcessor::class], $processors[InputTypeInterface::PRIORITY_MAIN]);
        $this->assertArrayHasKey(InputTypeInterface::PRIORITY_PREPARE, $processors);
    }

    public function testRuleItemProcessors(): void
    {
        $processors = (new ListInputType())->getRuleItemProcessors();

        $this->assertSame([ResolveUuidProcessor::class], $processors[InputTypeInterface::PRIORITY_OBJECT_END]);
        $this->assertSame([AppendRuleItemProcessor::class], $processors[InputTypeInterface::PRIORITY_FINALIZE]);
    }

    public function testPriorityOrderIsAscending(): void
    {
        $this->assertLessThan(InputTypeInterface::PRIORITY_EXTRACT, InputTypeInterface::PRIORITY_ORGANIZE);
        $this->assertLessThan(InputTypeInterface::PRIORITY_PREPARE, InputTypeInterface::PRIORITY_EXTRACT);
        $this->assertLessThan(InputTypeInterface::PRIORITY_MAIN, InputTypeInterface::PRIORITY_PREPARE);
        $this->assertLessThan(InputTypeInterface::PRIORITY_FINALIZE, InputTypeInterface::PRIORITY_OBJECT_END);
    }

    public function testPrepareRuleContextWithFileSource(): void
    {
        $destination = new TaskFileElement('/tmp/tasks');
        $rpContext = ContextFactory::rulePackageContext('/profiles/demo', $destination);
        $type = new ListInputType();

        $context = $type->prepareRuleContext($rpContext, [
            'rule' => [
                'identifier' => 'my-rule',
                'type' => 'email',
                'name' => 'Domains',
                'spam_rating_factor' => 2.5,
                'item' => ['type' => 'text', 'rating' => 3],
            ],
            'source' => [
                'type' => 'file',
                'path' => 'input/../input/list.txt',
                'options' => ['foo' => 'bar'],
            ],
        ]);

        $this->assertSame($rpContext, $context->getRulePackageContext());
        $this->assertSame($type, $context->getInputType());
        $this->assertSame('my-rule', $context->getIdentifier());
        $this->assertSame('email', $context->getType());
        $this->assertSame('Domains', $context->getName());
        $this->assertSame(2.5, $context->getSpamRatingFactor());
        $this->assertSame(['type' => 'text', 'rating' => 3], $context->getItemOptions());
        $this->assertSame($destination, $context->getDestination());

        $source = $context->getSource();
        $this->assertInstanceOf(FileElement::class, $source);
        $this->assertSame('/profiles/demo/input/list.txt', $source->getFilePath());
        $this->assertSame(['foo' => 'bar'], $source->getOptions());
    }

    public function testPrepareRuleContextKeepsAbsoluteFilePath(): void
    {
        $rpContext = ContextFactory::rulePackageContext('/profiles/demo', new TaskFileElement('/tmp/tasks'));

        $context = (new ListInputType())->prepareRuleContext($rpContext, [
            'source' => ['type' => 'file', 'path' => '/data/list.txt'],
        ]);

        $this->assertSame('/data/list.txt', $context->getSource()->getFilePath());
    }

    public function testPrepareRuleContextWithWebSourceAndDefaults(): void
    {
        $rpContext = ContextFactory::rulePackageContext('/profiles/demo', new TaskFileElement('/tmp/tasks'));

        $context = (new TableInputType())->prepareRuleContext($rpContext, [
            'source' => ['type' => 'web', 'url' => 'https://example.com/list.csv'],
        ]);

        $this->assertInstanceOf(UrlElement::class, $context->getSource());
        $this->assertSame('https://example.com/list.csv', $context->getSource()->getUrl());
        $this->assertSame([], $context->getSource()->getOptions());

        $this->assertNotEmpty($context->getIdentifier(), 'A unique identifier is generated when none is given.');
        $this->assertSame('', $context->getType());
        $this->assertSame('', $context->getName());
        $this->assertSame(1.0, $context->getSpamRatingFactor());
        $this->assertSame([], $context->getItemOptions());
    }

    public function testPrepareRuleContextWithInvalidSourceType(): void
    {
        $rpContext = ContextFactory::rulePackageContext('/profiles/demo', new TaskFileElement('/tmp/tasks'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid source type');

        (new ListInputType())->prepareRuleContext($rpContext, ['source' => ['type' => 'ftp']]);
    }
}
