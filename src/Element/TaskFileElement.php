<?php

namespace MosparoRpg\Element;

use DateTime;
use DateTimeInterface;
use Exception;
use Iterator;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Trait\ElementOptionTrait;
use MosparoRpg\Trait\KeyTrait;
use SplFileObject;

/**
 * A task file contains the tasks to build a rule package in the correct order as serialized objects.
 * One task is stored per line, and it's possible to iterate over the file line by line (for memory saving processing).
 */
class TaskFileElement extends FileElement implements Iterator
{
    const KEY = 'task_file_element';

    use KeyTrait;
    use ElementOptionTrait;

    protected ?SplFileObject $handle = null;

    protected ?DateTime $createdAt = null;

    protected int $numberOfTasks = 0;

    protected null|RuleTask|RuleItemTask $task = null;

    protected int $readTasks = 0;

    public function getFilePath(bool $temporaryFile = false): string
    {
        $suffix = '';
        if ($temporaryFile) {
            $suffix = '.tmp';
        }
        return $this->filePath . $suffix;
    }

    public function resetTaskFile(): void
    {
        $this->openFile(true);

        $this->createdAt = new DateTime();
        $this->numberOfTasks = 0;

        $this->handle->ftruncate(0);
        $this->handle->fseek(0);
        $this->handle->fwrite($this->getMetadataLine());
    }

    public function finalizeTasksFile(): void
    {
        // Open the real one
        $this->handle = null;
        $this->handle = new SplFileObject($this->getFilePath(), 'w');

        // Open the temporary one
        $tempFilePath = $this->getFilePath(true);
        $tempHandle = new SplFileObject($tempFilePath, 'r');

        // Put the metadata line
        $this->handle->fwrite($this->getMetadataLine());

        // Copy all the other lines except the metadata line
        $first = true;
        foreach ($tempHandle as $data) {
            if ($first) {
                $first = false;
                continue;
            }

            $this->handle->fwrite($data);
        }

        $tempHandle = null;
        $this->handle = null;

        unlink($tempFilePath);

        $this->openFile();
    }

    public function writeTaskToFile(RuleTask|RuleItemTask $task): void
    {
        $this->openFile();

        $this->handle->fwrite(base64_encode(serialize($task)) . "\n");
        $this->numberOfTasks++;
    }

    public function getNumberOfTasks(): int
    {
        $this->openFile();

        return $this->numberOfTasks;
    }

    public function getMetadataLine(): string
    {
        return sprintf('rptf::%s::%s', $this->createdAt->format(DateTimeInterface::ATOM), $this->numberOfTasks) . "\n";
    }

    public function current(): mixed
    {
        $this->openFile();

        return $this->task;
    }

    public function key(): mixed
    {
        $this->openFile();

        return $this->handle->key();
    }

    public function next(): void
    {
        $this->openFile();

        $this->handle->next();
        $this->readNextLine();
    }

    public function rewind(): void
    {
        $this->openFile();

        $this->handle->rewind();
        $this->handle->seek(1);
        $this->readTasks = 0;
        $this->readNextLine();
    }

    public function valid(): bool
    {
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            return false;
        }

        $this->openFile();

        return ($this->task !== null);
    }

    protected function readNextLine(): void
    {
        $this->openFile();

        $line = $this->handle->current();
        if ($line === false || trim($line) === '') {
            $this->task = null;

            // End of file reached: all tasks from the metadata line must have been read
            if ($this->readTasks !== $this->numberOfTasks) {
                throw new Exception(sprintf(
                    'Task file "%s" is incomplete: %d of %d tasks read.',
                    $this->handle->getPathname(),
                    $this->readTasks,
                    $this->numberOfTasks
                ));
            }

            return;
        }

        $data = base64_decode(trim($line), true);
        $task = ($data !== false) ? @unserialize($data, ['allowed_classes' => [RuleTask::class, RuleItemTask::class]]) : false;
        if (!($task instanceof RuleTask) && !($task instanceof RuleItemTask)) {
            throw new Exception(sprintf(
                'Task file "%s" contains an invalid task in line %d.',
                $this->handle->getPathname(),
                $this->handle->key() + 1
            ));
        }

        $this->task = $task;
        $this->readTasks++;
    }

    protected function readMetadata(): void
    {
        $line = $this->handle->current();

        if (str_starts_with($line, 'rptf::')) {
            $data = explode('::', $line);

            $this->createdAt = DateTime::createFromFormat(DateTimeInterface::ATOM, $data[1]);
            $this->numberOfTasks = intval($data[2]);
        }
    }

    protected function openFile(bool $openTemporaryFile = false): void
    {
        if (!$this->handle) {
            $mode = 'r';
            if ($openTemporaryFile) {
                $mode = 'w+';
            }

            $this->handle = new SplFileObject($this->getFilePath($openTemporaryFile), $mode);
            $this->readMetadata();
            $this->rewind();
        }
    }
}