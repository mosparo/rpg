<?php

namespace MosparoRpg\Context;

use MosparoRpg\Element\ElementInterface;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Type\InputTypeInterface;
use Symfony\Component\Uid\Uuid;

class RuleContext implements ContextInterface
{
    protected RulePackageContext $rulePackageContext;

    protected InputTypeInterface $inputType;

    protected ?Uuid $uuid = null;
    
    protected ?string $identifier = null;
    
    protected ?string $type = null;

    protected ?string $name = null;
    
    protected ?float $spamRatingFactor = null;

    protected array $itemOptions = [];
    
    protected ?ElementInterface $source = null;
    
    protected ?TemporaryFileElement $destination = null;

    protected int $itemCounter = 0;

    public function __construct(RulePackageContext $rulePackageContext, InputTypeInterface $inputType)
    {
        $this->rulePackageContext = $rulePackageContext;
        $this->inputType = $inputType;
    }

    public function getRulePackageContext(): RulePackageContext
    {
        return $this->rulePackageContext;
    }

    public function getInputType(): InputTypeInterface
    {
        return $this->inputType;
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

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
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

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setSpamRatingFactor(float $spamRatingFactor): self
    {
        $this->spamRatingFactor = $spamRatingFactor;

        return $this;
    }

    public function getSpamRatingFactor(): ?float
    {
        return $this->spamRatingFactor;
    }

    public function setItemOptions(array $itemOptions): self
    {
        $this->itemOptions = $itemOptions;

        return $this;
    }

    public function getItemOptions(): array
    {
        return $this->itemOptions;
    }

    public function setSource(ElementInterface $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getSource(): ?ElementInterface
    {
        return $this->source;
    }

    public function setDestination(TemporaryFileElement $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDestination(): ?TemporaryFileElement
    {
        return $this->destination;
    }

    public function increaseItemCounter(): void
    {
        $this->itemCounter++;
    }

    public function getItemCounter(): int
    {
        return $this->itemCounter;
    }

    public function buildIdentifier(): string
    {
        return sprintf('%s_%s', $this->type, $this->name);
    }

    public function __toString(): string
    {
        return sprintf(
            'rule:%s|%s|%s',
            $this->uuid ?? '-',
            $this->identifier ?? spl_object_id($this),
            $this->name
        );
    }
}