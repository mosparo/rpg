<?php

namespace MosparoRpg\Context;

use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TemporaryFileElement;

class RulePackageContext implements ContextInterface
{
    protected ?string $name = null;

    protected ?int $refreshInterval = null;

    protected ?DirectoryElement $profileDirectory = null;

    protected ?DirectoryElement $temporaryDirectory = null;

    protected ?TemporaryFileElement $destination = null;

    protected int $ruleCounter = 0;

    protected array $temporaryFiles = [];

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setRefreshInterval(int $refreshInterval): self
    {
        $this->refreshInterval = $refreshInterval;

        return $this;
    }

    public function getRefreshInterval(): ?int
    {
        return $this->refreshInterval;
    }

    public function setProfileDirectory(DirectoryElement $profileDirectory): self
    {
        $this->profileDirectory = $profileDirectory;

        return $this;
    }

    public function getProfileDirectory(): ?DirectoryElement
    {
        return $this->profileDirectory;
    }

    public function setTemporaryDirectory(DirectoryElement $temporaryDirectory): self
    {
        $this->temporaryDirectory = $temporaryDirectory;

        return $this;
    }

    public function getTemporaryDirectory(): ?DirectoryElement
    {
        return $this->temporaryDirectory;
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

    public function increaseRuleCounter(): void
    {
        $this->ruleCounter++;
    }

    public function getRuleCounter(): int
    {
        return $this->ruleCounter;
    }

    public function buildIdentifier(): string
    {
        return str_replace('/', '_', $this->name);
    }

    public function addTemporaryFile(string $filePath): self
    {
        $this->temporaryFiles[] = $filePath;

        return $this;
    }

    public function getTemporaryFiles(): array
    {
        return $this->temporaryFiles;
    }

    public function __toString(): string
    {
        return sprintf(
            'package:%s',
            $this->name ?? spl_object_id($this)
        );
    }
}