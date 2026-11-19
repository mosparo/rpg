<?php

namespace MosparoRpg\Tests\Unit\Context;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Type\Input\ListInputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class RuleContextTest extends TestCase
{
    public function testDefaults(): void
    {
        $rpContext = new RulePackageContext();
        $inputType = new ListInputType();
        $context = new RuleContext($rpContext, $inputType);

        $this->assertSame($rpContext, $context->getRulePackageContext());
        $this->assertSame($inputType, $context->getInputType());
        $this->assertNull($context->getUuid());
        $this->assertNull($context->getIdentifier());
        $this->assertNull($context->getType());
        $this->assertNull($context->getName());
        $this->assertNull($context->getDescription());
        $this->assertNull($context->getSpamRatingFactor());
        $this->assertSame([], $context->getItemOptions());
        $this->assertNull($context->getSource());
        $this->assertNull($context->getDestination());
        $this->assertSame(0, $context->getItemCounter());
    }

    public function testSettersAndGetters(): void
    {
        $uuid = Uuid::v4();
        $source = new UrlElement('https://example.com/list.txt');
        $destination = new TaskFileElement('/tmp/tasks');

        $context = (new RuleContext(new RulePackageContext(), new ListInputType()))
            ->setUuid($uuid)
            ->setIdentifier('id')
            ->setType('word')
            ->setName('Bad words')
            ->setDescription('Words that are often used in spam.')
            ->setSpamRatingFactor(2.5)
            ->setItemOptions(['type' => 'text', 'rating' => 3])
            ->setSource($source)
            ->setDestination($destination);

        $context->increaseItemCounter();

        $this->assertSame($uuid, $context->getUuid());
        $this->assertSame('id', $context->getIdentifier());
        $this->assertSame('word', $context->getType());
        $this->assertSame('Bad words', $context->getName());
        $this->assertSame('Words that are often used in spam.', $context->getDescription());
        $this->assertSame(2.5, $context->getSpamRatingFactor());
        $this->assertSame(['type' => 'text', 'rating' => 3], $context->getItemOptions());
        $this->assertSame($source, $context->getSource());
        $this->assertSame($destination, $context->getDestination());
        $this->assertSame(1, $context->getItemCounter());
    }

    public function testBuildIdentifier(): void
    {
        $context = (new RuleContext(new RulePackageContext(), new ListInputType()))
            ->setType('email')
            ->setName('Spam domains');

        $this->assertSame('email_Spam domains', $context->buildIdentifier());
    }

    public function testToString(): void
    {
        $uuid = Uuid::fromString('2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f');
        $context = (new RuleContext(new RulePackageContext(), new ListInputType()))
            ->setUuid($uuid)
            ->setIdentifier('my-id')
            ->setName('Name');

        $this->assertSame('rule:2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f|my-id|Name', (string) $context);
    }

    public function testToStringWithoutUuidAndIdentifier(): void
    {
        $context = (new RuleContext(new RulePackageContext(), new ListInputType()))->setName('Name');

        $this->assertSame(sprintf('rule:-|%d|Name', spl_object_id($context)), (string) $context);
    }
}
