<?php

namespace MosparoRpg\Tests\Support;

use ArrayObject;
use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;

/**
 * Test double that records its calls into a shared log.
 */
final class RecordingProcessor implements ProcessorInterface
{
    /**
     * @param ArrayObject<int, string> $log
     */
    public function __construct(
        private readonly string $name,
        private readonly ArrayObject $log,
        private readonly bool $applicable = true,
        private readonly bool $result = true,
    ) {
    }

    public function getKey(): string
    {
        return $this->name;
    }

    public function isApplicable(ContextInterface $context): bool
    {
        return $this->applicable;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        $this->log[] = $this->name;

        return $this->result;
    }
}
