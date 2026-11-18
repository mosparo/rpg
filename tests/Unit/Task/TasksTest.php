<?php

namespace MosparoRpg\Tests\Unit\Task;

use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use PHPUnit\Framework\TestCase;

class TasksTest extends TestCase
{
    public function testRuleTask(): void
    {
        $task = new RuleTask('uuid', 'email', 'Name', 'Desc', 1.5);

        $this->assertSame('uuid', $task->getUuid());
        $this->assertSame('email', $task->getType());
        $this->assertSame('Name', $task->getName());
        $this->assertSame('Desc', $task->getDescription());
        $this->assertSame(1.5, $task->getSpamRatingFactor());
    }

    public function testRuleItemTask(): void
    {
        $task = new RuleItemTask('rule-uuid', 'uuid', 'text', 'value', null);

        $this->assertSame('rule-uuid', $task->getRuleUuid());
        $this->assertSame('uuid', $task->getUuid());
        $this->assertSame('text', $task->getType());
        $this->assertSame('value', $task->getValue());
        $this->assertNull($task->getRating());
    }

    public function testTasksSurviveSerialization(): void
    {
        $rule = new RuleTask('uuid', 'email', 'Name', null, 2.0);
        $item = new RuleItemTask('rule-uuid', 'uuid', 'text', 'value', 3.0);

        $this->assertEquals($rule, unserialize(serialize($rule)));
        $this->assertEquals($item, unserialize(serialize($item)));
    }
}
