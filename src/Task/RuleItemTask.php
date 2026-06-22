<?php

namespace MosparoRpg\Task;

class RuleItemTask
{
    protected string $ruleUuid;

    protected string $uuid;

    protected string $type;

    protected mixed $value;

    protected ?float $rating;

    public function __construct(string $ruleUuid, string $uuid, string $type, mixed $value, ?float $rating)
    {
        $this->ruleUuid = $ruleUuid;
        $this->uuid = $uuid;
        $this->type = $type;
        $this->value = $value;
        $this->rating = $rating;
    }

    public function getRuleUuid(): string
    {
        return $this->ruleUuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }
}