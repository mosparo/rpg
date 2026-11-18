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
        while (($line = fgetcsv($fileHandle, null, $context->getSource()->getOption('separator', ','), '"', '\\'))) {
            $rowCounter++;

            if ($rowCounter <= $skipRows) {
                continue;
            }

            $type = trim($line[$itemColumn] ?? '');
            $value = trim($line[$valueColumn] ?? '');
            $rating = trim($line[$ratingColumn] ?? '');

            // Ignore empty rows (also rows with only whitespace or separators)
            if ($type === '' && $value === '' && $rating === '') {
                continue;
            }

            if ($type === '' || $value === '') {
                $mainProcessor->getLogger()->warning(sprintf(
                    'Row %d in "%s" has no type or no value and is ignored.',
                    $rowCounter,
                    $context->getSource()->getFilePath()
                ));
                continue;
            }

            $ruleItemContext = (new RuleItemContext($context))
                ->setType($type)
                ->setValue($value)
                ->setRating(($rating !== '') ? floatval($rating) : 1.0)
            ;

            $mainProcessor->process($ruleItemContext, $context->getInputType()->getRuleItemProcessors());
        }

        return true;
    }
}