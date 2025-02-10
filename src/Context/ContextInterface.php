<?php

namespace MosparoRpg\Context;

interface ContextInterface
{
    public function buildIdentifier(): string;

    public function __toString(): string;
}