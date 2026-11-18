<?php

namespace MosparoRpg\Tests\Unit\Processor;

use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Helper\CliOutputHelper;
use MosparoRpg\Processor\Storage\StoreRulePackageInFileProcessor;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Type\Output\FileOutputType;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use ZipArchive;

class StoreRulePackageInFileProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const RULE_A = '2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f';
    private const RULE_B = '3a9b0c2f-2d5a-4e7b-8a1f-1b2c3d4e5f6a';
    private const ITEM_1 = '6c1d6f1a-7c5f-4b8e-8a3b-1d2e3f4a5b6c';
    private const ITEM_2 = '7d2e7a2b-8d6a-4c9f-9b4c-2e3f4a5b6c7d';
    private const ITEM_3 = '8e3f8b3c-9e7b-4d0a-8c5d-3f4a5b6c7d8e';

    private RulePackageContext $rpContext;

    protected function setUp(): void
    {
        $dir = $this->createTemporaryDirectory();
        $this->rpContext = ContextFactory::rulePackageContext($dir, new TaskFileElement($dir . '/tasks'));
        $this->rpContext->setRefreshInterval(7200);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @param array<int, RuleTask|RuleItemTask> $tasks
     */
    private function writeTasks(array $tasks): void
    {
        $writer = new TaskFileElement($this->tmpPath('tasks'));
        $writer->resetTaskFile();
        foreach ($tasks as $task) {
            $writer->writeTaskToFile($task);
        }
        $writer->finalizeTasksFile();

        // Fresh reader, like the command does after finalizing
        $this->rpContext->setDestination(new TaskFileElement($this->tmpPath('tasks')));
    }

    private function defaultTasks(): array
    {
        return [
            new RuleTask(self::RULE_A, 'email', 'Rule "A"', null, 1.5),
            new RuleItemTask(self::RULE_A, self::ITEM_1, 'text', 'a.com', 2.0),
            new RuleItemTask(self::RULE_A, self::ITEM_2, 'text', 'b.com', 3.0),
            new RuleTask(self::RULE_B, 'word', 'Rule B', null, 1.0),
            new RuleItemTask(self::RULE_B, self::ITEM_3, 'regex', '/spam/i', 5.0),
        ];
    }

    private function outputContext(array $options): OutputContext
    {
        return (new FileOutputType())
            ->prepareOutputContext($this->rpContext, ['identifier' => 'test', 'options' => $options])
            ->setCliOutputHelper(new CliOutputHelper(new NullOutput()));
    }

    public function testIsApplicable(): void
    {
        $processor = new StoreRulePackageInFileProcessor();

        $this->assertSame('store_rule_package_in_file', $processor->getKey());
        $this->assertTrue($processor->isApplicable($this->outputContext([])));
        $this->assertFalse($processor->isApplicable($this->rpContext));
    }

    public function testMissingFilePathIsReported(): void
    {
        $this->writeTasks($this->defaultTasks());
        $log = new \Symfony\Component\Console\Output\BufferedOutput(\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_DEBUG);

        $result = (new StoreRulePackageInFileProcessor())->process($this->outputContext([]), ContextFactory::mainProcessor([], $log));

        $this->assertFalse($result);
        $this->assertStringContainsString('Output "test": the option file_path is missing or invalid.', $log->fetch());
    }

    public function testStoresJsonWithChecksum(): void
    {
        $this->writeTasks($this->defaultTasks());

        $result = (new StoreRulePackageInFileProcessor())->process(
            $this->outputContext(['file_path' => 'output/sub/package.json']),
            ContextFactory::mainProcessor()
        );
        $this->assertTrue($result);

        $path = $this->tmpPath('output/sub/package.json');
        $this->assertFileExists($path);
        $this->assertSame(hash_file('sha256', $path), file_get_contents($path . '.sha256'));

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(7200, $data['refreshInterval']);
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTimeInterface::ATOM, $data['lastUpdatedAt']));
        $this->assertCount(2, $data['rules']);

        $this->assertSame(self::RULE_A, $data['rules'][0]['uuid']);
        $this->assertSame('Rule "A"', $data['rules'][0]['name']);
        $this->assertSame('email', $data['rules'][0]['type']);
        $this->assertEqualsWithDelta(1.5, $data['rules'][0]['spamRatingFactor'], 0.0001);
        // assertEquals: json_encode(2.0) results in "2.0", but json_decode() may return an int
        $this->assertEquals([
            ['uuid' => self::ITEM_1, 'type' => 'text', 'value' => 'a.com', 'rating' => 2.0],
            ['uuid' => self::ITEM_2, 'type' => 'text', 'value' => 'b.com', 'rating' => 3.0],
        ], $data['rules'][0]['items']);

        $this->assertSame(self::RULE_B, $data['rules'][1]['uuid']);
        $this->assertEquals([
            ['uuid' => self::ITEM_3, 'type' => 'regex', 'value' => '/spam/i', 'rating' => 5.0],
        ], $data['rules'][1]['items']);
    }

    public function testJsonIsTheDefaultFormat(): void
    {
        $this->writeTasks($this->defaultTasks());

        (new StoreRulePackageInFileProcessor())->process($this->outputContext(['file_path' => 'package.out']), ContextFactory::mainProcessor());

        json_decode(file_get_contents($this->tmpPath('package.out')), flags: JSON_THROW_ON_ERROR);
        $this->addToAssertionCount(1);
    }

    public function testStoresValidJsonForEmptyPackage(): void
    {
        $this->writeTasks([]);

        (new StoreRulePackageInFileProcessor())->process($this->outputContext(['file_path' => 'empty.json']), ContextFactory::mainProcessor());

        $data = json_decode(file_get_contents($this->tmpPath('empty.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['rules']);
    }

    public function testStoresValidJsonForRuleWithoutItems(): void
    {
        $this->writeTasks([
            new RuleTask(self::RULE_A, 'email', 'Empty rule', null, 1.0),
            new RuleTask(self::RULE_B, 'email', 'Another rule', null, 1.0),
            new RuleItemTask(self::RULE_B, self::ITEM_1, 'text', 'x', 1.0),
        ]);

        (new StoreRulePackageInFileProcessor())->process($this->outputContext(['file_path' => 'p.json']), ContextFactory::mainProcessor());

        $data = json_decode(file_get_contents($this->tmpPath('p.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['rules'][0]['items']);
        $this->assertCount(1, $data['rules'][1]['items']);
    }

    #[RequiresPhpExtension('zip')]
    public function testStoresZip(): void
    {
        $this->writeTasks($this->defaultTasks());

        $result = (new StoreRulePackageInFileProcessor())->process(
            $this->outputContext(['file_path' => 'package.zip', 'format' => 'zip']),
            ContextFactory::mainProcessor()
        );
        $this->assertTrue($result);

        $path = $this->tmpPath('package.zip');
        $this->assertSame(hash_file('sha256', $path), file_get_contents($path . '.sha256'));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));

        $meta = json_decode($zip->getFromName('rule-package.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(7200, $meta['refreshInterval']);
        $this->assertSame(['rules-0.json'], $meta['rFiles']);
        $this->assertSame(['rule-items-0.json'], $meta['riFiles']);

        $rules = json_decode($zip->getFromName('rules-0.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([self::RULE_A, self::RULE_B], array_column($rules, 'uuid'));
        $this->assertSame('', $rules[0]['description']);

        $items = json_decode($zip->getFromName('rule-items-0.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([self::ITEM_1, self::ITEM_2, self::ITEM_3], array_column($items, 'uuid'));
        $this->assertSame([self::RULE_A, self::RULE_A, self::RULE_B], array_column($items, 'ruleUuid'));

        $zip->close();
    }

    #[RequiresPhpExtension('zip')]
    public function testZipSplitsItemsIntoChunksOf1000(): void
    {
        $tasks = [new RuleTask(self::RULE_A, 'email', 'Big rule', null, 1.0)];
        for ($i = 0; $i < 2500; $i++) {
            $tasks[] = new RuleItemTask(self::RULE_A, sprintf('00000000-0000-4000-8000-%012d', $i), 'text', 'v' . $i, 1.0);
        }
        $this->writeTasks($tasks);

        (new StoreRulePackageInFileProcessor())->process(
            $this->outputContext(['file_path' => 'big.zip', 'format' => 'zip']),
            ContextFactory::mainProcessor()
        );

        $zip = new ZipArchive();
        $zip->open($this->tmpPath('big.zip'));
        $meta = json_decode($zip->getFromName('rule-package.json'), true);

        $this->assertSame(['rule-items-0.json', 'rule-items-1.json', 'rule-items-2.json'], $meta['riFiles']);
        $this->assertCount(1000, json_decode($zip->getFromName('rule-items-0.json'), true));
        $this->assertCount(1000, json_decode($zip->getFromName('rule-items-1.json'), true));
        $this->assertCount(500, json_decode($zip->getFromName('rule-items-2.json'), true));
        $zip->close();
    }
}
