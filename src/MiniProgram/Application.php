<?php

declare(strict_types=1);

namespace HelloDouYin\MiniProgram;

use HelloDouYin\Kernel\Contracts\AccessToken as AccessTokenInterface;
use HelloDouYin\Kernel\Contracts\Server as ServerInterface;
use HelloDouYin\Kernel\Encryptor;
use HelloDouYin\Kernel\Exceptions\InvalidConfigException;
use HelloDouYin\Kernel\HttpClient\AccessTokenAwareClient;
use HelloDouYin\Kernel\HttpClient\AccessTokenExpiredRetryStrategy;
use HelloDouYin\Kernel\HttpClient\RequestUtil;
use HelloDouYin\Kernel\HttpClient\Response;
use HelloDouYin\Kernel\Traits\InteractWithCache;
use HelloDouYin\Kernel\Traits\InteractWithClient;
use HelloDouYin\Kernel\Traits\InteractWithConfig;
use HelloDouYin\Kernel\Traits\InteractWithHttpClient;
use HelloDouYin\Kernel\Traits\InteractWithServerRequest;
use HelloDouYin\MiniProgram\Contracts\Account as AccountInterface;
use HelloDouYin\MiniProgram\Contracts\Application as ApplicationInterface;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\RetryableHttpClient;

use function array_merge;
use function is_null;
use function str_contains;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Application implements ApplicationInterface
{
    use InteractWithCache;
    use InteractWithClient;
    use InteractWithConfig;
    use InteractWithHttpClient;
    use InteractWithServerRequest;
    use LoggerAwareTrait;

    protected ?Encryptor $encryptor = null;

    protected ?ServerInterface $server = null;

    protected ?AccountInterface $account = null;

    protected ?AccessTokenInterface $accessToken = null;

    public function getAccount(): AccountInterface
    {
        if (! $this->account) {
            $this->account = new Account(
                appId: (string) $this->config->get('app_id'),
                secret: (string) $this->config->get('secret'),
                token: (string) $this->config->get('token'),
                aesKey: (string) $this->config->get('aes_key'),
            );
        }

        return $this->account;
    }

    public function setAccount(AccountInterface $account): static
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     */
    public function getEncryptor(): Encryptor
    {
        if (! $this->encryptor) {
            $token = $this->getAccount()->getToken();
            $aesKey = $this->getAccount()->getAesKey();

            if (empty($token) || empty($aesKey)) {
                throw new InvalidConfigException('token or aes_key cannot be empty.');
            }

            $this->encryptor = new Encryptor(
                appId: $this->getAccount()->getAppId(),
                token: $token,
                aesKey: $aesKey,
                receiveId: $this->getAccount()->getAppId()
            );
        }

        return $this->encryptor;
    }

    public function setEncryptor(Encryptor $encryptor): static
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): Server|ServerInterface
    {
        if (! $this->server) {
            $this->server = new Server(
                request: $this->getRequest(),
                encryptor: $this->getAccount()->getAesKey() ? $this->getEncryptor() : null
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        if (! $this->accessToken) {
            $this->accessToken = new AccessToken(
                appId: $this->getAccount()->getAppId(),
                secret: $this->getAccount()->getSecret(),
                cache: $this->getCache(),
                httpClient: $this->getHttpClient(),
                stable: $this->config->get('use_stable_access_token', false)
            );
        }

        return $this->accessToken;
    }

    public function setAccessToken(AccessTokenInterface $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    #[Pure]
    public function getUtils(): Utils
    {
        return new Utils($this);
    }

    public function createClient(): AccessTokenAwareClient
    {
        $httpClient = $this->getHttpClient();

        if ((bool) $this->config->get('http.retry', false)) {
            $httpClient = new RetryableHttpClient(
                $httpClient,
                $this->getRetryStrategy(),
                (int) $this->config->get('http.max_retries', 2)
            );
        }

        return (new AccessTokenAwareClient(
            client: $httpClient,
            accessToken: $this->getAccessToken(),
            failureJudge: fn (
                Response $response
            ) => (bool) ($response->toArray()['errcode'] ?? 0) || ! is_null($response->toArray()['error'] ?? null),
            throw: (bool) $this->config->get('http.throw', true),
        ))->setPresets($this->config->all());
    }

    public function getRetryStrategy(): AccessTokenExpiredRetryStrategy
    {
        $retryConfig = RequestUtil::mergeDefaultRetryOptions((array) $this->config->get('http.retry', []));

        return (new AccessTokenExpiredRetryStrategy($retryConfig))
            ->decideUsing(function (AsyncContext $context, ?string $responseContent): bool {
                return ! empty($responseContent)
                    && str_contains($responseContent, '42001')
                    && str_contains($responseContent, 'access_token expired');
            });
    }

    /**
     * @return array<string,mixed>
     */
    protected function getHttpClientDefaultOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://developer.toutiao.com/'],
            (array) $this->config->get('http', [])
        );
    }
}
