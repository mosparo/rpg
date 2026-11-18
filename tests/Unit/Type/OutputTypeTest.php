<?php

namespace MosparoRpg\Tests\Unit\Type;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Processor\Storage\StoreRulePackageInFileProcessor;
use MosparoRpg\Processor\Storage\StoreRulePackageInMosparoProcessor;
use MosparoRpg\Type\Output\FileOutputType;
use MosparoRpg\Type\Output\MosparoApiOutputType;
use MosparoRpg\Type\OutputTypeInterface;
use PHPUnit\Framework\TestCase;

class OutputTypeTest extends TestCase
{
    public function testKeys(): void
    {
        $this->assertSame('file', (new FileOutputType())->getKey());
        $this->assertSame('mosparo_api', (new MosparoApiOutputType())->getKey());
    }

    public function testProcessors(): void
    {
        $this->assertSame(
            [OutputTypeInterface::PRIORITY_MAIN => [StoreRulePackageInFileProcessor::class]],
            (new FileOutputType())->getProcessors()
        );
        $this->assertSame(
            [OutputTypeInterface::PRIORITY_MAIN => [StoreRulePackageInMosparoProcessor::class]],
            (new MosparoApiOutputType())->getProcessors()
        );
    }

    public function testPrepareOutputContext(): void
    {
        $rpContext = new RulePackageContext();
        $type = new FileOutputType();

        $context = $type->prepareOutputContext($rpContext, [
            'identifier' => 'json-out',
            'options' => ['file_path' => 'out.json'],
        ]);

        $this->assertSame($rpContext, $context->getRulePackageContext());
        $this->assertSame($type, $context->getOutputType());
        $this->assertSame('json-out', $context->getIdentifier());
        $this->assertSame(['file_path' => 'out.json'], $context->getOptions());
    }

    public function testPrepareOutputContextDefaults(): void
    {
        $context = (new MosparoApiOutputType())->prepareOutputContext(new RulePackageContext(), []);

        $this->assertNotEmpty($context->getIdentifier());
        $this->assertSame([], $context->getOptions());
    }
}
