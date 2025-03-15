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

date_default_timezone_set('UTC');

class STSToken implements RefreshableAccessTokenInterface
{
    protected HttpClientInterface $httpClient;

    protected CacheInterface $cache;

    const CACHE_KEY_PREFIX = 'douyin_vegame';

    public function __construct(
        protected AccountInterface $account,
        ?CacheInterface            $cache = null,
        ?HttpClientInterface       $httpClient = null,
        protected ?int             $expire = 300,
        protected ?string          $key = null,
    )
    {
        $this->httpClient = $httpClient ?? (
        HttpClient::create(['base_uri' => 'https://open.volcengineapi.com'])
        );
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'hello_douyin', defaultLifetime: 1500));
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

    public function getCredential()
    {
        return [
            "access_key" => $this->account->getAK(),
            "secret_key" => $sk,
            "service" => "veGame",
            "region" => "cn-north-1"
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
    public function getAccessToken(): string
    {
        $response = $this->httpClient->request('GET', '/', [
            'query' => [
                'Action' => 'STSToken',
                'Version' => $this->account->getVersion(),
                'Ak' => $this->account->getAK(),
                'Sk' => $this->account->getSK(),
                'Expire' => $this->expire,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ])->toArray(false);

        if (empty($response['Token'])) {
            throw new HttpException('Failed to get sts_token: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($this->getKey(), $response['Token'], strtotime($response['expires_in']) - time());

        return $response['Token'];
    }
}
