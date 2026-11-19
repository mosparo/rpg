<?php

namespace MosparoRpg\Tests\Unit\Processor;

use Mosparo\ApiClient\Client;
use Mosparo\ApiClient\Exception as ApiException;
use Mosparo\ApiClient\RulePackageBatchResult;
use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Factory\MosparoApiClientFactory;
use MosparoRpg\Helper\CliOutputHelper;
use MosparoRpg\Processor\Storage\StoreRulePackageInMosparoProcessor;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Tests\Support\ContextFactory;
use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Type\Output\MosparoApiOutputType;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The mosparo API client is replaced with a mock, so the synchronization logic can be tested without a server.
 */
class StoreRulePackageInMosparoProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const RULE_A = '2f8a9b1e-1c4f-4d6a-9f0e-0a1b2c3d4e5f';
    private const ITEM_1 = '6c1d6f1a-7c5f-4b8e-8a3b-1d2e3f4a5b6c';
    private const ITEM_2 = '7d2e7a2b-8d6a-4c9f-9b4c-2e3f4a5b6c7d';
    private const OLD_RULE = '9f4a9c4d-0f8c-4e1b-9d6e-4a5b6c7d8e9f';
    private const OLD_ITEM = 'a05b0d5e-1a9d-4f2c-8e7f-5b6c7d8e9f0a';

    private RulePackageContext $rpContext;

    /** @var array<int, array<int, array>> */
    private array $batches = [];

    private string $hashIndex = '';

    private bool $failBatch = false;

    private RulePackageBatchResult $batchResult;

    private BufferedOutput $log;

    protected function setUp(): void
    {
        $dir = $this->createTemporaryDirectory();
        $this->rpContext = ContextFactory::rulePackageContext($dir);
        $this->rpContext->setRefreshInterval(600);
        $this->batchResult = new RulePackageBatchResult(true, []);
        $this->log = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
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

        $this->rpContext->setDestination(new TaskFileElement($this->tmpPath('tasks')));
    }

    private function defaultTasks(): array
    {
        return [
            new RuleTask(self::RULE_A, 'email', 'Domains', null, 1.5),
            new RuleItemTask(self::RULE_A, self::ITEM_1, 'text', 'a.com', 2.0),
            new RuleItemTask(self::RULE_A, self::ITEM_2, 'text', 'b.com', 3.0),
        ];
    }

    /**
     * Same hash calculation as mosparo (RulePackageRuleCache::getHash()).
     */
    private function ruleLine(string $uuid, string $type, string $name, ?string $description, float $factor, int $id): string
    {
        return sprintf('%s::r/%s/%d', $uuid, md5($uuid . $type . $name . $description . $factor), $id) . "\n";
    }

    /**
     * Same hash calculation as mosparo (RulePackageRuleItemCache::getHash()).
     */
    private function itemLine(string $uuid, string $type, string $value, float $rating, int $id): string
    {
        return sprintf('%s::i/%s/%d', $uuid, md5($uuid . $type . $value . $rating), $id) . "\n";
    }

    private function runProcessor(): bool
    {
        $client = $this->createMock(Client::class);
        $client->method('streamRulePackageHashIndex')->willReturnCallback(function (int $id, SplFileObject $target) {
            $target->fwrite($this->hashIndex);

            return true;
        });
        $client->method('batchUpdateRulePackage')->willReturnCallback(function (int $id, array $tasks) {
            if ($this->failBatch) {
                throw new ApiException('Connection failed');
            }
            $this->batches[] = $tasks;

            return $this->batchResult;
        });

        $factory = $this->createMock(MosparoApiClientFactory::class);
        $factory->expects($this->once())
            ->method('getClient')
            ->with('https://mosparo.example', 'pub', 'priv', ['verify' => true])
            ->willReturn($client);

        $context = (new MosparoApiOutputType())
            ->prepareOutputContext($this->rpContext, ['options' => [
                'host' => 'https://mosparo.example',
                'public_key' => 'pub',
                'private_key' => 'priv',
                'rule_package_id' => 5,
            ]])
            ->setCliOutputHelper(new CliOutputHelper(new NullOutput()));

        return (new StoreRulePackageInMosparoProcessor($factory))->process($context, ContextFactory::mainProcessor([], $this->log));
    }

    /**
     * @return array<int, string>
     */
    private function taskTypes(array $batch): array
    {
        return array_column($batch, 'type');
    }

    public function testIsApplicable(): void
    {
        $processor = new StoreRulePackageInMosparoProcessor($this->createStub(MosparoApiClientFactory::class));
        $context = (new MosparoApiOutputType())->prepareOutputContext($this->rpContext, []);

        $this->assertSame('store_rule_package_in_mosparo', $processor->getKey());
        $this->assertTrue($processor->isApplicable($context));
        $this->assertFalse($processor->isApplicable($this->rpContext));
    }

    public function testNewPackageSendsEverything(): void
    {
        $this->writeTasks($this->defaultTasks());

        $this->assertTrue($this->runProcessor());

        $this->assertCount(1, $this->batches);
        $batch = $this->batches[0];
        $this->assertSame(['update_rule_package', 'store_rule', 'store_rule_item', 'store_rule_item'], $this->taskTypes($batch));

        $this->assertSame(600, $batch[0]['data']['refreshInterval']);
        $this->assertSame([
            'uuid' => self::RULE_A,
            'name' => 'Domains',
            'description' => null,
            'type' => 'email',
            'spamRatingFactor' => 1.5,
        ], $batch[1]['data']);
        $this->assertSame([
            'ruleUuid' => self::RULE_A,
            'uuid' => self::ITEM_1,
            'type' => 'text',
            'value' => 'a.com',
            'rating' => 2.0,
        ], $batch[2]['data']);
    }

    public function testUnchangedEntriesAreNotSentAgain(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->hashIndex = $this->ruleLine(self::RULE_A, 'email', 'Domains', null, 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10)
            . $this->itemLine(self::ITEM_2, 'text', 'b.com', 3.0, 11);

        $this->assertTrue($this->runProcessor());

        $this->assertCount(1, $this->batches);
        $this->assertSame(['update_rule_package'], $this->taskTypes($this->batches[0]));
    }

    public function testUnchangedRuleWithDescriptionIsNotSentAgain(): void
    {
        $this->writeTasks([
            new RuleTask(self::RULE_A, 'email', 'Domains', 'Known spam domains', 1.5),
            new RuleItemTask(self::RULE_A, self::ITEM_1, 'text', 'a.com', 2.0),
        ]);
        $this->hashIndex = $this->ruleLine(self::RULE_A, 'email', 'Domains', 'Known spam domains', 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10);

        $this->assertTrue($this->runProcessor());

        $this->assertSame(['update_rule_package'], $this->taskTypes($this->batches[0]));
    }

    public function testChangedDescriptionIsSentAgain(): void
    {
        $this->writeTasks([
            new RuleTask(self::RULE_A, 'email', 'Domains', 'New description', 1.5),
            new RuleItemTask(self::RULE_A, self::ITEM_1, 'text', 'a.com', 2.0),
        ]);
        $this->hashIndex = $this->ruleLine(self::RULE_A, 'email', 'Domains', 'Old description', 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10);

        $this->assertTrue($this->runProcessor());

        $batch = $this->batches[0];
        $this->assertSame(['update_rule_package', 'store_rule'], $this->taskTypes($batch));
        $this->assertSame('New description', $batch[1]['data']['description']);
    }

    public function testChangedItemIsSentAgain(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->hashIndex = $this->ruleLine(self::RULE_A, 'email', 'Domains', null, 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10)
            . $this->itemLine(self::ITEM_2, 'text', 'b.com', 1.0, 11); // rating changed

        $this->runProcessor();

        $batch = $this->batches[0];
        $this->assertSame(['update_rule_package', 'store_rule_item'], $this->taskTypes($batch));
        $this->assertSame(self::ITEM_2, $batch[1]['data']['uuid']);
    }

    public function testOutdatedEntriesAreRemovedItemsBeforeRules(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->hashIndex = $this->ruleLine(self::OLD_RULE, 'email', 'Old', null, 1.0, 7)
            . $this->ruleLine(self::RULE_A, 'email', 'Domains', null, 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10)
            . $this->itemLine(self::ITEM_2, 'text', 'b.com', 3.0, 11)
            . $this->itemLine(self::OLD_ITEM, 'text', 'old.com', 1.0, 12);

        $this->assertTrue($this->runProcessor());

        $tasks = array_merge(...$this->batches);
        $this->assertSame(['update_rule_package', 'remove_rule_item', 'remove_rule'], $this->taskTypes($tasks));
        $this->assertSame(['id' => 12], $tasks[1]['data']);
        $this->assertSame(['id' => 7], $tasks[2]['data']);
    }

    public function testEmptyPackageRemovesEverythingInMosparo(): void
    {
        $this->writeTasks([]);
        $this->hashIndex = $this->ruleLine(self::RULE_A, 'email', 'Domains', null, 1.5, 1)
            . $this->itemLine(self::ITEM_1, 'text', 'a.com', 2.0, 10);

        $this->assertTrue($this->runProcessor());

        $this->assertSame(['update_rule_package', 'remove_rule_item', 'remove_rule'], $this->taskTypes(array_merge(...$this->batches)));
    }

    public function testLargePackagesAreSentInBatches(): void
    {
        $tasks = [new RuleTask(self::RULE_A, 'email', 'Big', null, 1.0)];
        for ($i = 0; $i < 1500; $i++) {
            $tasks[] = new RuleItemTask(self::RULE_A, sprintf('00000000-0000-4000-8000-%012d', $i), 'text', 'v' . $i, 1.0);
        }
        $this->writeTasks($tasks);

        $this->assertTrue($this->runProcessor());

        $this->assertCount(2, $this->batches);
        $this->assertCount(1502, array_merge(...$this->batches), 'update_rule_package + 1 rule + 1500 items');
        $this->assertSame('update_rule_package', $this->batches[0][0]['type']);
    }

    public function testApiErrorReturnsFalse(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->failBatch = true;

        $this->assertFalse($this->runProcessor());
        $this->assertStringContainsString('Connection failed', $this->log->fetch());
    }

    public function testRejectedTasksAreLogged(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->batchResult = new RulePackageBatchResult(true, ['Rule not available: ' . self::RULE_A]);

        $this->assertTrue($this->runProcessor(), 'Single rejected tasks do not fail the whole synchronization.');

        $log = $this->log->fetch();
        $this->assertStringContainsString('[error]', $log);
        $this->assertStringContainsString('Rule not available: ' . self::RULE_A, $log);
    }

    public function testUnsuccessfulBatchReturnsFalse(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->batchResult = new RulePackageBatchResult(false, []);

        $this->assertFalse($this->runProcessor());
        $this->assertStringContainsString('not processed successfully', $this->log->fetch());
    }

    public function testHashIndexFileIsRegisteredAsTemporaryFile(): void
    {
        $this->writeTasks($this->defaultTasks());
        $this->failBatch = true;

        // Also on the error path, the file must be known to the cleanup of the command
        $this->runProcessor();

        $files = $this->rpContext->getTemporaryFiles();
        $this->assertCount(1, $files);
        $this->assertSame($this->tmpDir, dirname($files[0]), 'The file must be in the configured temporary directory.');
    }

    public function testMissingOptionsAreReported(): void
    {
        $this->writeTasks($this->defaultTasks());
        $factory = $this->createMock(MosparoApiClientFactory::class);
        $factory->expects($this->never())->method('getClient');

        $context = (new MosparoApiOutputType())
            ->prepareOutputContext($this->rpContext, ['identifier' => 'api', 'options' => ['host' => 'https://mosparo.example', 'rule_package_id' => 'abc']])
            ->setCliOutputHelper(new CliOutputHelper(new NullOutput()));

        $result = (new StoreRulePackageInMosparoProcessor($factory))->process($context, ContextFactory::mainProcessor([], $this->log));

        $this->assertFalse($result);
        $this->assertStringContainsString('Output "api": the options public_key, private_key, rule_package_id are missing or invalid.', $this->log->fetch());
    }

    public function testRulePackageIdAsStringIsAccepted(): void
    {
        $this->writeTasks($this->defaultTasks());
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('batchUpdateRulePackage')
            ->with(5, $this->anything())
            ->willReturn(new RulePackageBatchResult(true, []));
        $factory = $this->createStub(MosparoApiClientFactory::class);
        $factory->method('getClient')->willReturn($client);

        $context = (new MosparoApiOutputType())
            ->prepareOutputContext($this->rpContext, ['options' => [
                'host' => 'https://mosparo.example', 'public_key' => 'pub', 'private_key' => 'priv', 'rule_package_id' => '5',
            ]])
            ->setCliOutputHelper(new CliOutputHelper(new NullOutput()));

        $this->assertTrue((new StoreRulePackageInMosparoProcessor($factory))->process($context, ContextFactory::mainProcessor()));
    }

    public function testFactoryCreatesClient(): void
    {
        $this->assertInstanceOf(Client::class, (new MosparoApiClientFactory())->getClient('https://a', 'b', 'c', []));
    }
}
