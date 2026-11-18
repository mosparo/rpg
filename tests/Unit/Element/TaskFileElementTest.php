<?php

namespace MosparoRpg\Tests\Unit\Element;

use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use PHPUnit\Framework\TestCase;

class TaskFileElementTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $path;

    protected function setUp(): void
    {
        $this->path = $this->createTemporaryDirectory() . '/package.rptasks';
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testTemporaryFilePath(): void
    {
        $element = new TaskFileElement($this->path);

        $this->assertSame($this->path, $element->getFilePath());
        $this->assertSame($this->path . '.tmp', $element->getFilePath(true));
    }

    public function testInvalidWhenFileDoesNotExist(): void
    {
        $this->assertFalse((new TaskFileElement($this->path))->valid());
    }

    public function testResetWritesMetadataLineToTemporaryFile(): void
    {
        $element = new TaskFileElement($this->path);
        $element->resetTaskFile();

        $this->assertFileExists($this->path . '.tmp');
        $this->assertMatchesRegularExpression('/^rptf::\d{4}-\d\d-\d\dT[^:]+:\d\d:\d\d[+-]\d\d:\d\d::0\n$/', file_get_contents($this->path . '.tmp'));
        $this->assertSame(0, $element->getNumberOfTasks());
    }

    public function testWriteFinalizeAndIterate(): void
    {
        $tasks = $this->writeTasks();

        $this->assertFileDoesNotExist($this->path . '.tmp', 'The temporary file must be removed after finalizing.');
        $this->assertFileExists($this->path);

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        $this->assertCount(4, $lines);
        $this->assertStringStartsWith('rptf::', $lines[0]);
        $this->assertStringEndsWith('::3', $lines[0]);

        $element = new TaskFileElement($this->path);
        $this->assertTrue($element->valid());
        $this->assertSame(3, $element->getNumberOfTasks());

        $read = iterator_to_array($element, false);
        $this->assertEquals($tasks, $read);
    }

    public function testIteratingTwiceYieldsTheSameTasks(): void
    {
        $this->writeTasks();
        $element = new TaskFileElement($this->path);

        $first = iterator_to_array($element, false);
        $second = iterator_to_array($element, false);

        $this->assertCount(3, $first);
        $this->assertEquals($first, $second);
    }

    public function testEmptyTaskFileHasNoTasks(): void
    {
        $element = new TaskFileElement($this->path);
        $element->resetTaskFile();
        $element->finalizeTasksFile();

        $reader = new TaskFileElement($this->path);
        $this->assertSame(0, $reader->getNumberOfTasks());
        $this->assertSame([], iterator_to_array($reader, false));
        $this->assertFalse($reader->valid());
    }

    public function testResetDiscardsPreviousTasks(): void
    {
        $this->writeTasks();

        $element = new TaskFileElement($this->path);
        $element->resetTaskFile();
        $element->writeTaskToFile(new RuleTask('11111111-1111-4111-8111-111111111111', 'word', 'Only', null, 1.0));
        $element->finalizeTasksFile();

        $reader = new TaskFileElement($this->path);
        $this->assertSame(1, $reader->getNumberOfTasks());
        $this->assertCount(1, iterator_to_array($reader, false));
    }

    public function testDamagedLineThrows(): void
    {
        $this->writeTasks();
        $lines = file($this->path);
        $lines[2] = substr($lines[2], 0, 20) . "\n";
        file_put_contents($this->path, implode('', $lines));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('invalid task in line 3');

        iterator_to_array(new TaskFileElement($this->path), false);
    }

    public function testMissingTasksAtTheEndThrow(): void
    {
        $this->writeTasks();
        $lines = file($this->path);
        array_pop($lines);
        file_put_contents($this->path, implode('', $lines));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('2 of 3 tasks read');

        iterator_to_array(new TaskFileElement($this->path), false);
    }

    public function testFileWithoutBase64EncodedTasksThrows(): void
    {
        $task = new RuleTask('2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f', 'email', 'Old', null, 1.0);
        file_put_contents($this->path, "rptf::2026-11-18T10:00:00+01:00::1\n" . serialize($task) . "\n");

        $this->expectException(\Exception::class);

        iterator_to_array(new TaskFileElement($this->path), false);
    }

    /**
     * A value with a line break (e.g. from a quoted CSV field) must survive the line based task file.
     */
    public function testValueWithLineBreakSurvivesRoundTrip(): void
    {
        $ruleUuid = '2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f';
        $tasks = [
            new RuleItemTask($ruleUuid, '6c1d6f1a-7c5f-4b8e-8a3b-1d2e3f4a5b6c', 'text', "first\nsecond", 1.0),
            new RuleItemTask($ruleUuid, '7d2e7a2b-8d6a-4c9f-9b4c-2e3f4a5b6c7d', 'text', 'after', 1.0),
        ];

        $element = new TaskFileElement($this->path);
        $element->resetTaskFile();
        foreach ($tasks as $task) {
            $element->writeTaskToFile($task);
        }
        $element->finalizeTasksFile();

        $this->assertEquals($tasks, iterator_to_array(new TaskFileElement($this->path), false));
    }

    /**
     * @return array<int, RuleTask|RuleItemTask>
     */
    private function writeTasks(): array
    {
        $ruleUuid = '2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f';
        $tasks = [
            new RuleTask($ruleUuid, 'email', 'Domains', null, 1.5),
            new RuleItemTask($ruleUuid, '6c1d6f1a-7c5f-4b8e-8a3b-1d2e3f4a5b6c', 'text', 'spam.example', 2.0),
            new RuleItemTask($ruleUuid, '7d2e7a2b-8d6a-4c9f-9b4c-2e3f4a5b6c7d', 'text', 'example.com', null),
        ];

        $element = new TaskFileElement($this->path);
        $element->resetTaskFile();
        foreach ($tasks as $task) {
            $element->writeTaskToFile($task);
        }
        $this->assertSame(3, $element->getNumberOfTasks());
        $element->finalizeTasksFile();

        return $tasks;
    }
}
