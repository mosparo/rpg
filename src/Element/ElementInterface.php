<?php

namespace MosparoRpg\Element;

interface ElementInterface
{
    public function getKey(): string;

    public function setOptions(array $options): self;

    public function getOptions(): array;

    public function setOption(string $key, mixed $value): self;

    public function getOption(string $key, mixed $default): mixed;

    public function hasOption(string $key): bool;
}