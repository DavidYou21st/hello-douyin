<?php

declare(strict_types=1);

namespace HelloDouYin\VeGame\Traits;

use HelloDouYin\Kernel\Contracts\Config as ConfigInterface;
use HelloDouYin\Kernel\HttpClient\AccessTokenAwareClient;
use HelloDouYin\Kernel\HttpClient\ScopingHttpClient;
use HelloDouYin\Kernel\Support\Arr;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


trait InteractWithClient
{
    protected ?HttpClientInterface $httpClient = null;
    protected ?string $action;
    protected array $options = [];
    protected string $host = 'open.volcengineapi.com';
    protected string $contentType = "application/x-www-form-urlencoded";
    protected ?string $date;
    protected ResponseInterface $response;
    protected ConfigInterface $config;

    public function getHttpClient(): HttpClientInterface
    {
        if (!$this->httpClient) {
            $this->httpClient = $this->createHttpClient();
        }

        return $this->httpClient;
    }

    public function setClient(AccessTokenAwareClient $client): static
    {
        $this->client = $client;

        return $this;
    }

    protected function createHttpClient(): HttpClientInterface
    {
        $optionsByRegexp = Arr::get($this->options, 'options_by_regexp', []);
        unset($this->options['options_by_regexp']);

        $client = HttpClient::create(['base_uri' => "https://" . $this->host]);
        if (!empty($optionsByRegexp)) {
            $client = new ScopingHttpClient($client, $optionsByRegexp);
        }

        return $client;
    }

    /**
     * @param array $options
     * @return InteractWithClient
     */
    public function setHttpClientOptions(array $options = ['body' => []]): static
    {
        $body = $options['body'] ?? [];
        $this->options = \array_merge_recursive([
            'query' => [
                'Action' => $this->action,
                'Version' => (string)$this->config->get('version'),
                'ak' => (string)$this->config->get('ak'),
                'sk' => (string)$this->config->get('sk'),
                'expire' => '600',
            ],
            'headers' => [
                'Host' => $this->host,
                'Content-Type' => $this->contentType,
                'X-Date' => $this->date,
                'X-Content-Sha256' => $this->hashSha256(json_encode($body)),
            ],
            'json' => $body,
        ], $options);

        $this->options['headers']['Authorization'] = $this->authorization($body);

        return $this;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function setUTCDate(): static
    {
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->date = $date->format('Ymd\THis\Z');
        return $this;
    }

    /**
     * sha256 hash算法
     *
     * @param string $content
     * @return string
     */
    private function hashSha256(string $content): string
    {
        return hash("sha256", $content);
    }

    /**
     * sha256 非对称加密
     *
     * @param string $key
     * @param string $content
     * @return string
     */
    private function hmacSha256(string $key, string $content): string
    {
        return hash_hmac("sha256", $content, $key);
    }

    /**
     * @param array $body
     * @return string
     */
    public function authorization(array $body = []): string
    {
        $credential = [
            "access_key" => (string)$this->config->get('ak'),
            "secret_key" => (string)$this->config->get('sk'),
            "service" => "veGame",
            "region" => "cn-north-1"
        ];

        $shortDate = substr($this->date, 0, 8);
        $xContentSha256 = $this->hashSha256(json_encode($body));

        $signedHeadersStr = "host;x-date;x-content-sha256;content-type";
        $canonicalRequestStr = 'GET' . "\n" . '/' . "\n" . $this->buildParams($this->options['query']) . "\n" .
            "host:" . $this->host . "\n" .
            "x-date:" . $this->date . "\n" .
            "x-content-sha256:" . $xContentSha256 . "\n" .
            "content-type:" . $this->contentType . "\n\n" .
            $signedHeadersStr . "\n" . $xContentSha256;

        $hashedCanonicalRequest = $this->hashSha256($canonicalRequestStr);
        $credentialScope = $shortDate . "/" . $credential["region"] . "/" . $credential["service"] . "/request";
        $signedCanonicalStr = "HMAC-SHA256\n" . $this->date . "\n" . $credentialScope . "\n" . $hashedCanonicalRequest;

        $kDate = $this->hmacSha256((string)$this->config->get('sk'), $shortDate);

        $kRegion = $this->hmacSha256(hex2bin($kDate), $credential["region"]);

        $kService = $this->hmacSha256(hex2bin($kRegion), $credential["service"]);

        $kSigning = $this->hmacSha256(hex2bin($kService), "request");

        $signature = $this->hmacSha256(hex2bin($kSigning), $signedCanonicalStr);

        return "HMAC-SHA256 Credential=" . $credential["access_key"] . "/" . $credentialScope . ", SignedHeaders=" . $signedHeadersStr . ", Signature=" . $signature;
    }


    /**
     * @param array $params
     * @return string
     */
    private function buildParams(array $params): string
    {
        $query = '';
        ksort($params);

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $query .= urlencode($key) . "=" . urlencode($v) . "&";
                }
            } else {
                $query .= urlencode($key) . "=" . urlencode($value) . "&";
            }
        }
        return rtrim($query, "&");
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function post(string $path = '/', array $options = []): ResponseInterface
    {
        return $this->setUTCDate()
            ->setHttpClientOptions($options)
            ->getHttpClient()
            ->request('POST', $path);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function get(string $path = '/', array $options = []): ResponseInterface
    {
        return $this->setUTCDate()
            ->setHttpClientOptions($options)
            ->getHttpClient()
            ->request('GET', $path, $this->options);
    }
}
