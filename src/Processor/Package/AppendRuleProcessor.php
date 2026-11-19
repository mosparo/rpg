<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Task\RuleTask;
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

        $destination = $context->getRulePackageContext()->getDestination();

        $task = new RuleTask(
            $context->getUuid(),
            $context->getType(),
            $context->getName(),
            $context->getDescription(),
            $context->getSpamRatingFactor()
        );
        $destination->writeTaskToFile($task);

        return true;
    }
}
