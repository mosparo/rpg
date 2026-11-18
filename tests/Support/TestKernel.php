<?php

namespace MosparoRpg\Tests\Support;

use MosparoRpg\Kernel;

/**
 * Same as the application kernel, but keeps cache and logs out of the project directory.
 */
class TestKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/mosparo_rpg_test_kernel/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/mosparo_rpg_test_kernel/log';
    }
}
