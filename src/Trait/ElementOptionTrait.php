<?php

namespace MosparoRpg\Trait;

trait ElementOptionTrait
{
    protected array $options;

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function getOption(string $key, mixed $default): mixed
    {
        if (!$this->hasOption($key)) {
            return $default;
        }

        return $this->options[$key];
    }

    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }
}