<?php

namespace MosparoRpg\Processor\File;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\OutputContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;
use Symfony\Component\Filesystem\Path;

class StoreRulePackageInFileProcessor implements ProcessorInterface
{
    const KEY = 'store_rule_package_in_file';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof OutputContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof OutputContext)) {
            return true;
        }

        $rpContext = $context->getRulePackageContext();

        $filePath = $context->getOption('file_path', null);

        // Store the rule package
        $destination = new FileElement(Path::makeAbsolute(Path::canonicalize($filePath), $rpContext->getProfileDirectory()->getPath()));
        $destination->copyFromFile($rpContext->getDestination(), false);

        // Generate the SHA256 hash
        file_put_contents(
            $destination->getFilePath() . '.sha256',
            hash_file('sha256', $destination->getFilePath())
        );

        return true;
    }
}