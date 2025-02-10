<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class GenerateRuleFooterProcessor implements ProcessorInterface
{
    const KEY = 'generate_rule_footer';

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

        $context->getDestination()->writeToFile(']}');

        return true;
    }
}