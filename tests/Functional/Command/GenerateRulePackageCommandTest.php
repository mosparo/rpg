<?php

namespace MosparoRpg\Tests\Functional\Command;

use MosparoRpg\Tests\Support\TemporaryDirectoryTrait;
use MosparoRpg\Tests\Support\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Runs the real command with the real service container against a profile in a temporary directory.
 */
class GenerateRulePackageCommandTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
        mkdir($this->tmpPath('profile/input'), 0777, true);
        mkdir($this->tmpPath('work'));
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        $this->removeTemporaryDirectory();
    }

    /**
     * Every run gets a fresh kernel, because services like the UuidHelper keep state.
     */
    private function runCommand(array $input): CommandTester
    {
        $this->kernel?->shutdown();
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $tester = new CommandTester($application->find('rpg:generate-rule-package'));
        $tester->execute($input);

        return $tester;
    }

    private function writeProfile(array $overrides = []): string
    {
        $profile = array_replace([
            'name' => 'Test package',
            'refresh_interval' => 3600,
            'paths' => [
                'task_file' => $this->tmpPath('work/package.rptasks'),
                'uuid_index_file' => $this->tmpPath('work/package.rpuuids'),
            ],
            'input' => [
                [
                    'identifier' => 'domains',
                    'type' => 'list',
                    'rule' => [
                        'type' => 'domain',
                        'name' => 'Spam domains',
                        'spam_rating_factor' => 2,
                        'item' => ['type' => 'text', 'rating' => 5],
                    ],
                    'source' => ['type' => 'file', 'path' => 'input/domains.txt'],
                ],
            ],
            'output' => [
                [
                    'identifier' => 'json',
                    'type' => 'file',
                    'options' => ['file_path' => 'output/package.json'],
                ],
            ],
        ], $overrides);

        file_put_contents($this->tmpPath('profile/input/domains.txt'), "spam.example\nmore-spam.example\n");

        $path = $this->tmpPath('profile/profile.yaml');
        file_put_contents($path, Yaml::dump($profile, 6));

        return $path;
    }

    private function readPackage(): array
    {
        return json_decode(file_get_contents($this->tmpPath('profile/output/package.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testGeneratesJsonRulePackage(): void
    {
        $tester = $this->runCommand([
            'profile' => $this->writeProfile(),
            '--tempdir' => $this->tmpPath('work'),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Test package', $tester->getDisplay());
        $this->assertStringContainsString('Profile processed.', $tester->getDisplay());

        $package = $this->readPackage();
        $this->assertSame(3600, $package['refreshInterval']);
        $this->assertCount(1, $package['rules']);

        $rule = $package['rules'][0];
        $this->assertSame('Spam domains', $rule['name']);
        $this->assertSame('domain', $rule['type']);
        $this->assertSame(['spam.example', 'more-spam.example'], array_column($rule['items'], 'value'));
        $this->assertSame(['text', 'text'], array_column($rule['items'], 'type'));
        $this->assertNull($rule['description']);
        $this->assertArrayNotHasKey('license', $package);

        $this->assertFileExists($this->tmpPath('profile/output/package.json.sha256'));
        $this->assertFileExists($this->tmpPath('work/package.rptasks'));
        $this->assertFileExists($this->tmpPath('work/package.rpuuids'));
    }

    public function testDescriptionAndLicenseAreWritten(): void
    {
        $license = "MIT License\n\nCopyright (c) 2026 \"Example\"\n";
        file_put_contents($this->tmpPath('profile/LICENSE'), $license);

        $profile = $this->writeProfile(['license_file' => 'LICENSE']);
        $data = Yaml::parseFile($profile);
        $data['input'][0]['rule']['description'] = 'Domains that send "spam"';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());

        $package = $this->readPackage();
        $this->assertSame('Domains that send "spam"', $package['rules'][0]['description']);
        $this->assertSame($license, $package['license']);
    }

    public function testMissingLicenseFileIsReportedBeforeProcessing(): void
    {
        $profile = $this->writeProfile(['license_file' => 'legal/LICENSE']);

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('License file "legal/LICENSE" not found or not readable.', $tester->getDisplay());
        $this->assertStringContainsString($this->tmpPath('profile/legal/LICENSE'), $tester->getDisplay(), 'The resolved path helps to find the error.');
        $this->assertFileDoesNotExist($this->tmpPath('work/package.rptasks'), 'Nothing must be processed.');
        $this->assertFileDoesNotExist($this->tmpPath('profile/output/package.json'));
    }

    public function testMissingLicenseFileIsReportedWithOutputOnly(): void
    {
        $profile = $this->writeProfile();
        $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);
        unlink($this->tmpPath('profile/output/package.json'));

        $data = Yaml::parseFile($profile);
        $data['license_file'] = 'LICENSE';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work'), '--outputOnly' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('License file "LICENSE" not found or not readable.', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->tmpPath('profile/output/package.json'));
    }

    public function testDirectoryAsLicenseFileIsReported(): void
    {
        mkdir($this->tmpPath('profile/LICENSE'));
        $profile = $this->writeProfile(['license_file' => 'LICENSE']);

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('License file "LICENSE" not found or not readable.', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->tmpPath('profile/output/package.json'));
    }

    public function testLicenseFileMustBeAString(): void
    {
        $profile = $this->writeProfile(['license_file' => ['LICENSE']]);

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('The option "license_file" must be a string', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->tmpPath('work/package.rptasks'));
    }

    public function testUuidsAreStableBetweenRuns(): void
    {
        $profile = $this->writeProfile();
        $input = ['profile' => $profile, '--tempdir' => $this->tmpPath('work')];

        $this->runCommand($input);
        $first = $this->readPackage();

        $this->runCommand($input);
        $second = $this->readPackage();

        $this->assertSame($first['rules'][0]['uuid'], $second['rules'][0]['uuid']);
        $this->assertSame(
            array_column($first['rules'][0]['items'], 'uuid'),
            array_column($second['rules'][0]['items'], 'uuid')
        );
    }

    public function testTemporaryFilesAreRemoved(): void
    {
        file_put_contents($this->tmpPath('profile/input/domains.txt.gz'), gzencode("a.example\nb.example\n"));
        $profile = $this->writeProfile();
        // Point the source to the gzipped file after writeProfile() created the plain one
        $data = Yaml::parseFile($profile);
        $data['input'][0]['source']['path'] = 'input/domains.txt.gz';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(['a.example', 'b.example'], array_column($this->readPackage()['rules'][0]['items'], 'value'));
        $this->assertSame([], glob($this->tmpPath('work/mosparo_rpg_*')), 'Temporary files must be cleaned up.');
    }

    public function testTemporaryFilesAreRemovedWhenAnExceptionIsThrown(): void
    {
        file_put_contents($this->tmpPath('profile/input/domains.txt.gz'), gzencode("a.example\n"));
        $profile = $this->writeProfile();
        $data = Yaml::parseFile($profile);
        $data['input'][0]['source']['path'] = 'input/domains.txt.gz';
        // The second input throws an exception, after the first one extracted the gzip file into a temporary file
        $data['input'][1] = $data['input'][0];
        $data['input'][1]['identifier'] = 'broken';
        $data['input'][1]['source'] = ['type' => 'ftp'];
        file_put_contents($profile, Yaml::dump($data, 6));

        try {
            $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);
            $this->fail('The invalid source type must lead to an exception.');
        } catch (\Throwable $e) {
            if ($e instanceof \PHPUnit\Framework\AssertionFailedError) {
                throw $e;
            }
        }

        $this->assertSame([], glob($this->tmpPath('work/mosparo_rpg_*')), 'Temporary files must be cleaned up.');
    }

    public function testUnknownInputTypeIsReportedBeforeProcessing(): void
    {
        $profile = $this->writeProfile();
        $data = Yaml::parseFile($profile);
        $data['input'][0]['type'] = 'lsit';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Input "domains" has an unknown or missing type "lsit"', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->tmpPath('work/package.rptasks'), 'Nothing must be processed.');
    }

    public function testUnknownOutputTypeIsReportedBeforeProcessing(): void
    {
        $profile = $this->writeProfile();
        $data = Yaml::parseFile($profile);
        $data['output'][0]['type'] = 'mosparo';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Output "json" has an unknown or missing type "mosparo"', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->tmpPath('work/package.rptasks'), 'The inputs must not be processed.');
    }

    public function testMissingSectionsAreReported(): void
    {
        $profile = $this->writeProfile();
        $data = Yaml::parseFile($profile);
        unset($data['output']);
        $data['input'] = [];
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('The section "input" is missing or empty.', $tester->getDisplay());
        $this->assertStringContainsString('The section "output" is missing or empty.', $tester->getDisplay());
    }

    public function testEmptyProfileIsReported(): void
    {
        $profile = $this->tmpPath('profile/empty.yaml');
        file_put_contents($profile, '');

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('The profile is empty or not a YAML mapping.', $tester->getDisplay());
    }

    public function testOutputOnlyReusesExistingTaskFile(): void
    {
        $profile = $this->writeProfile();
        $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);
        $first = $this->readPackage();

        // Changing the input must have no effect when only the outputs are processed
        file_put_contents($this->tmpPath('profile/input/domains.txt'), "changed.example\n");
        unlink($this->tmpPath('profile/output/package.json'));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work'), '--outputOnly' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Inputs are skipped as requested.', $tester->getDisplay());
        $this->assertSame(
            array_column($first['rules'][0]['items'], 'value'),
            array_column($this->readPackage()['rules'][0]['items'], 'value')
        );
    }

    public function testOutputOnlyFailsWithoutTaskFile(): void
    {
        $tester = $this->runCommand([
            'profile' => $this->writeProfile(),
            '--tempdir' => $this->tmpPath('work'),
            '--outputOnly' => true,
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertFileDoesNotExist($this->tmpPath('profile/output/package.json'));
    }

    public function testFailsForMissingProfile(): void
    {
        $tester = $this->runCommand([
            'profile' => $this->tmpPath('profile/does-not-exist.yaml'),
            '--tempdir' => $this->tmpPath('work'),
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailsForMissingTemporaryDirectory(): void
    {
        $tester = $this->runCommand([
            'profile' => $this->writeProfile(),
            '--tempdir' => $this->tmpPath('does-not-exist'),
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testAbortsOnInputErrorAndWritesNoOutput(): void
    {
        $profile = $this->writeProfile();
        // A ZIP archive with two files is rejected by the ZippedFileProcessor and logged as error
        $zip = new \ZipArchive();
        $zip->open($this->tmpPath('profile/input/two.zip'), \ZipArchive::CREATE);
        $zip->addFromString('a.txt', 'a');
        $zip->addFromString('b.txt', 'b');
        $zip->close();

        $data = Yaml::parseFile($profile);
        $data['input'][0]['source']['path'] = 'input/two.zip';
        file_put_contents($profile, Yaml::dump($data, 6));

        $tester = $this->runCommand(['profile' => $profile, '--tempdir' => $this->tmpPath('work')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertFileDoesNotExist($this->tmpPath('profile/output/package.json'));
    }
}
