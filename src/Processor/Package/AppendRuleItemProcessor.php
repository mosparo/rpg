<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class AppendRuleItemProcessor implements ProcessorInterface
{
    const KEY = 'append_rule_item';

    use KeyTrait;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleItemContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof RuleItemContext)) {
            return true;
        }

        $ruleContext = $context->getRuleContext();
        $destination = $ruleContext->getDestination();

        $ruleItemJson = json_encode($context->toArray());

        if ($ruleContext->getItemCounter()) {
            $destination->writeToFile(',');
        }

        $destination->writeToFile($ruleItemJson);

        $ruleContext->increaseItemCounter();

        return true;
    }
}