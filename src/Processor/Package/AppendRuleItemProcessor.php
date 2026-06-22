<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Task\RuleItemTask;
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

        $task = new RuleItemTask(
            $ruleContext->getUuid(),
            $context->getUuid(),
            $context->getType(),
            $context->getValue(),
            $context->getRating()
        );
        $destination->writeTaskToFile($task);

        return true;
    }
}