<?php

namespace MosparoRpg;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const RPG_VERSION = '1.0.0-beta.3';

    /**
     * The project directory is fixed to the parent of src/ so that it does not
     * depend on a composer.json being present. Box removes composer.json from
     * the PHAR, which would otherwise make Symfony fall back to src/.
     */
    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }
}
