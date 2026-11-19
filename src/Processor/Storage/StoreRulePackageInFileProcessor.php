<?php

namespace MosparoRpg\Processor\Storage;

use DateTime;
use DateTimeInterface;
use Exception;
use MosparoRpg\Cli\ProgressBarFormat;
use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\OutputContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Task\RuleItemTask;
use MosparoRpg\Task\RuleTask;
use MosparoRpg\Trait\KeyTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use ZipArchive;

class StoreRulePackageInFileProcessor implements ProcessorInterface
{
    const KEY = 'store_rule_package_in_file';

    use KeyTrait;

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

        $filePath = $context->getOption('file_path', null);
        $format = $context->getOption('format', 'json');

        if (!is_string($filePath) || $filePath === '') {
            $mainProcessor->getLogger()->error(sprintf('Output "%s": the option file_path is missing or invalid.', $context->getIdentifier()));

            return false;
        }

        $fullPath = Path::makeAbsolute(Path::canonicalize($filePath), $rpContext->getProfileDirectory()->getPath());

        $directory = Path::getDirectory($fullPath);
        (new Filesystem())->mkdir($directory, 0755);
        if (!file_exists($directory)) {
            throw new Exception('Output directory does not exist.');
        }

        // Store the rule package
        if ($format === 'zip') {
            $destination = $this->storeRulePackageAsZip($context, $fullPath, $rpContext);
        } else {
            $destination = $this->storeRulePackageAsJson($context, $fullPath, $rpContext);
        }

        // Generate the SHA256 hash
        file_put_contents(
            $destination->getFilePath() . '.sha256',
            hash_file('sha256', $destination->getFilePath())
        );

