<?php

namespace MosparoRpg\Type;

interface OutputTypeInterface
{
    const PRIORITY_ORGANIZE = 100;
    const PRIORITY_MAIN = 500;
    const PRIORITY_FINALIZE = 900;


    public function getKey(): string;
}