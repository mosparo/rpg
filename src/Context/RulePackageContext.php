<?php

namespace MosparoRpg\Context;

use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TaskFileElement;
use Symfony\Component\Filesystem\Path;

class RulePackageContext implements ContextInterface
{
    protected ?string $name = null;

    protected ?int $refreshInterval = null;

    protected ?string $licenseFile = null;

    protected ?DirectoryElement $profileDirectory = null;

    protected ?DirectoryElement $temporaryDirectory = null;

    protected ?TaskFileElement $destination = null;

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

    public function setLicenseFile(?string $licenseFile): self
    {
        $this->licenseFile = $licenseFile;

        return $this;
    }

    public function getLicenseFile(): ?string
    {
        return $this->licenseFile;
    }

    /**
     * Returns the absolute path of the license file (relative paths are resolved against the profile directory),
     * or null if no license file is configured.
     */
    public function getLicenseFilePath(): ?string
    {
        if (!$this->licenseFile) {
            return null;
        }

        return Path::makeAbsolute(Path::canonicalize($this->licenseFile), $this->getProfileDirectory()->getPath());
    }

    public function isLicenseFileReadable(): bool
    {
        $path = $this->getLicenseFilePath();

        return $path !== null && is_file($path) && is_readable($path);
    }

    public function readLicenseFile(): ?string
    {
        if (!$this->isLicenseFileReadable()) {
            return null;
        }

        $content = file_get_contents($this->getLicenseFilePath());

        return ($content !== false) ? $content : null;
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

    public function setDestination(TaskFileElement $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDestination(): ?TaskFileElement
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
