<?php

namespace MosparoRpg\Context;

use Symfony\Component\Uid\Uuid;

class RuleItemContext implements ContextInterface
{
    protected RuleContext $ruleContext;

    protected ?Uuid $uuid = null;

    protected ?string $type = null;

    protected ?string $value = null;

    protected float $rating = 1.0;

    public function __construct(RuleContext $ruleContext)
    {
        $this->ruleContext = $ruleContext;
    }

    public function getRuleContext(): RuleContext
    {
        return $this->ruleContext;
    }

    public function setUuid(Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setRating(float $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function buildIdentifier(): string
    {
        return sprintf('%s/%s_%s', $this->ruleContext->buildIdentifier(), $this->type, $this->value);
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'value' => $this->value,
            'rating' => $this->rating,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'item:%s|%s|%s|%s',
            $this->uuid ?? '-',
            spl_object_id($this),
            $this->type,
            $this->value
        );
    }
}