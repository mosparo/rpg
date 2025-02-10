<?php

namespace MosparoRpg\Element;

use MosparoRpg\Context\RulePackageContext;
use MosparoRpg\Trait\ElementOptionTrait;
use MosparoRpg\Trait\KeyTrait;
use MosparoRpg\Util\TemporaryFileUtil;

class TemporaryFileElement extends FileElement
{
    const KEY = 'temporary_file_element';

    use KeyTrait;
    use ElementOptionTrait;

    protected RulePackageContext $rulePackageContext;

    protected string $suffix;

    public function __construct(RulePackageContext $rulePackageContext, string $suffix)
    {
        parent::__construct('');

        $this->rulePackageContext = $rulePackageContext;
        $this->suffix = $suffix;
    }

    public function getFilePath(): string
    {
        if (!$this->filePath) {
            $this->filePath = TemporaryFileUtil::createTemporaryFile($this->rulePackageContext, $this->suffix);
        }

        return $this->filePath;
    }
}