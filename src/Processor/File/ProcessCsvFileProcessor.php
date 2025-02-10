<?php

namespace MosparoRpg\Processor\File;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class ProcessCsvFileProcessor implements ProcessorInterface
{
    const KEY = 'process_csv_file';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleContext && $context->getSource() instanceof FileElement && $context->getSource()->getExtension() === 'csv';
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof RuleContext)) {
            return true;
        }

        $fileHandle = fopen($context->getSource()->getFilePath(), 'r');

        $skipRows = $context->getSource()->getOption('skipRows', 0);
        $mapping = $context->getSource()->getOption('mapping', []);

        $itemColumn = $mapping['type'] ?? 0;
        $valueColumn = $mapping['value'] ?? 1;
        $ratingColumn = $mapping['rating'] ?? 2;

        $rowCounter = 0;
        while (($line = fgetcsv($fileHandle, null, $context->getSource()->getOption('separator', ',')))) {
            if ($rowCounter < $skipRows) {
                $rowCounter++;
                continue;
            }

            $ruleItemContext = (new RuleItemContext($context))
                ->setType(trim($line[$itemColumn]))
                ->setValue(trim($line[$valueColumn]))
                ->setRating(floatval($line[$ratingColumn]));

            $mainProcessor->process($ruleItemContext, $context->getInputType()->getRuleItemProcessors());
        }

        return true;
    }
}