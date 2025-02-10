<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class GenerateRuleHeaderProcessor implements ProcessorInterface
{
    const KEY = 'generate_rule_header';

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

        $ruleHeader = sprintf(
            '{"uuid":%s,"name":%s,"type":%s,"spamRatingFactor":%f,"items":[',
            json_encode($context->getUuid()),
            json_encode($context->getName()),
            json_encode($context->getType()),
            $context->getSpamRatingFactor()
        );

        $context->getDestination()->writeToFile($ruleHeader);

        return true;
    }
}