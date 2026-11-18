<?php

namespace MosparoRpg\Tests\Support;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Creates an isolated temporary directory per test and removes it afterwards.
 */
trait TemporaryDirectoryTrait
{
    protected ?string $tmpDir = null;

    protected function createTemporaryDirectory(): string
    {
        $this->tmpDir = sys_get_temp_dir() . '/mosparo_rpg_test_' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->tmpDir);

        return $this->tmpDir;
    }

    protected function removeTemporaryDirectory(): void
    {
        if ($this->tmpDir !== null) {
            (new Filesystem())->remove($this->tmpDir);
            $this->tmpDir = null;
        }
    }

    protected function tmpPath(string $relativePath): string
    {
        return $this->tmpDir . '/' . ltrim($relativePath, '/');
    }
}
