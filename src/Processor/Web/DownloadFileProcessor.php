<?php

namespace MosparoRpg\Processor\Web;

use MosparoRpg\Context\ContextInterface;
use MosparoRpg\Context\RuleContext;
use MosparoRpg\Element\TemporaryFileElement;
use MosparoRpg\Element\UrlElement;
use MosparoRpg\Processor\MainProcessor;
use MosparoRpg\Processor\ProcessorInterface;
use MosparoRpg\Trait\KeyTrait;

class DownloadFileProcessor implements ProcessorInterface
{
    const KEY = 'download_file';

    use KeyTrait;

    protected $destinationFileHandle = null;

    public function isApplicable(ContextInterface $context): bool
    {
        return $context instanceof RuleContext && $context->getSource() instanceof UrlElement;
    }

    public function process(ContextInterface $context, MainProcessor $mainProcessor): bool
    {
        $mainProcessor->getLogger()->debug(sprintf('Create temporary file for download from "%s".', $context->getSource()->getUrl()));
        $destination = new TemporaryFileElement($context->getRulePackageContext(), 'downloaded');

        if ($context->getSource()->getOption('accepable_content_types', [])) {
            $result = $this->verifyContentType($mainProcessor, $context->getSource()->getUrl(), $context->getSource()->getOptions());

            if (!$result) {
                $mainProcessor->getLogger()->error(sprintf('Content type of "%s" is not acceptable.', $context->getSource()->getUrl()));
                return false;
            }
        }

        $result = $this->downloadFile($mainProcessor, $context->getSource()->getUrl(), $destination, $context->getSource()->getOptions());

        $context->setSource($destination);

        return $result;
    }

    protected function downloadFile(MainProcessor $mainProcessor, string $url, TemporaryFileElement $destination, array $options = []): bool
    {
        $result = true;
        $this->destinationFileHandle = fopen($destination->getFilePath(), 'w+');

        $curlHandle = $this->prepareCurlRequest($url, $options);
        curl_setopt($curlHandle, CURLOPT_FILE, $this->destinationFileHandle);
        curl_setopt($curlHandle, CURLOPT_WRITEFUNCTION, [$this, 'writeToFile']);

        $mainProcessor->getLogger()->debug(sprintf('Start download from "%s".', $url));
        curl_exec($curlHandle);

        $info = curl_getinfo($curlHandle);
        if (intval($info['http_code']) > 299) {
            $result = false;
            $mainProcessor->getLogger()->error(sprintf('Download from "%s" failed with error code %s.', $url, $info['http_code'] ?? 'unknown'));
        } else {
            $mainProcessor->getLogger()->info(sprintf('Download finished from "%s".', $url));
        }

        curl_close($curlHandle);

        fclose($this->destinationFileHandle);

        return $result;
    }

    protected function verifyContentType(MainProcessor $mainProcessor, string $url, array $options = []): bool
    {
        $curlHandle = $this->prepareCurlRequest($url, $options);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($curlHandle, CURLOPT_NOBODY, true);

        $mainProcessor->getLogger()->debug(sprintf('Start loading headers from "%s".', $url));
        $result = curl_exec($curlHandle);
        curl_close($curlHandle);
        $mainProcessor->getLogger()->debug(sprintf('Loading headers from "%s" finished.', $url));

        $contentType = '';
        $headerLines = explode("\r\n", $result);
        foreach ($headerLines as $line) {
            if (str_starts_with(strtolower($line), 'content-type:')) {
                $contentType = trim(substr($line, strpos($line, ':') + 1));
            }
        }

        $mainProcessor->getLogger()->debug(sprintf('Content type of "%s" is "%s".', $url, $contentType));

        return $contentType !== '' && in_array($contentType, $options['accepable_content_types'] ?? []);
    }

    protected function prepareCurlRequest($url, $options)
    {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_HEADER, 0);

        if ($options['user_agent'] ?? null) {
            curl_setopt($curlHandle, CURLOPT_USERAGENT, $options['user_agent'] ?? '-');
        }

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, -1);
        curl_setopt($curlHandle, CURLOPT_VERBOSE, false);

        if ($options['verify_ssl'] ?? true) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        }

        return $curlHandle;
    }

    public function writeToFile($curlHandle, $data)
    {
        return fwrite($this->destinationFileHandle, $data);
    }
}