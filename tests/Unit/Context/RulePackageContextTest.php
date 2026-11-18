<?php

namespace MosparoRpg\Tests\Unit\Context;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TaskFileElement;
use PHPUnit\Framework\TestCase;

class RulePackageContextTest extends TestCase
{
    public function testDefaults(): void
    {
        $context = new RulePackageContext();

        $this->assertNull($context->getName());
        $this->assertNull($context->getRefreshInterval());
        $this->assertNull($context->getProfileDirectory());
        $this->assertNull($context->getTemporaryDirectory());
        $this->assertNull($context->getDestination());
        $this->assertSame(0, $context->getRuleCounter());
        $this->assertSame([], $context->getTemporaryFiles());
    }

    public function testSettersAndGetters(): void
    {
        $profileDir = new DirectoryElement('/profile');
        $tmpDir = new DirectoryElement('/tmp');
        $destination = new TaskFileElement('/tmp/tasks');

        $context = (new RulePackageContext())
            ->setName('My package')
            ->setRefreshInterval(600)
            ->setProfileDirectory($profileDir)
            ->setTemporaryDirectory($tmpDir)
            ->setDestination($destination);

        $this->assertSame('My package', $context->getName());
        $this->assertSame(600, $context->getRefreshInterval());
        $this->assertSame($profileDir, $context->getProfileDirectory());
        $this->assertSame($tmpDir, $context->getTemporaryDirectory());
        $this->assertSame($destination, $context->getDestination());
    }

    public function testRuleCounter(): void
    {
        $context = new RulePackageContext();
        $context->increaseRuleCounter();
        $context->increaseRuleCounter();

        $this->assertSame(2, $context->getRuleCounter());
    }

    public function testTemporaryFilesAreCollected(): void
    {
        $context = (new RulePackageContext())
            ->addTemporaryFile('/tmp/a')
            ->addTemporaryFile('/tmp/b');

        $this->assertSame(['/tmp/a', '/tmp/b'], $context->getTemporaryFiles());
    }

    public function testBuildIdentifierReplacesSlashes(): void
    {
        $context = (new RulePackageContext())->setName('vendor/package/name');

        $this->assertSame('vendor_package_name', $context->buildIdentifier());
    }

    public function testToString(): void
    {
        $this->assertSame('package:abc', (string) (new RulePackageContext())->setName('abc'));

        $unnamed = new RulePackageContext();
        $this->assertSame('package:' . spl_object_id($unnamed), (string) $unnamed);
    }
}
