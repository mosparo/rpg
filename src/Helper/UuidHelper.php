<?php

namespace MosparoRpg\Helper;

use Exception;
use SplFileObject;
use Symfony\Component\Uid\Uuid;

class UuidHelper
{
    protected ?string $uuidFilePath = null;

    protected ?SplFileObject $uuidFile = null;

    protected array $uuids = [];

    protected array $newUuids = [];

    public function setUuidFilePath($uuidFilePath): self
    {
        $this->uuidFilePath = $uuidFilePath;

        return $this;
    }

    public function findUuidForIdentifier(string $identifier, ?string $type = null): ?Uuid
    {
        $this->openUuidFile();
        $identifier = $this->prepareIdentifier($identifier, $type);

        return $this->uuids[$identifier] ?? null;
    }

    public function storeUuid(string $identifier, string $type, Uuid $uuid): void
    {
        $identifier = $this->prepareIdentifier($identifier, $type);
        $this->newUuids[$identifier] = $uuid;
    }

    public function storeNewUuidIndex(): void
    {
        if (!$this->newUuids) {
            return;
        }

        $this->openUuidFile();

        $this->uuidFile->ftruncate(0);
        $this->uuidFile->fseek(0);

        foreach ($this->newUuids as $identifier => $uuid) {
            $this->uuidFile->fwrite(sprintf('%s::%s', $identifier, $uuid) . PHP_EOL);
        }
    }

    public function determineType(string $identifier): ?string
    {
        if (!str_starts_with($identifier, 'r/') && !str_starts_With($identifier, 'i/')) {
            return null;
        }

        return substr($identifier, 0, 1);
    }

    public function getUuidChanges(): array
    {
        $deleted = array_diff(array_keys($this->uuids), array_keys($this->newUuids));
        $added = array_diff(array_keys($this->newUuids), array_keys($this->uuids));

        return [$deleted, $added];
    }

    protected function openUuidFile(): void
    {
        if (!$this->uuidFilePath) {
            throw new Exception('No filepath set.');
        }

        if (!$this->uuidFile) {
            $this->uuidFile = new SplFileObject($this->uuidFilePath, 'c+');
            while (!$this->uuidFile->eof()) {
                $line = $this->uuidFile->fgets();
                $parts = explode('::', trim($line));

                $identifier = $parts[0] ?? null;
                $uuid = $parts[1] ?? null;

                if (!$identifier || !$uuid) {
                    continue;
                }

                if (strlen($uuid) < 36) {
                    continue;
                }

                $uuid = Uuid::fromString($uuid);
                $this->uuids[$identifier] = $uuid;
            }
        }
    }

    protected function prepareIdentifier(string $identifier, ?string $type = null): string
    {
        if (str_starts_with($identifier, 'r/') || str_starts_with($identifier, 'i/')) {
            return $identifier;
        }

        if (!$type) {
            $type = $this->determineType($identifier);
        }

        return sprintf('%s/%s', $type, md5($identifier));
    }
}