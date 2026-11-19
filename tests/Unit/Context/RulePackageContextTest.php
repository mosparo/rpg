<?php

namespace MosparoRpg\Tests\Unit\Context;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;

class RulePackageContextTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testDefaults(): void
    {
        $context = new RulePackageContext();

        $this->assertNull($context->getName());
        $this->assertNull($context->getRefreshInterval());
        $this->assertNull($context->getLicenseFile());
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
            ->setLicenseFile('LICENSE')
            ->setProfileDirectory($profileDir)
            ->setTemporaryDirectory($tmpDir)
            ->setDestination($destination);

        $this->assertSame('My package', $context->getName());
        $this->assertSame(600, $context->getRefreshInterval());
        $this->assertSame('LICENSE', $context->getLicenseFile());
        $this->assertSame($profileDir, $context->getProfileDirectory());
        $this->assertSame($tmpDir, $context->getTemporaryDirectory());
        $this->assertSame($destination, $context->getDestination());
    }

    public function testReadLicenseFileRelativeToProfileDirectory(): void
    {
        $dir = $this->createTemporaryDirectory();
        mkdir($dir . '/legal');
        file_put_contents($dir . '/legal/LICENSE.txt', "MIT License\n\nCopyright (c) 2026\n");

        $context = (new RulePackageContext())
            ->setProfileDirectory(new DirectoryElement($dir))
            ->setLicenseFile('legal/../legal/LICENSE.txt');

        $this->assertTrue($context->isLicenseFileReadable());
        $this->assertSame("MIT License\n\nCopyright (c) 2026\n", $context->readLicenseFile());
    }

    public function testReadLicenseFileWithAbsolutePath(): void
    {
        $dir = $this->createTemporaryDirectory();
        file_put_contents($dir . '/LICENSE', 'Absolute license');

        $context = (new RulePackageContext())
            ->setProfileDirectory(new DirectoryElement('/does/not/matter'))
            ->setLicenseFile($dir . '/LICENSE');

        $this->assertSame('Absolute license', $context->readLicenseFile());
    }

    public function testLicenseFilePath(): void
    {
        $context = (new RulePackageContext())->setProfileDirectory(new DirectoryElement('/profiles/demo'));
        $this->assertNull($context->getLicenseFilePath(), 'Without a license file, there is no path.');
        $this->assertFalse($context->isLicenseFileReadable());

        $context->setLicenseFile('legal/../LICENSE');
        $this->assertSame('/profiles/demo/LICENSE', $context->getLicenseFilePath());

        $context->setLicenseFile('/srv/LICENSE');
        $this->assertSame('/srv/LICENSE', $context->getLicenseFilePath());
    }

    public function testDirectoryIsNotAReadableLicenseFile(): void
    {
        $dir = $this->createTemporaryDirectory();
        mkdir($dir . '/LICENSE');

        $context = (new RulePackageContext())
            ->setProfileDirectory(new DirectoryElement($dir))
            ->setLicenseFile('LICENSE');

        $this->assertFalse($context->isLicenseFileReadable());
        $this->assertNull($context->readLicenseFile());
    }

    public function testReadLicenseFileReturnsNullForMissingFile(): void
    {
        $dir = $this->createTemporaryDirectory();

        $context = (new RulePackageContext())
            ->setProfileDirectory(new DirectoryElement($dir))
            ->setLicenseFile('does-not-exist.txt');

        $this->assertFalse($context->isLicenseFileReadable());
        $this->assertNull($context->readLicenseFile());
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
