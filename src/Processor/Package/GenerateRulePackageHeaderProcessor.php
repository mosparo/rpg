<?php

namespace MosparoRpg\Processor\Package;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class GenerateRulePackageHeaderProcessor implements ProcessorInterface
{
    const KEY = 'generate_rule_package_header';

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

        $rulePackageContent = sprintf(
            '{"lastUpdatedAt":%s,"refreshInterval":%d,"rules":[',
            json_encode((new \DateTime())->format(\DateTimeInterface::ATOM)),
            $profile['refresh_interval'] ?? 86400
        );

        $context->getDestination()->writeToFile($rulePackageContent);

        return true;
    }
}