        return true;
    }

    protected function storeRulePackageAsJson(ContextInterface $context, string $fullPath, RulePackageContext $rpContext): FileElement
    {
        $destination = new FileElement($fullPath);

        $progressBar = $context->getCliOutputHelper()->startProgress(
            ProgressBarFormat::STEPS,
            'Create JSON file of rule package tasks...',
            $rpContext->getDestination()->getNumberOfTasks()
        );

        // Add the rule package header
        $rulePackageContent = sprintf(
            '{"lastUpdatedAt":%s,"refreshInterval":%d,"rules":[',
            json_encode((new DateTime())->format(DateTimeInterface::ATOM)),
            $rpContext->getRefreshInterval() ?? 86400
        );
        $destination->writeToFile($rulePackageContent, false);

        // Loop through the tasks
        $openRule = false;
        $suffix = '';
        $rpContext->getDestination()->rewind();
        $counter = 0;
        foreach ($rpContext->getDestination() as $task) {
            $counter++;
            $progressBar->advance($counter);

            if ($task instanceof RuleTask) {
                if ($openRule) {
                    $destination->writeToFile(']},');
                }

                $ruleHeader = sprintf(
                    '{"uuid":%s,"name":%s,"description":%s,"type":%s,"spamRatingFactor":%s,"items":[',
                    json_encode($task->getUuid(), JSON_INVALID_UTF8_SUBSTITUTE),
                    json_encode($task->getName(), JSON_INVALID_UTF8_SUBSTITUTE),
                    json_encode($task->getDescription(), JSON_INVALID_UTF8_SUBSTITUTE),
                    json_encode($task->getType(), JSON_INVALID_UTF8_SUBSTITUTE),
                    json_encode($task->getSpamRatingFactor())
                );

                $destination->writeToFile($ruleHeader);
                $openRule = true;
                $suffix = '';
            } else if ($task instanceof RuleItemTask) {
                $ruleItem = [
                    'uuid' => $task->getUuid(),
                    'type' => $task->getType(),
                    'value' => $task->getValue(),
                    'rating' => $task->getRating(),
                ];

                $destination->writeToFile($suffix . json_encode($ruleItem, JSON_INVALID_UTF8_SUBSTITUTE));
                $suffix = ',';
            }
        }

        $progressBar->finish();

        // Finish the last rule
        if ($openRule) {
            $destination->writeToFile(']}');
        }

        // Add the rule package footer
        $licenseContent = '';
        if ($rpContext->getLicenseFile()) {
            $content = $rpContext->readLicenseFile();
            if ($content) {
                $licenseContent = sprintf(',"license":%s', json_encode($content, JSON_INVALID_UTF8_SUBSTITUTE));
            }
        }
        $destination->writeToFile(sprintf(']%s}', $licenseContent));

        return $destination;
    }

    protected function storeRulePackageAsZip(ContextInterface $context, string $fullPath, RulePackageContext $rpContext): FileElement
    {
        $zip = new ZipArchive();
        $zip->open($fullPath, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        $maxItems = 1000;

        $ruleFiles = [];
        $ruleItemFiles = [];

        $ruleFileId = 0;
        $ruleFileContent = [];
        $ruleCounter = 0;

        $ruleItemFileId = 0;
        $ruleItemFileContent = [];
        $ruleItemCounter = 0;

        $progressBar = $context->getCliOutputHelper()->startProgress(
            ProgressBarFormat::STEPS,
            'Create ZIP file of rule package tasks...',
            $rpContext->getDestination()->getNumberOfTasks()
        );

        $rpContext->getDestination()->rewind();
        $counter = 0;
        foreach ($rpContext->getDestination() as $task) {
            $counter++;
            $progressBar->advance($counter);

            if ($task instanceof RuleTask) {
                $ruleFileContent[] = [
                    'uuid' => $task->getUuid(),
                    'name' => $task->getName(),
                    'description' => $task->getDescription(),
                    'type' => $task->getType(),
                    'spamRatingFactor' => $task->getSpamRatingFactor(),
                ];

                $ruleCounter++;

                if ($ruleCounter >= $maxItems) {
                    $ruleFiles[] = $this->storeRulePackageFile($zip, $ruleFileId, 'rules-%d.json', $ruleFileContent);
                    $ruleFileId++;
                    $ruleFileContent = [];
                    $ruleCounter = 0;
                }
            } else if ($task instanceof RuleItemTask) {
                $ruleItemFileContent[] = [
                    'ruleUuid' => $task->getRuleUuid(),
                    'uuid' => $task->getUuid(),
                    'type' => $task->getType(),
                    'value' => $task->getValue(),
                    'rating' => $task->getRating(),
                ];

                $ruleItemCounter++;

                if ($ruleItemCounter >= $maxItems) {
                    $ruleItemFiles[] = $this->storeRulePackageFile($zip, $ruleItemFileId, 'rule-items-%d.json', $ruleItemFileContent);
                    $ruleItemFileId++;
                    $ruleItemFileContent = [];
                    $ruleItemCounter = 0;
                }
            }
        }

        // Add the last files
        $ruleFiles[] = $this->storeRulePackageFile($zip, $ruleFileId, 'rules-%d.json', $ruleFileContent);
        $ruleItemFiles[] = $this->storeRulePackageFile($zip, $ruleItemFileId, 'rule-items-%d.json', $ruleItemFileContent);

        $zip->addFromString('rule-package.json', json_encode([
            'lastUpdatedAt' => (new DateTime())->format(DateTimeInterface::ATOM),
            'refreshInterval' => $rpContext->getRefreshInterval() ?? 86400,
            'rFiles' => $ruleFiles,
            'riFiles' => $ruleItemFiles,
        ], JSON_INVALID_UTF8_SUBSTITUTE));

        if ($rpContext->getLicenseFile()) {
            $content = $rpContext->readLicenseFile();

            if ($content) {
                $zip->addFromString('LICENSE', $content);
            }
        }

        $progressBar->finish();

        return new FileElement($fullPath);
    }

    protected function storeRulePackageFile(ZipArchive $zip, int $id, string $pattern, array $content): string
    {
        $fileName = sprintf($pattern, $id);
        $zip->addFromString($fileName, json_encode($content, JSON_INVALID_UTF8_SUBSTITUTE));

        return $fileName;
    }
}
