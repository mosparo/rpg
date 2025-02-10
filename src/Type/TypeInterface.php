<?php

namespace MosparoRpg\Type;

interface TypeInterface
{
    public function getKey(): string;

    public function getProcessors(): array;
}