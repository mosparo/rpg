<?php

namespace MosparoRpg\Command;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Helper\CliOutputHelper;
use MosparoRpg\Helper\UuidHelper;
use MosparoRpg\Processor\MainProcessor;
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

#[AsCommand(name: 'rpg:generate-rule-package', aliases: ['generate'])]
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
            ->addOption('outputOnly', 'o', InputOption::VALUE_NONE, 'If set, the command will not recreate the rule package but just process the outputs')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consoleLogger = new ConsoleLogger($output);
        $this->mainProcessor->setLogger($consoleLogger);
        $cliOutputHelper = new CliOutputHelper($output);

        $output->writeln([
            PHP_EOL .
            '                                               _____  _____   _____' . PHP_EOL .
            '                                              |  __ \|  __ \ / ____|' . PHP_EOL .
            '  _ __ ___   ___  ___ _ __   __ _ _ __ ___    | |__) | |__) | |  __ ' . PHP_EOL .
            ' | \'_ ` _ \ / _ \/ __| \'_ \ / _` | \'__/ _ \   |  _  /|  ___/| | |_ |' . PHP_EOL .
            ' | | | | | | (_) \__ \ |_) | (_| | | | (_) |  | | \ \| |    | |__| |' . PHP_EOL .
            ' |_| |_| |_|\___/|___/ .__/ \__,_|_|  \___/   |_|  \_\_|     \_____|' . PHP_EOL .
            '                     | |' . PHP_EOL .
            '                     |_|  mosparo.io' . PHP_EOL
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

        // Check the profile before anything is processed
        $profileErrors = $this->validateProfile($profile);
        if ($profileErrors) {
            foreach ($profileErrors as $profileError) {
                $consoleLogger->critical(sprintf('Invalid profile: %s', $profileError));
            }

            return Command::FAILURE;
        }

        $output->writeln([
            ' ' . str_pad('Name:', 10) . ($profile['name'] ?? 'Unnamed rule package'),
            ' ' . str_pad('File:', 10) . $profileFilePath,
            '',
        ]);

        // Set the uuid file
        $this->uuidHelper->setUuidFilePath($profile['paths']['uuid_index_path'] ?? $profileFilePath . '.rpuuids');

        // Prepare the rule package context
        $rulePackageContext = (new RulePackageContext())
            ->setName($profile['name'] ?? 'Unnamed rule package')
            ->setRefreshInterval($profile['refresh_interval'] ?? 86400)
            ->setProfileDirectory(new DirectoryElement(dirname($profileFilePath)))
            ->setTemporaryDirectory(new DirectoryElement($temporaryDirectory))
        ;

        if ($profile['license_file'] ?? '') {
            $rulePackageContext->setLicenseFile($profile['license_file']);

            // Checked here and not in validateProfile(), so the path is resolved the same way as when the file is read
            if (!$rulePackageContext->isLicenseFileReadable()) {
                $consoleLogger->critical(sprintf(
                    'Invalid profile: License file "%s" not found or not readable. Full path: "%s"',
                    $rulePackageContext->getLicenseFile(),
                    $rulePackageContext->getLicenseFilePath()
                ));

                return Command::FAILURE;
            }
        }

        // Create the destination for the rule package tasks.
        $destination = new TaskFileElement($profile['paths']['task_file'] ?? $profileFilePath . '.rptasks');
        $rulePackageContext->setDestination($destination);

        try {
            if ($input->getOption('outputOnly') && !$destination->valid()) {
                $consoleLogger->critical('The command was started with -o, output only, but the task file is not valid. Does it exist?');
                return Command::FAILURE;
            }

            // We do not create the tasks if the output only option is set.
            if (!$input->getOption('outputOnly') || !$destination->valid()) {
                $destination->resetTaskFile();

                foreach ($profile['input'] as $idx => $inputProfile) {
                    $cliOutputHelper->writeln('input', sprintf('Process input %s', $inputProfile['identifier'] ?? $idx), 'green');

                    $type = $this->typeManager->getInputType($inputProfile['type'] ?? null);

                    $ruleContext = $type->prepareRuleContext($rulePackageContext, $inputProfile);

                    $this->mainProcessor->process($ruleContext, $type->getProcessors());
                }

                // Store the new list of UUIDs
                $destination->finalizeTasksFile();
                $this->uuidHelper->storeNewUuidIndex();
            } else {
                $cliOutputHelper->writeln('input', 'Inputs are skipped as requested.', 'green');
            }

            // Abort the generating process if an error occurred and the option to abort is set
            if (($profile['abort_on_error'] ?? true) && $consoleLogger->hasErrored()) {
                return Command::FAILURE;
            }

            $output->writeln([
                '',
                ' ' . str_pad('Tasks:', 10) . $destination->getNumberOfTasks(),
                '',
                ' Rule package tasks prepared. Processing outputs...',
                '',
            ]);

            // Process the configured outputs
            foreach ($profile['output'] as $idx => $outputProfile) {
                $cliOutputHelper->writeln('output', sprintf('Process output %s', $outputProfile['identifier'] ?? $idx));
                $type = $this->typeManager->getOutputType($outputProfile['type'] ?? null);

                $outputContext = $type->prepareOutputContext($rulePackageContext, $outputProfile);

                $outputContext->setCliOutputHelper($cliOutputHelper);

                $this->mainProcessor->process($outputContext, $type->getProcessors());
            }

            $output->writeln([
                'Profile processed.',
                '',
            ]);

            return Command::SUCCESS;
        } finally {
            // Remove the temporary files, also if a processor throws an exception
            $this->cleanupTemporaryFiles($rulePackageContext);
        }
    }

    /**
     * Checks the structure of the profile and returns a list of errors (empty if the profile is valid).
     */
    protected function validateProfile(mixed $profile): array
    {
        if (!is_array($profile)) {
            return ['The profile is empty or not a YAML mapping.'];
        }

        $errors = [];
        foreach (['input', 'output'] as $section) {
            if (!is_array($profile[$section] ?? null) || !$profile[$section]) {
                $errors[] = sprintf('The section "%s" is missing or empty.', $section);
                continue;
            }

            foreach ($profile[$section] as $idx => $sectionProfile) {
                $typeKey = $sectionProfile['type'] ?? null;
                $type = ($section === 'input') ? $this->typeManager->getInputType($typeKey) : $this->typeManager->getOutputType($typeKey);

                if ($type === null) {
                    $errors[] = sprintf(
                        '%s "%s" has an unknown or missing type "%s".',
                        ucfirst($section),
                        $sectionProfile['identifier'] ?? $idx,
                        is_scalar($typeKey) ? $typeKey : ''
                    );
                }
            }
        }

        if (isset($profile['license_file']) && !is_string($profile['license_file'])) {
            $errors[] = 'The option "license_file" must be a string with the path to the license file.';
        }

        return $errors;
    }

    protected function preparePath($path): string
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
