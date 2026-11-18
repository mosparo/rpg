<?php

namespace MosparoRpg\Tests\Unit\Helper;

use Exception;
use MosparoRpg\Helper\UuidHelper;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UuidHelperTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $path;

    protected function setUp(): void
    {
        $this->path = $this->createTemporaryDirectory() . '/profile.rpuuids';
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testThrowsWithoutFilePath(): void
    {
        $this->expectException(Exception::class);

        (new UuidHelper())->findUuidForIdentifier('abc', 'r');
    }

    public function testUnknownIdentifierReturnsNullAndCreatesFile(): void
    {
        $helper = (new UuidHelper())->setUuidFilePath($this->path);

        $this->assertNull($helper->findUuidForIdentifier('email_Domains', 'r'));
        $this->assertFileExists($this->path);
    }

    public function testStoreAndReloadIndex(): void
    {
        $ruleUuid = Uuid::v4();
        $itemUuid = Uuid::v4();

        $helper = (new UuidHelper())->setUuidFilePath($this->path);
        $helper->storeUuid('email_Domains', 'r', $ruleUuid);
        $helper->storeUuid('Domains/text_example.com', 'i', $itemUuid);
        $helper->storeNewUuidIndex();

        $content = file_get_contents($this->path);
        $this->assertStringContainsString('r/' . md5('email_Domains') . '::' . $ruleUuid, $content);
        $this->assertStringContainsString('i/' . md5('Domains/text_example.com') . '::' . $itemUuid, $content);

        $reloaded = (new UuidHelper())->setUuidFilePath($this->path);
        $this->assertEquals($ruleUuid, $reloaded->findUuidForIdentifier('email_Domains', 'r'));
        $this->assertEquals($itemUuid, $reloaded->findUuidForIdentifier('Domains/text_example.com', 'i'));
        $this->assertNull($reloaded->findUuidForIdentifier('email_Domains', 'i'), 'Rule and item namespaces must not collide.');
    }

    public function testIdentifiersAreAlwaysHashedWithType(): void
    {
        $uuid = Uuid::v4();
        $helper = (new UuidHelper())->setUuidFilePath($this->path);
        $helper->storeUuid('r/custom', 'r', $uuid);
        $helper->storeNewUuidIndex();

        // Even an identifier that looks prefixed is hashed
        $this->assertSame('r/' . md5('r/custom') . '::' . $uuid . PHP_EOL, file_get_contents($this->path));
    }

    public function testInvalidLinesAreIgnored(): void
    {
        $uuid = Uuid::v4();
        file_put_contents($this->path, implode(PHP_EOL, [
            '',
            'garbage',
            'r/' . md5('short') . '::123',
            'r/' . md5('valid') . '::' . $uuid,
            '',
        ]));

        $helper = (new UuidHelper())->setUuidFilePath($this->path);

        $this->assertEquals($uuid, $helper->findUuidForIdentifier('valid', 'r'));
        $this->assertNull($helper->findUuidForIdentifier('short', 'r'));
    }

    public function testStoreNewUuidIndexWithoutNewUuidsKeepsFile(): void
    {
        $uuid = Uuid::v4();
        file_put_contents($this->path, 'r/a::' . $uuid . PHP_EOL);

        (new UuidHelper())->setUuidFilePath($this->path)->storeNewUuidIndex();

        $this->assertSame('r/a::' . $uuid . PHP_EOL, file_get_contents($this->path));
    }

    public function testStoreNewUuidIndexReplacesOldEntries(): void
    {
        file_put_contents($this->path, 'r/' . md5('old') . '::' . Uuid::v4() . PHP_EOL);

        $helper = (new UuidHelper())->setUuidFilePath($this->path);
        $helper->storeUuid('new', 'r', $newUuid = Uuid::v4());
        $helper->storeNewUuidIndex();

        $this->assertSame('r/' . md5('new') . '::' . $newUuid . PHP_EOL, file_get_contents($this->path));
    }
}
