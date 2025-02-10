<?php

namespace MosparoRpg\Processor;

use MosparoRpg\Context\ContextInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\DependencyInjection\Container;

class MainProcessor
{
    protected Container $container;

    protected ?ConsoleLogger $logger = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function setLogger(ConsoleLogger $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger(): ConsoleLogger
    {
        return $this->logger;
    }

    public function process(ContextInterface $context, array $processors)
    {
        ksort($processors);

        foreach ($processors as $subProcessors) {
            foreach ($subProcessors as $processor) {
                $obj = $this->container->get($processor);
                if (!$obj->isApplicable($context)) {
                    $this->logger->debug(sprintf('Processor "%s" not applicable for context "%s".', $processor, $context));
                    continue;
                }

                $this->logger->debug(sprintf('Execute processor "%s" for context "%s".', $processor, $context));

                $result = $obj->process($context, $this);

                if ($result === false) {
                    $this->logger->info(sprintf('Result from processor "%s" indicates an error, aborting the process for this rule.', $processor));
                    break 2;
                }
            }
        }
    }
}