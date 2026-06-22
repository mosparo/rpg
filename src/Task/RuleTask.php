<?php

namespace MosparoRpg\Task;

class RuleTask
{
    protected string $uuid;

    protected string $type;

    protected string $name;

    protected ?string $description = null;

    protected ?float $spamRatingFactor = null;

    public function __construct(string $uuid, string $type, string $name, ?string $description, ?float $spamRatingFactor)
    {
        $this->uuid = $uuid;
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->spamRatingFactor = $spamRatingFactor;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSpamRatingFactor(): ?float
    {
        return $this->spamRatingFactor;
    }
}