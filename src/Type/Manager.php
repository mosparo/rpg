<?php

namespace MosparoRpg\Type;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class Manager
{
    protected array $inputTypes = [];

    protected array $outputTypes = [];

    public function __construct(RewindableGenerator $generator)
    {
        foreach ($generator as $type) {
            if ($type instanceof InputTypeInterface) {
                $this->inputTypes[$type->getKey()] = $type;
            } else if ($type instanceof OutputTypeInterface) {
                $this->outputTypes[$type->getKey()] = $type;
            }
        }
    }

    public function getInputType($key): ?InputTypeInterface
    {
        if (!$key) {
            return null;
        }

        return $this->inputTypes[$key] ?? null;
    }

    public function getOutputType($key): ?OutputTypeInterface
    {
        if (!$key) {
            return null;
        }

        return $this->outputTypes[$key] ?? null;
    }
}