<?php

namespace MosparoRpg\Processor;

use MosparoRpg\Context\ContextInterface;

interface ProcessorInterface
{
    public function getKey(): string;

    public function isApplicable(ContextInterface $context): bool;

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool;
}