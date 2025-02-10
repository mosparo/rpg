<?php

namespace MosparoRpg\Type;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RulePackageContext;

interface InputTypeInterface
{
    const PRIORITY_ORGANIZE = 100;
    const PRIORITY_EXTRACT = 150;
    const PRIORITY_PREPARE = 200;
    const PRIORITY_OBJECT_START = 250;
    const PRIORITY_MAIN = 500;
    const PRIORITY_OBJECT_END = 850;
    const PRIORITY_FINALIZE = 900;


    public function getKey(): string;

    public function prepareRuleContext(RulePackageContext $rulePackageContext, array $input): RuleContext;

    public function getRuleItemProcessors(): array;
}