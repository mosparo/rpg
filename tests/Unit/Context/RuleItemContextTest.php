<?php

namespace MosparoRpg\Tests\Unit\Context;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Type\Input\ListInputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class RuleItemContextTest extends TestCase
{
    private RuleContext $ruleContext;

    protected function setUp(): void
    {
        $this->ruleContext = (new RuleContext(new RulePackageContext(), new ListInputType()))
            ->setType('email')
            ->setName('Domains');
    }

    public function testDefaults(): void
    {
        $context = new RuleItemContext($this->ruleContext);

        $this->assertSame($this->ruleContext, $context->getRuleContext());
        $this->assertNull($context->getUuid());
        $this->assertNull($context->getType());
        $this->assertNull($context->getValue());
        $this->assertSame(1.0, $context->getRating());
    }

    public function testSettersAndToArray(): void
    {
        $uuid = Uuid::v4();
        $context = (new RuleItemContext($this->ruleContext))
            ->setUuid($uuid)
            ->setType('text')
            ->setValue('example.com')
            ->setRating(4.5);

        $this->assertSame([
            'uuid' => $uuid,
            'type' => 'text',
            'value' => 'example.com',
            'rating' => 4.5,
        ], $context->toArray());
    }

    public function testBuildIdentifierContainsItemTypeAndValue(): void
    {
        $context = (new RuleItemContext($this->ruleContext))
            ->setType('text')
            ->setValue('example.com');

        $this->assertStringEndsWith('/text_example.com', $context->buildIdentifier());
    }

    public function testBuildIdentifierIsDeterministicAndDistinct(): void
    {
        $a = (new RuleItemContext($this->ruleContext))->setType('text')->setValue('a');
        $a2 = (new RuleItemContext($this->ruleContext))->setType('text')->setValue('a');
        $b = (new RuleItemContext($this->ruleContext))->setType('text')->setValue('b');

        $this->assertSame($a->buildIdentifier(), $a2->buildIdentifier());
        $this->assertNotSame($a->buildIdentifier(), $b->buildIdentifier());
    }

    public function testBuildIdentifierDependsOnTheRule(): void
    {
        $otherRule = (new RuleContext(new RulePackageContext(), new ListInputType()))
            ->setType('email')
            ->setName('Other');

        $a = (new RuleItemContext($this->ruleContext))->setType('text')->setValue('a');
        $b = (new RuleItemContext($otherRule))->setType('text')->setValue('a');

        $this->assertNotSame($a->buildIdentifier(), $b->buildIdentifier());
    }

    public function testToString(): void
    {
        $context = (new RuleItemContext($this->ruleContext))->setType('text')->setValue('v');

        $this->assertSame(sprintf('item:-|%d|text|v', spl_object_id($context)), (string) $context);
    }
}
