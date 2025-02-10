<?php

namespace MosparoRpg\Type\Input;

use MosparoRpg\Processor\File\ProcessCsvFileProcessor;
use MosparoRpg\Trait\KeyTrait;
use MosparoRpg\Type\AbstractInputType;
use MosparoRpg\Type\InputTypeInterface;

class TableInputType extends AbstractInputType
{
    const KEY = 'table';

    use KeyTrait;

    public function getProcessors(): array
    {
        return $this->mergeProcessors(
            parent::getProcessors(),
            [
                InputTypeInterface::PRIORITY_MAIN => [
                    ProcessCsvFileProcessor::class,
                ],
            ]
        );
    }
}