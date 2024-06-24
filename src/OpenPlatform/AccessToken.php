<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform;

use HelloDouYin\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use HelloDouYin\Kernel\Exceptions\HttpException;
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

use function intval;
use function is_string;
use function json_encode;
use function sprintf;

class AccessToken implements RefreshableAccessTokenInterface
{
    protected HttpClientInterface $httpClient;

    protected CacheInterface $cache;

    const CACHE_KEY_PREFIX = 'douyin_open_platform';

    public function __construct(
        protected string     $appId,
        protected string     $secret,
        protected ?string    $key = null,
        ?CacheInterface      $cache = null,
        ?HttpClientInterface $httpClient = null,
        protected ?bool      $isSandbox = false,
        protected ?string    $scope = 'ma.clientToken'
    )
    {
        $this->httpClient = $httpClient ?? (
        $isSandbox ? HttpClient::create(['base_uri' => 'https://open-sandbox.douyin.com/']) :
            HttpClient::create(['base_uri' => 'https://developer.toutiao.com/'])
        );
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'hello_douyin', defaultLifetime: 1500));
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('%s.access_token.%s.%s.%s', static::CACHE_KEY_PREFIX, $this->appId, $this->secret, $this->scope);
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

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \HelloDouYin\Kernel\Exceptions\HttpException
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    public function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', 'api/apps/v2/token', [
            'json' => [
                'grant_type' => 'client_credential',
                'scope' => $this->scope,
                'appid' => $this->appId,
                'secret' => $this->secret,
            ],
        ])->toArray(false);

        if (empty($response['data']['access_token'])) {
            throw new HttpException('Failed to get access_token: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($this->getKey(), $response['data']['access_token'], intval($response['data']['expires_in']));

        return $response['data']['access_token'];
    }
}
