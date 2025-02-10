<?php

namespace MosparoRpg\Processor\File;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class ZippedFileProcessor implements ProcessorInterface
{
    const KEY = 'zipped_file';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return extension_loaded('zip') && $context instanceof RuleContext
            && $context->getSource() instanceof FileElement && mime_content_type($context->getSource()->getFilePath()) === 'application/zip';
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!extension_loaded('zip') || !($context instanceof RuleContext)) {
            return true;
        }

        $zip = new \ZipArchive();
        $result = $zip->open($context->getSource()->getFilePath(), \ZipArchive::RDONLY);

        if ($result !== true) {
            $mainProcessor->getLogger()->error(sprintf('Cannot open ZIP archive "%s". Error: %s', $context->getSource()->getFilePath(), $result));
            return false;
        }

        if ($zip->count() > 1) {
            $mainProcessor->getLogger()->error(sprintf('The ZIP archive "%s" contains more than one file (total: %d).', $context->getSource()->getFilePath(), $zip->count()));
            return false;
        }

        $firstFileName = $zip->getNameIndex(0);
        $destination = new TemporaryFileElement($context->getRulePackageContext(), 'extracted');

        $streamHandle = $zip->getStream($firstFileName);
        if (!$streamHandle) {
            $mainProcessor->getLogger()->error(sprintf('Cannot open file "%s" in the ZIP archive "%s".', $firstFileName, $context->getSource()->getFilePath()));
            return false;
        }

        while (!feof($streamHandle)) {
            $destination->writeToFile(fread($streamHandle, 1024 * 1024));
        }

        fclose($streamHandle);

        $context->setSource($destination);

        return true;
    }
}