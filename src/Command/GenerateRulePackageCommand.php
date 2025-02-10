<?php

namespace MosparoRpg\Command;

use MosparoRpg\__old\Input\ListInputProcessor;
use MosparoRpg\__old\Input\TableInputProcessor;
use MosparoRpg\__old\Output\FileOutputProcessor;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Helper\UuidHelper;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\Package\GenerateRulePackageFooterProcessor;
use MosparoRpg\Processor\Package\GenerateRulePackageHeaderProcessor;
use MosparoRpg\Type\Manager as TypeManager;
use MosparoRpg\Util\TemporaryFileUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'rpg:generate-rule-package')]
class GenerateRulePackageCommand extends Command
{
    protected TypeManager $typeManager;

    protected MainProcessor $mainProcessor;

    protected UuidHelper $uuidHelper;

    public function __construct(TypeManager $typeManager, MainProcessor $mainProcessor, UuidHelper $uuidHelper)
    {
        parent::__construct();

        $this->typeManager = $typeManager;
        $this->mainProcessor = $mainProcessor;
        $this->uuidHelper = $uuidHelper;
    }

    protected function configure(): void
    {
        $this
            // Arguments
            ->addArgument('profile', InputArgument::REQUIRED, 'The profile configuration file.')

            // Options
            ->addOption('tempdir', 't', InputOption::VALUE_REQUIRED, 'The temporary directory.', sys_get_temp_dir())
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consoleLogger = new ConsoleLogger($output);
        $this->mainProcessor->setLogger($consoleLogger);

        $output->writeln([
            'mosparo RPG'
        ]);

        $filesystem = new Filesystem();
        $profileFilePath = $this->preparePath($input->getArgument('profile'));
        $temporaryDirectory = $this->preparePath($input->getOption('tempdir'));

        // Check the profile file path
        if (!$filesystem->exists($profileFilePath) || !is_readable($profileFilePath)) {
            $consoleLogger->critical(sprintf('Profile file does not exist or is not readable! Path: %s', $profileFilePath));
            return Command::FAILURE;
        }

        // Check the temporary directory path
        if (!$filesystem->exists($temporaryDirectory) || !is_writable($temporaryDirectory)) {
            $consoleLogger->critical(sprintf('Temporary directory does not exist or is not writable! Path: %s', $temporaryDirectory));
            return Command::FAILURE;
        }

        // Load the profile
        $profile = Yaml::parseFile($profileFilePath);

        // Set the uuid file
        $this->uuidHelper->setUuidFilePath($profile['uuid_index_path'] ?? $profileFilePath . '.uuidIndex');

        // Prepare the rule package context
        $rulePackageContext = (new RulePackageContext())
            ->setName($profile['name'] ?? 'Unnamed rule package')
            ->setRefreshInterval($profile['refresh_interval'] ?? 86400)
            ->setProfileDirectory(new DirectoryElement(dirname($profileFilePath)))
            ->setTemporaryDirectory(new DirectoryElement($temporaryDirectory));

        // Chaining is not possible with this one since we need the $rulePackageContext in the TemporaryFileElement
        $rulePackageContext->setDestination(new TemporaryFileElement($rulePackageContext, 'package'));

        // Generate the rule package header
        $this->mainProcessor->process($rulePackageContext, [
            500 => [GenerateRulePackageHeaderProcessor::class]
        ]);

        foreach ($profile['input'] as $inputProfile) {
            $type = $this->typeManager->getInputType($inputProfile['type'] ?? null);

            $ruleContext = $type->prepareRuleContext($rulePackageContext, $inputProfile);

            $this->mainProcessor->process($ruleContext, $type->getProcessors());
        }

        // Abort the generating process if an error occurred and the option to abort is set
        if (($profile['abort_on_error'] ?? true) && $consoleLogger->hasErrored()) {
            // Cleanup
            $this->cleanupTemporaryFiles($rulePackageContext);

            return Command::FAILURE;
        }

        // Generate the rule package footer
        $this->mainProcessor->process($rulePackageContext, [
            500 => [GenerateRulePackageFooterProcessor::class]
        ]);

        // Process the configured outputs
        foreach ($profile['output'] as $outputProfile) {
            $type = $this->typeManager->getOutputType($outputProfile['type'] ?? null);

            $outputContext = $type->prepareOutputContext($rulePackageContext, $outputProfile);

            $this->mainProcessor->process($outputContext, $type->getProcessors());
        }

        // Cleanup
        $this->cleanupTemporaryFiles($rulePackageContext);

        return Command::SUCCESS;
    }

    protected function preparePath($path)
    {
        $path = Path::canonicalize($path);

        if (!Path::isAbsolute($path)) {
            $path = Path::makeAbsolute($path, getcwd());
        }

        return $path;
    }

    protected function cleanupTemporaryFiles(RulePackageContext $rulePackageContext): void
    {
        // Remove all the temporary files
        foreach ($rulePackageContext->getTemporaryFiles() as $tmpFile) {
            TemporaryFileUtil::removeTemporaryFile($tmpFile);
        }
    }
}