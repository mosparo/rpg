<?php

namespace MosparoRpg\Type;

use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Trait\MergeProcessorsTrait;

abstract class AbstractOutputType implements OutputTypeInterface, TypeInterface
{
    use MergeProcessorsTrait;

    public function getProcessors(): array
    {
        return [];
    }

    public function prepareOutputContext(RulePackageContext $rulePackageContext, array $input): OutputContext
    {
        $outputContext = (new OutputContext($rulePackageContext, $this))
            ->setIdentifier($input['identifier'] ?? uniqid())
            ->setOptions($input['options'] ?? []);

        return $outputContext;
    }
}