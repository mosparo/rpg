<?php

namespace MosparoRpg\Processor\File;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class GzippedFileProcessor implements ProcessorInterface
{
    const KEY = 'gzipped_file';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return extension_loaded('zlib') && $context instanceof RuleContext
            && $context->getSource() instanceof FileElement && mime_content_type($context->getSource()->getFilePath()) === 'application/gzip';
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!extension_loaded('zlib') || !($context instanceof RuleContext)) {
            return true;
        }

        $fileHandle = gzopen($context->getSource()->getFilePath(), 'rb');
        if ($fileHandle === false) {
            $mainProcessor->getLogger()->error(sprintf('Cannot open gzip file "%s"', $context->getSource()->getFilePath()));
            return false;
        }

        $destination = new TemporaryFileElement($context->getRulePackageContext(), 'extracted');

        while (!feof($fileHandle)) {
            $destination->writeToFile(gzread($fileHandle, 1024 * 1024));
        }

        gzclose($fileHandle);

        $context->setSource($destination);

        return true;
    }
}