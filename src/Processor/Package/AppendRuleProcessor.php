<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class AppendRuleProcessor implements ProcessorInterface
{
    const KEY = 'append_rule';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof RuleContext)) {
            return true;
        }

        $rulePackageContext = $context->getRulePackageContext();
        $destination = $rulePackageContext->getDestination();

        if ($rulePackageContext->getRuleCounter()) {
            $destination->writeToFile(',');
        }

        $destination->copyFromFile($context->getDestination());

        $rulePackageContext->increaseRuleCounter();

        return true;
    }
}