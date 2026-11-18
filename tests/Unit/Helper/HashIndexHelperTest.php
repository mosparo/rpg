<?php

namespace MosparoRpg\Tests\Unit\Helper;

use MosparoRpg\Helper\HashIndexHelper;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use Symfony\Component\Uid\UuidV4;

class HashIndexHelperTest extends TestCase
{
    private const UUID_A = '2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f';
    private const UUID_B = '6c1d6f1a-7c5f-4b8e-8a3b-1d2e3f4a5b6c';
    private const UUID_C = '7d2e7a2b-8d6a-4c9f-9b4c-2e3f4a5b6c7d';

    private function createIndexFile(string $content): SplFileObject
    {
        $file = new SplTempFileObject();
        $file->fwrite($content);

        return $file;
    }

    public function testFindsHashAndRemovesItFromRemaining(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(
            self::UUID_A . "::r/hashA/1\n" .
            self::UUID_B . "::i/hashB/2\n"
        ));

        $this->assertSame('r/hashA/1', $helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_A)));
        $this->assertSame(['i/hashB/2'], array_values($helper->getRemainingHashes()));
    }

    public function testUnknownUuidReturnsNull(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(self::UUID_A . "::r/hashA/1\n"));

        $this->assertNull($helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_B)));
        $this->assertCount(1, $helper->getRemainingHashes());
    }

    public function testHashIsOnlyReturnedOnce(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(
            self::UUID_A . "::r/hashA/1\n" .
            self::UUID_B . "::i/hashB/2\n"
        ));

        $uuid = UuidV4::fromString(self::UUID_A);
        $this->assertNotNull($helper->findIndexHashForUuid($uuid));
        $this->assertNull($helper->findIndexHashForUuid($uuid));
    }

    public function testEmptyLinesAreIgnored(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile("\n" . self::UUID_A . "::r/hashA/1\n\n"));

        $this->assertSame('r/hashA/1', $helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_A)));
    }

    public function testEmptyIndex(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(''));

        $this->assertNull($helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_A)));
        $this->assertSame([], $helper->getRemainingHashes());
    }

    /**
     * With an empty rule package no lookup happens. All entries in mosparo must then count as remaining (outdated).
     */
    public function testRemainingHashesLoadIndexWithoutLookup(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(
            self::UUID_A . "::r/hashA/1\n" .
            self::UUID_B . "::i/hashB/2\n"
        ));

        $this->assertSame(['r/hashA/1', 'i/hashB/2'], array_values($helper->getRemainingHashes()));
    }

    /**
     * Once every entry has been consumed, the next lookup must not rebuild the index from the file.
     * Otherwise already matched entries show up as "remaining" again and are deleted in mosparo.
     */
    public function testConsumedEntriesDoNotComeBackAfterIndexIsEmpty(): void
    {
        $helper = new HashIndexHelper($this->createIndexFile(self::UUID_A . "::r/hashA/1\n"));

        $this->assertSame('r/hashA/1', $helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_A)));
        $this->assertNull($helper->findIndexHashForUuid(UuidV4::fromString(self::UUID_C)));

        $this->assertSame([], $helper->getRemainingHashes());
    }
}
