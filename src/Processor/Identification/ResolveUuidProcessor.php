<?php

namespace MosparoRpg\Processor\Identification;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RuleItemContext;
use MosparoRpg\Helper\UuidHelper;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;
use Symfony\Component\Uid\Uuid;

class ResolveUuidProcessor implements ProcessorInterface
{
    const KEY = 'resolve_uuid';

    use KeyTrait;

    protected UuidHelper $uuidHelper;

    public function __construct(UuidHelper $uuidHelper)
    {
        $this->uuidHelper = $uuidHelper;
    }

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleContext || $context instanceof RuleItemContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        $identifier = $context->buildIdentifier();

        $type = null;
        if ($context instanceof RuleContext) {
            $type = 'r';
        } else if ($context instanceof RuleItemContext) {
            $type = 'i';
        }

        $uuid = $this->uuidHelper->findUuidForIdentifier($identifier, $type);
        if ($uuid === null) {
            $uuid = Uuid::v4();
        }

        $this->uuidHelper->storeUuid($identifier, $type, $uuid);

        $context->setUuid($uuid);

        return true;
    }
}