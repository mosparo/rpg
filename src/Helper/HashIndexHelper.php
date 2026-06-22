<?php

namespace MosparoRpg\Helper;

use SplFileObject;
use Symfony\Component\Uid\UuidV4;

class HashIndexHelper
{
    protected ?SplFileObject $hashIndexFile;

    protected array $hashIndex = [];

    public function __construct(SplFileObject $hashIndexFile)
    {
        $this->hashIndexFile = $hashIndexFile;
    }

    public function findIndexHashForUuid(UuidV4 $uuid): ?string
    {
        if (!$this->hashIndex) {
            $this->buildIndex();
        }

        $hash = $this->hashIndex[$uuid->toBinary()] ?? null;

        if ($hash) {
            unset($this->hashIndex[$uuid->toBinary()]);
        }

        return $hash;
    }

    public function getRemainingHashes(): array
    {
        return $this->hashIndex;
    }

    protected function buildIndex(): void
    {
        $this->hashIndexFile->rewind();
        while (!$this->hashIndexFile->eof()) {
            $line = $this->hashIndexFile->fgets();

            if (!trim($line)) {
                continue;
            }

            $parts = explode('::', trim($line));

            $identifier = UuidV4::fromString($parts[0]);
            $this->hashIndex[$identifier->toBinary()] = $parts[1];
        }
    }
}