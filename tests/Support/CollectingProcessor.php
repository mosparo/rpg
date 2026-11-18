<?php

namespace MosparoRpg\Tests\Support;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;

/**
 * Test double that collects every context it receives.
 */
final class CollectingProcessor implements ProcessorInterface
{
    /** @var array<int, ContextInterface> */
    public array $contexts = [];

    public function getKey(): string
    {
        return 'collecting';
    }

    public function isApplicable(ContextInterface $context): bool
    {
        return true;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        $this->contexts[] = $context;

        return true;
    }
}
