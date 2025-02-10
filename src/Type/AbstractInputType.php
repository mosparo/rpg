<?php

namespace MosparoRpg\Type;

use MosparoRpg\Context\RuleContext;
use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Element\FileElement;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\File\GzippedFileProcessor;
use MosparoRpg\Processor\File\ZippedFileProcessor;
use MosparoRpg\Processor\Identification\ResolveUuidProcessor;
use MosparoRpg\Processor\Package\AppendRuleItemProcessor;
use MosparoRpg\Processor\Package\AppendRuleProcessor;
use MosparoRpg\Processor\Package\GenerateRuleFooterProcessor;
use MosparoRpg\Processor\Package\GenerateRuleHeaderProcessor;
use MosparoRpg\Processor\Web\DownloadFileProcessor;
use MosparoRpg\Trait\MergeProcessorsTrait;
use Symfony\Component\Filesystem\Path;

abstract class AbstractInputType implements InputTypeInterface, TypeInterface
{
    use MergeProcessorsTrait;

    public function getProcessors(): array
    {
        return [
            InputTypeInterface::PRIORITY_ORGANIZE => [
                DownloadFileProcessor::class,
            ],
            InputTypeInterface::PRIORITY_EXTRACT => [
                GzippedFileProcessor::class,
                ZippedFileProcessor::class,
            ],
            InputTypeInterface::PRIORITY_PREPARE => [
                ResolveUuidProcessor::class,
            ],
            InputTypeInterface::PRIORITY_OBJECT_START => [
                GenerateRuleHeaderProcessor::class,
            ],
            InputTypeInterface::PRIORITY_OBJECT_END => [
                GenerateRuleFooterProcessor::class,
            ],
            InputTypeInterface::PRIORITY_FINALIZE => [
                AppendRuleProcessor::class,
            ],
        ];
    }

    public function getRuleItemProcessors(): array
    {
        return [
            InputTypeInterface::PRIORITY_OBJECT_END => [
                ResolveUuidProcessor::class,
            ],
            InputTypeInterface::PRIORITY_FINALIZE => [
                AppendRuleItemProcessor::class,
            ],
        ];
    }

    public function prepareRuleContext(RulePackageContext $rulePackageContext, array $input): RuleContext
    {
        $ruleContext = (new RuleContext($rulePackageContext, $this))
            ->setIdentifier($input['rule']['identifier'] ?? uniqid())
            ->setType($input['rule']['type'] ?? '')
            ->setName($input['rule']['name'] ?? '')
            ->setSpamRatingFactor($input['rule']['spam_rating_factor'] ?? 1)
            ->setItemOptions($input['rule']['item'] ?? []);

        if ($input['source']['type'] === 'file') {
            $source = new FileElement(Path::makeAbsolute(Path::canonicalize($input['source']['path']), $rulePackageContext->getProfileDirectory()->getPath()));
        } else if ($input['source']['type'] === 'web') {
            $source = new UrlElement($input['source']['url']);
        }

        $source->setOptions($input['source']['options'] ?? []);

        $ruleContext->setSource($source);

        $ruleContext->setDestination(new TemporaryFileElement($rulePackageContext, 'rule'));

        return $ruleContext;
    }
}