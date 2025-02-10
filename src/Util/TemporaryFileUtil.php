<?php

namespace MosparoRpg\Util;

use MosparoRpg\Context\RulePackageContext;
use Symfony\Component\Filesystem\Filesystem;

class TemporaryFileUtil
{
    public static function createTemporaryFile(RulePackageContext $rulePackageContext, string $suffix = ''): string
    {
        $tmpFile = (new Filesystem())->tempnam(
            $rulePackageContext->getTemporaryDirectory()->getPath(),
            sprintf('mosparo_rpg_%s_', $suffix)
        );

        $rulePackageContext->addTemporaryFile($tmpFile);

        return $tmpFile;
    }

    public static function removeTemporaryFile(string $filePath): void
    {
        (new Filesystem())->remove($filePath);
    }
}