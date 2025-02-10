<?php

namespace MosparoRpg\Context;

use MosparoRpg\Element\ElementInterface;
use MosparoRpg\Type\OutputTypeInterface;

class OutputContext implements ContextInterface
{
    protected RulePackageContext $rulePackageContext;

    protected OutputTypeInterface $outputType;

    protected ?string $identifier = null;
    
    protected array $options = [];
    
    protected ?ElementInterface $destination = null;
    
    public function __construct(RulePackageContext $rulePackageContext, OutputTypeInterface $outputType)
    {
        $this->rulePackageContext = $rulePackageContext;
        $this->outputType = $outputType;
    }

    public function getRulePackageContext(): RulePackageContext
    {
        return $this->rulePackageContext;
    }

    public function getOutputType(): OutputTypeInterface
    {
        return $this->outputType;
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

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $defaultValue): mixed
    {
        if (!isset($this->options[$key])) {
            return $defaultValue;
        }

        return $this->options[$key];
    }

    public function setDestination(ElementInterface $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDestination(): ?ElementInterface
    {
        return $this->destination;
    }

    public function buildIdentifier(): string
    {
        return sprintf('%s', $this->identifier);
    }

    public function __toString(): string
    {
        return sprintf(
            'output:%s',
            $this->identifier ?? spl_object_id($this),
        );
    }
}