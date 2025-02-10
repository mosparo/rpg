<?php

namespace MosparoRpg\Trait;

trait MergeProcessorsTrait
{
    protected function mergeProcessors(array $processorsA, array $processorsB): array
    {
        foreach ($processorsB as $priority => $processors) {
            if (isset($processorsA[$priority])) {
                $processorsA[$priority] = array_merge($processorsA[$priority], $processors);
            } else {
                $processorsA[$priority] = $processors;
            }
        }

        return $processorsA;
    }
}