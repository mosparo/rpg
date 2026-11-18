<?php

namespace MosparoRpg\Processor\Storage;

use DateTime;
use DateTimeInterface;
use Mosparo\ApiClient\Exception;
use MosparoRpg\Cli\ProgressBarFormat;
use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\OutputContext;
use MosparoRpg\Factory\MosparoApiClientFactory;
use MosparoRpg\Helper\HashIndexHelper;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Trait\KeyTrait;
use SplFileObject;
use Symfony\Component\Uid\UuidV4;

class StoreRulePackageInMosparoProcessor implements ProcessorInterface
{
    const KEY = 'store_rule_package_in_mosparo';

    use KeyTrait;

    protected MosparoApiClientFactory $clientFactory;

    public function __construct(MosparoApiClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof OutputContext;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        if (!($context instanceof OutputContext)) {
            return true;
        }

        $rpContext = $context->getRulePackageContext();

        $mosparoHost = $context->getOption('host', null);
        $mosparoPublicKey = $context->getOption('public_key', null);
        $mosparoPrivateKey = $context->getOption('private_key', null);
        $options = [
            'verify' => $context->getOption('verify_ssl', true),
        ];
        $mosparoApiClient = $this->clientFactory->getClient($mosparoHost, $mosparoPublicKey, $mosparoPrivateKey, $options);

        $rulePackageId = $context->getOption('rule_package_id', null);
        $maxTasks = 1000;
        $apiTasksCounter = 0;

        /**
         * Download the hash index from the mosparo instance.
         */
        $progressBar = $context->getCliOutputHelper()->startProgress(
            ProgressBarFormat::BYTES_UNKNOWN,
            'Download hash index from mosparo...',
            0
        );
        $hashIndexFilePath = tempnam(sys_get_temp_dir(), 'rphi');
        $hashIndexSplFile = new SplFileObject($hashIndexFilePath, 'c+');
        $bytes = 0;
        $previousBytes = 0;
        $mosparoApiClient->streamRulePackageHashIndex($rulePackageId, $hashIndexSplFile, function ($totalBytes) use ($progressBar, &$bytes, &$previousBytes) {
            if ($previousBytes > $totalBytes) {
                $bytes += $previousBytes;
                $progressBar->advance($bytes);
            } else {
                $progressBar->advance($bytes + $totalBytes);
            }
            $previousBytes = $totalBytes;
        });

        $progressBar->finish();

        /**
         * Read the hash index file
         */
        $progressBar = $context->getCliOutputHelper()->startProgress(
            ProgressBarFormat::BYTES_FIXED,
            'Read hash index...',
            filesize($hashIndexFilePath)
        );

        $hashIndexHelper = new HashIndexHelper($hashIndexSplFile);

        $progressBar->advance(100);
        $progressBar->finish();

        // Add the rule package as the first task
        $apiTasks = [
            [
                'type' => 'update_rule_package',
                'data' => [
                    'lastUpdatedAt' => (new DateTime())->format(DateTimeInterface::ATOM),
                    'refreshInterval' => $rpContext->getRefreshInterval() ?? 86400,
                ],
            ],
        ];

        /**
         * Process the tasks
         */
        $progressBar = $context->getCliOutputHelper()->startProgress(
            ProgressBarFormat::STEPS,
            'Synchronize rule package with mosparo...',
            $rpContext->getDestination()->getNumberOfTasks()
        );

        $rpContext->getDestination()->rewind();
        $counter = 0;
        foreach ($rpContext->getDestination() as $task) {
            $counter++;
            $progressBar->advance($counter);

            $indexHash = $hashIndexHelper->findIndexHashForUuid(UuidV4::fromString($task->getUuid()));
            if ($indexHash) {
                $hashData = explode('/', $indexHash);
                $indexHash = $hashData[1];
            }

            if ($task instanceof RuleTask) {
                if ($indexHash) {
                    $hash = md5($task->getUuid() . $task->getType() . $task->getName() . $task->getSpamRatingFactor());

                    if ($indexHash === $hash) {
                        continue;
                    }
                }

                $apiTasks[] = [
                    'type' => 'store_rule',
                    'data' => [
                        'uuid' => $task->getUuid(),
                        'name' => $task->getName(),
                        'description' => '',
                        'type' => $task->getType(),
                        'spamRatingFactor' => $task->getSpamRatingFactor(),
                    ],
                ];
            } else if ($task instanceof RuleItemTask) {
                if ($indexHash) {
                    $hash = md5($task->getUuid() . $task->getType() . $task->getValue() . $task->getRating());

                    if ($indexHash === $hash) {
                        continue;
                    }
                }

                $apiTasks[] = [
                    'type' => 'store_rule_item',
                    'data' => [
                        'ruleUuid' => $task->getRuleUuid(),
                        'uuid' => $task->getUuid(),
                        'type' => $task->getType(),
                        'value' => $task->getValue(),
                        'rating' => $task->getRating(),
                    ],
                ];
            }

            $apiTasksCounter++;
            if ($apiTasksCounter >= $maxTasks || $counter >= $rpContext->getDestination()->getNumberOfTasks()) {
                try {
                    $mosparoApiClient->batchUpdateRulePackage($rulePackageId, $apiTasks);

                    $apiTasks = [];
                    $apiTasksCounter = 0;
                } catch (Exception) {
                    return false;
                }
            }
        }

        $progressBar->finish();

        /**
         * Remove the outdated rules and rule items. These items were not deleted by a previous run of RPG and are now
         * outdated.
         */
        $index = $hashIndexHelper->getRemainingHashes();
        if ($index) {
            $progressBar = $context->getCliOutputHelper()->startProgress(
                ProgressBarFormat::STEPS,
                'Delete the outdated items..',
                count($index)
            );

            $delayedApiTasks = [];
            $apiTasksCounter = 0;
            $counter = 0;
            foreach ($index as $hash) {
                $counter++;
                $progressBar->advance($counter);

                $hashData = explode('/', $hash);
                $type = $hashData[0];

                if ($type === 'r') {
                    // We need to delay these tasks so that we first can delete all the rule items
                    $delayedApiTasks[] = [
                        'type' => 'remove_rule',
                        'data' => [
                            'id' => intval($hashData[2]),
                        ],
                    ];
                } else if ($type === 'i') {
                    $apiTasks[] = [
                        'type' => 'remove_rule_item',
                        'data' => [
                            'id' => intval($hashData[2]),
                        ],
                    ];
                    $apiTasksCounter++;
                }

                if ($apiTasksCounter >= $maxTasks) {
                    try {
                        $mosparoApiClient->batchUpdateRulePackage($rulePackageId, $apiTasks);

                        $apiTasks = [];
                        $apiTasksCounter = 0;
                    } catch (Exception) {
                        return false;
                    }
                }
            }

            if ($delayedApiTasks) {
                $apiTasks = array_merge($apiTasks, $delayedApiTasks);
            }
        }

        // Send the last tasks
        if ($apiTasks) {
            try {
                $mosparoApiClient->batchUpdateRulePackage($rulePackageId, $apiTasks);
            } catch (Exception) {
                return false;
            }
        }

        $progressBar->finish();

        return true;
    }
}