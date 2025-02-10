<?php

namespace MosparoRpg\Processor\File;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class ProcessListFileProcessor implements ProcessorInterface
{
    const KEY = 'process_list_file';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleContext && $context->getSource() instanceof FileElement;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof RuleContext)) {
            return true;
        }

        $fileHandle = fopen($context->getSource()->getFilePath(), 'r');

        while (($line = fgets($fileHandle))) {
            $ruleItemContext = (new RuleItemContext($context))
                ->setType($context->getItemOptions()['type'] ?? '')
                ->setValue(trim($line))
                ->setRating($context->getItemOptions()['rating'] ?? 1);

            $mainProcessor->process($ruleItemContext, $context->getInputType()->getRuleItemProcessors());
        }

        return true;
    }
}