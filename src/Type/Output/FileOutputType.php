<?php

namespace MosparoRpg\Type\Output;

use MosparoRpg\Processor\File\StoreRulePackageInFileProcessor;
use MosparoRpg\Trait\KeyTrait;
use MosparoRpg\Type\AbstractOutputType;
use MosparoRpg\Type\OutputTypeInterface;

class FileOutputType extends AbstractOutputType
{
    const KEY = 'file';

    use KeyTrait;

    public function getProcessors(): array
    {
        return $this->mergeProcessors(
            parent::getProcessors(),
            [
                OutputTypeInterface::PRIORITY_MAIN => [
                    StoreRulePackageInFileProcessor::class,
                ],
            ]
        );
    }
}