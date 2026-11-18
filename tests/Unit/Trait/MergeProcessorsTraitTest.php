<?php

namespace MosparoRpg\Tests\Unit\Trait;

use MosparoRpg\Trait\MergeProcessorsTrait;
use PHPUnit\Framework\TestCase;

class MergeProcessorsTraitTest extends TestCase
{
    private object $merger;

    protected function setUp(): void
    {
        $this->merger = new class {
            use MergeProcessorsTrait {
                mergeProcessors as public;
            }
        };
    }

    public function testMergesSamePriorityAndKeepsOrder(): void
    {
        $result = $this->merger->mergeProcessors(
            [100 => ['A', 'B'], 200 => ['C']],
            [100 => ['D'], 300 => ['E']]
        );

        $this->assertSame([100 => ['A', 'B', 'D'], 200 => ['C'], 300 => ['E']], $result);
    }

    public function testEmptyInputs(): void
    {
        $this->assertSame([], $this->merger->mergeProcessors([], []));
        $this->assertSame([1 => ['A']], $this->merger->mergeProcessors([], [1 => ['A']]));
        $this->assertSame([1 => ['A']], $this->merger->mergeProcessors([1 => ['A']], []));
    }
}
