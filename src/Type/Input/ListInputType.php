<?php

namespace MosparoRpg\Type\Input;

use MosparoRpg\Processor\File\ProcessListFileProcessor;
use MosparoRpg\Trait\KeyTrait;
use MosparoRpg\Type\AbstractInputType;
use MosparoRpg\Type\InputTypeInterface;

class ListInputType extends AbstractInputType
{
    const KEY = 'list';

    use KeyTrait;

    public function getProcessors(): array
    {
        return $this->mergeProcessors(
            parent::getProcessors(),
            [
                InputTypeInterface::PRIORITY_MAIN => [
                    ProcessListFileProcessor::class,
                ],
            ]
        );
    }
}