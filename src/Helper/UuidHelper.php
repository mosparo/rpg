<?php

namespace MosparoRpg\Helper;

use Exception;
use Symfony\Component\Uid\Uuid;

class UuidHelper
{
    protected ?string $uuidFilePath = null;

    protected ?\SplFileObject $uuidFile = null;

    public function setUuidFilePath($uuidFilePath): self
    {
        $this->uuidFilePath = $uuidFilePath;

        return $this;
    }

    public function findUuidForIdentifier(string $identifier): ?Uuid
    {
        $identifier = $this->cleanIdentifier($identifier);
        $this->openUuidFile();

        $this->uuidFile->rewind();
        while (!$this->uuidFile->eof()) {
            $line = $this->uuidFile->fgets();

            if (str_starts_with($line, $identifier . '::')) {
                $uuid = trim(substr($line, strpos($line, '::') + 2));

                return Uuid::fromString($uuid);
            }
        }

        return null;
    }

    public function storeUuid(Uuid $uuid, string $identifier): void
    {
        $this->openUuidFile();

        $this->uuidFile->fseek(0, SEEK_END);

        $this->uuidFile->fwrite(sprintf('%s::%s', $this->cleanIdentifier($identifier), $uuid) . PHP_EOL);
    }

    protected function openUuidFile(): void
    {
        if (!$this->uuidFilePath) {
            throw new Exception('No filepath set.');
        }

        if (!$this->uuidFile) {
            $this->uuidFile = new \SplFileObject($this->uuidFilePath, 'c+');
        }
    }

    protected function cleanIdentifier(string $identifier): string
    {
        return str_replace('::', '__', $identifier);;
    }
}