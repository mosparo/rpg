<?php

namespace MosparoRpg\Type\Output;

use MosparoRpg\Processor\Storage\StoreRulePackageInMosparoProcessor;
use MosparoRpg\Trait\KeyTrait;
use MosparoRpg\Type\AbstractOutputType;
use MosparoRpg\Type\OutputTypeInterface;

class MosparoApiOutputType extends AbstractOutputType
{
    const KEY = 'mosparo_api';

    use KeyTrait;

    public function getProcessors(): array
    {
        return $this->mergeProcessors(
            parent::getProcessors(),
            [
                OutputTypeInterface::PRIORITY_MAIN => [
                    StoreRulePackageInMosparoProcessor::class,
                ],
            ]
        );
    }
}