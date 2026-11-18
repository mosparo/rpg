<?php

namespace MosparoRpg\Tests\Unit\Context;

use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Helper\CliOutputHelper;
use MosparoRpg\Type\Output\FileOutputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class OutputContextTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $rpContext = new RulePackageContext();
        $type = new FileOutputType();
        $helper = new CliOutputHelper(new NullOutput());
        $destination = new FileElement('/tmp/out.json');

        $context = (new OutputContext($rpContext, $type))
            ->setIdentifier('out')
            ->setOptions(['format' => 'zip'])
            ->setCliOutputHelper($helper)
            ->setDestination($destination);

        $this->assertSame($rpContext, $context->getRulePackageContext());
        $this->assertSame($type, $context->getOutputType());
        $this->assertSame($helper, $context->getCliOutputHelper());
        $this->assertSame('out', $context->getIdentifier());
        $this->assertSame(['format' => 'zip'], $context->getOptions());
        $this->assertSame($destination, $context->getDestination());
    }

    public function testGetOptionFallsBackToDefault(): void
    {
        $context = (new OutputContext(new RulePackageContext(), new FileOutputType()))
            ->setOptions(['format' => 'zip', 'empty' => null]);

        $this->assertSame('zip', $context->getOption('format', 'json'));
        $this->assertSame('json', $context->getOption('missing', 'json'));
        // null values count as "not set"
        $this->assertSame('default', $context->getOption('empty', 'default'));
    }

    public function testIdentifierAndToString(): void
    {
        $context = (new OutputContext(new RulePackageContext(), new FileOutputType()))->setIdentifier('main');

        $this->assertSame('main', $context->buildIdentifier());
        $this->assertSame('output:main', (string) $context);
    }

    public function testToStringWithoutIdentifier(): void
    {
        $context = new OutputContext(new RulePackageContext(), new FileOutputType());

        $this->assertSame('output:' . spl_object_id($context), (string) $context);
    }
}
