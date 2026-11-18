<?php

namespace MosparoRpg\Tests\Support;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\DirectoryElement;
use MosparoRpg\Element\TaskFileElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Type\Input\ListInputType;
use MosparoRpg\Type\InputTypeInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * Small helpers to build the objects most tests need.
 */
final class ContextFactory
{
    public static function rulePackageContext(string $dir, ?TaskFileElement $destination = null): RulePackageContext
    {
        $context = (new RulePackageContext())
            ->setName('test/package')
            ->setRefreshInterval(3600)
            ->setProfileDirectory(new DirectoryElement($dir))
            ->setTemporaryDirectory(new DirectoryElement($dir));

        if ($destination !== null) {
            $context->setDestination($destination);
        }

        return $context;
    }

    public static function ruleContext(RulePackageContext $rulePackageContext, ?InputTypeInterface $inputType = null): RuleContext
    {
        return (new RuleContext($rulePackageContext, $inputType ?? new ListInputType()))
            ->setIdentifier('rule-1')
            ->setType('email')
            ->setName('Test rule')
            ->setSpamRatingFactor(1.5);
    }

    /**
     * Builds a MainProcessor with a plain container and a buffered console logger.
     *
     * @param array<string, object> $services
     */
    public static function mainProcessor(array $services = [], ?BufferedOutput $output = null): MainProcessor
    {
        $container = new Container();
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        $mainProcessor = new MainProcessor($container);
        $mainProcessor->setLogger(new ConsoleLogger($output ?? new BufferedOutput(OutputInterface::VERBOSITY_DEBUG)));

        return $mainProcessor;
    }
}
