<?php

namespace MosparoRpg\Tests\Unit\Type;

use MosparoRpg\Type\Input\ListInputType;
use MosparoRpg\Type\Input\TableInputType;
use MosparoRpg\Type\Manager;
use MosparoRpg\Type\Output\FileOutputType;
use MosparoRpg\Type\Output\MosparoApiOutputType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class ManagerTest extends TestCase
{
    private Manager $manager;

    protected function setUp(): void
    {
        $types = [new ListInputType(), new TableInputType(), new FileOutputType(), new MosparoApiOutputType()];

        $this->manager = new Manager(new RewindableGenerator(static function () use ($types) {
            yield from $types;
        }, count($types)));
    }

    public function testInputTypes(): void
    {
        $this->assertInstanceOf(ListInputType::class, $this->manager->getInputType('list'));
        $this->assertInstanceOf(TableInputType::class, $this->manager->getInputType('table'));
    }

    public function testOutputTypes(): void
    {
        $this->assertInstanceOf(FileOutputType::class, $this->manager->getOutputType('file'));
        $this->assertInstanceOf(MosparoApiOutputType::class, $this->manager->getOutputType('mosparo_api'));
    }

    public function testInputAndOutputTypesAreSeparated(): void
    {
        $this->assertNull($this->manager->getInputType('file'));
        $this->assertNull($this->manager->getOutputType('list'));
    }

    public function testUnknownOrEmptyKeys(): void
    {
        $this->assertNull($this->manager->getInputType('unknown'));
        $this->assertNull($this->manager->getInputType(null));
        $this->assertNull($this->manager->getInputType(''));
        $this->assertNull($this->manager->getOutputType(null));
    }
}
