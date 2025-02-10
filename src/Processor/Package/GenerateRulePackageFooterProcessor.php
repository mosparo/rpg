<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class GenerateRulePackageFooterProcessor implements ProcessorInterface
{
    const KEY = 'generate_rule_package_footer';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RulePackageContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof RulePackageContext)) {
            return true;
        }

        $context->getDestination()->writeToFile(']}');

        return true;
    }
}