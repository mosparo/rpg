<?php

namespace MosparoRpg\Tests\Unit\Util;

use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Util\TemporaryFileUtil;
use PHPUnit\Framework\TestCase;

class TemporaryFileUtilTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testCreateAndRemoveTemporaryFile(): void
    {
        $dir = $this->createTemporaryDirectory();
        $rpContext = ContextFactory::rulePackageContext($dir);

        $path = TemporaryFileUtil::createTemporaryFile($rpContext, 'suffix');

        $this->assertFileExists($path);
        $this->assertSame($dir, dirname($path));
        $this->assertStringStartsWith('mosparo_rpg_suffix_', basename($path));
        $this->assertSame([$path], $rpContext->getTemporaryFiles());

        TemporaryFileUtil::removeTemporaryFile($path);
        $this->assertFileDoesNotExist($path);
    }

    public function testFilesAreUnique(): void
    {
        $rpContext = ContextFactory::rulePackageContext($this->createTemporaryDirectory());

        $this->assertNotSame(
            TemporaryFileUtil::createTemporaryFile($rpContext),
            TemporaryFileUtil::createTemporaryFile($rpContext)
        );
    }

    public function testRemovingMissingFileDoesNotFail(): void
    {
        TemporaryFileUtil::removeTemporaryFile(sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4)));

        $this->addToAssertionCount(1);
    }
}
