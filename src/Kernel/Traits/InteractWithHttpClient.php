<?php

declare(strict_types=1);

namespace HelloDouYin\Kernel\Traits;

use HelloDouYin\Kernel\HttpClient\RequestUtil;
use HelloDouYin\Kernel\HttpClient\ScopingHttpClient;
use HelloDouYin\Kernel\Support\Arr;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function property_exists;

trait InteractWithHttpClient
{
    protected ?HttpClientInterface $httpClient = null;

    public function getHttpClient(): HttpClientInterface
    {
        if (! $this->httpClient) {
            $this->httpClient = $this->createHttpClient();
        }

        return $this->httpClient;
    }

    public function setHttpClient(HttpClientInterface $httpClient): static
    {
        $this->httpClient = $httpClient;

        if ($this instanceof LoggerAwareInterface && $httpClient instanceof LoggerAwareInterface
            && property_exists($this, 'logger')
            && $this->logger) {
            $httpClient->setLogger($this->logger);
        }

        return $this;
    }

    protected function createHttpClient(): HttpClientInterface
    {
        $options = $this->getHttpClientDefaultOptions();

        $optionsByRegexp = Arr::get($options, 'options_by_regexp', []);
        unset($options['options_by_regexp']);

        $client = HttpClient::create(RequestUtil::formatDefaultOptions($options));

        if (! empty($optionsByRegexp)) {
            $client = new ScopingHttpClient($client, $optionsByRegexp);
        }

        return $client;
    }

    /**
     * @return array<string,mixed>
     */
    protected function getHttpClientDefaultOptions(): array
    {
        return [];
    }
}
