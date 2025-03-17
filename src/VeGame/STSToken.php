<?php

declare(strict_types=1);

namespace HelloDouYin\VeGame;

use HelloDouYin\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use HelloDouYin\Kernel\Exceptions\HttpException;
use HelloDouYin\VeGame\Contracts\Account as AccountInterface;
use JetBrains\PhpStorm\ArrayShape;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_string;
use function json_encode;
use function sprintf;

class STSToken implements RefreshableAccessTokenInterface
{
    protected HttpClientInterface $httpClient;

    protected CacheInterface $cache;

    const CACHE_KEY_PREFIX = 'douyin_vegame';

    protected string $host = 'open.volcengineapi.com';

    protected string $contentType = "application/x-www-form-urlencoded";
    public function __construct(
        protected AccountInterface $account,
        ?CacheInterface            $cache = null,
        ?HttpClientInterface       $httpClient = null,
        protected ?int             $expire = 300,
        protected ?string          $key = null,
        protected ?string          $date = null,
    )
    {
        $this->httpClient = $httpClient ?? (
        HttpClient::create(['base_uri' => "https://" . $this->host])
        );
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'hello_douyin', defaultLifetime: 1500));
        $this->getUTCDateTime();
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('%s:%s_%s:sts_token', static::CACHE_KEY_PREFIX, $this->account->getAK(), $this->account->getSK());
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @throws HttpException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());

        if ((bool)$token && is_string($token)) {
            return $token;
        }

        return $this->refresh();
    }

    /**
     * @return array<string, string>
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[ArrayShape(['access_token' => 'string'])]
    public function toQuery(): array
    {
        return ['access_token' => $this->getToken()];
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function refresh(): string
    {
        return $this->getAccessToken();
    }

    private function getCredential(): array
    {
        return [
            "access_key" => $this->account->getAK(),
            "secret_key" => $this->account->getSK(),
            "service" => "veGame",
            "region" => "cn-north-1"
        ];
    }

    private function getQuery(): array
    {
        return [
            'Action' => 'STSToken',
            'Version' => $this->account->getVersion(),
            'ak' => $this->account->getAK(),
            'sk' => $this->account->getSK(),
            'expire' => (string)$this->expire,
        ];
    }

    private function getHeaders(array $body = []): array
    {
        return [
            'Host' => $this->host,
            'Content-Type' => $this->contentType,
            'X-Date' => $this->date,
            "X-Content-Sha256" => $this->hashSha256(json_encode($body)),
            "Authorization" => $this->authorization($body),
        ];
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \HelloDouYin\Kernel\Exceptions\HttpException
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    public function getAccessToken(array $body = []): string
    {
        $response = $this->httpClient->request('GET', '/', [
            'query' => $this->getQuery(),
            'headers' => $this->getHeaders($body),
            'json' => $body,
        ])->toArray(false);

        if (empty($response['Result']['token'])) {
            throw new HttpException('Failed to get sts_token: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($this->getKey(), $response['Result']['token'], strtotime($response['Result']['expire_at']) - time());

        return $response['Result']['token'];
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
    function hmacSha256(string $key, string $content): string
    {
        return hash_hmac("sha256", $content, $key);
    }

    private function getUTCDateTime(): void
    {
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->date = $date->format('Ymd\THis\Z');
    }

    public function authorization(array $body = []): string
    {
        $credential = $this->getCredential();
        $shortDate = substr($this->date, 0, 8);
        $xContentSha256 = $this->hashSha256(json_encode($body));

        $signedHeadersStr = "host;x-date;x-content-sha256;content-type";
        $canonicalRequestStr = 'GET' . "\n" . '/' . "\n" . $this->buildParams($this->getQuery()) . "\n" .
            "host:" . $this->host . "\n" .
            "x-date:" . $this->date . "\n" .
            "x-content-sha256:" . $xContentSha256 . "\n" .
            "content-type:" . $this->contentType . "\n\n" .
            $signedHeadersStr . "\n" . $xContentSha256;

        $hashedCanonicalRequest = $this->hashSha256($canonicalRequestStr);
        $credentialScope = $shortDate . "/" . $credential["region"] . "/" . $credential["service"] . "/request";
        $signedCanonicalStr = "HMAC-SHA256\n" . $this->date . "\n" . $credentialScope . "\n" . $hashedCanonicalRequest;

        $kDate = $this->hmacSha256($this->account->getSK(), $shortDate);

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
}
