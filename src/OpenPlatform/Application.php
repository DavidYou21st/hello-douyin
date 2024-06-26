<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform;

use Closure;
use HelloDouYin\DouYin;
use HelloDouYin\Kernel\Contracts\AccessToken as AccessTokenInterface;
use HelloDouYin\Kernel\Contracts\ProviderInterface;
use HelloDouYin\Kernel\Contracts\Server as ServerInterface;
use HelloDouYin\Kernel\Encryptor;
use HelloDouYin\Kernel\Exceptions\BadResponseException;
use HelloDouYin\Kernel\Exceptions\HttpException;
use HelloDouYin\Kernel\HttpClient\AccessTokenAwareClient;
use HelloDouYin\Kernel\HttpClient\Response;
use HelloDouYin\Kernel\Traits\InteractWithCache;
use HelloDouYin\Kernel\Traits\InteractWithClient;
use HelloDouYin\Kernel\Traits\InteractWithConfig;
use HelloDouYin\Kernel\Traits\InteractWithHttpClient;
use HelloDouYin\Kernel\Traits\InteractWithServerRequest;
use HelloDouYin\Kernel\Exceptions\InvalidArgumentException;
use HelloDouYin\MiniProgram\Application as MiniProgramApplication;
use HelloDouYin\OpenPlatform\Contracts\Account as AccountInterface;
use HelloDouYin\OpenPlatform\Contracts\Application as ApplicationInterface;
use ReflectionException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;


use function array_merge;
use function md5;
use function sprintf;

class Application implements ApplicationInterface
{
    use InteractWithCache;
    use InteractWithClient;
    use InteractWithConfig;
    use InteractWithHttpClient;
    use InteractWithServerRequest;

    protected ?Encryptor $encryptor = null;

    protected ?ServerInterface $server = null;

    protected ?AccountInterface $account = null;

    protected ?AccessTokenInterface $accessToken = null;
    protected ?\Closure $oauthFactory = null;

    public function getAccount(): AccountInterface
    {
        if (!$this->account) {
            $this->account = new Account(
                appId: (string)$this->config->get('app_id'),
                secret: (string)$this->config->get('secret'),
                token: (string)$this->config->get('token'),
                aesKey: (string)$this->config->get('aes_key'),
            );
        }

        return $this->account;
    }

    public function setAccount(AccountInterface $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function setOAuthFactory(callable $factory): static
    {
        $this->oauthFactory = fn(Application $app): DouYin => $factory($app);

        return $this;
    }

    public function getEncryptor(): Encryptor
    {
        if (!$this->encryptor) {
            $this->encryptor = new Encryptor(
                appId: $this->getAccount()->getAppId(),
                token: $this->getAccount()->getToken(),
                aesKey: $this->getAccount()->getAesKey(),
                receiveId: $this->getAccount()->getAppId(),
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
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): Server|ServerInterface
    {
        if (!$this->server) {
            $this->server = new Server(
                encryptor: $this->getEncryptor(),
                request: $this->getRequest()
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
        if (!$this->accessToken) {
            $this->accessToken = new AccessToken(
                appId: $this->getAccount()->getAppId(),
                secret: $this->getAccount()->getSecret(),
                cache: $this->getCache(),
                httpClient: $this->getHttpClient(),
                isSandbox: $this->getAccount()->isSandbox(),
            );
        }

        return $this->accessToken;
    }

    public function getOauthClientToken(): AccessTokenInterface
    {
        if (!$this->accessToken) {
            $this->accessToken = new OauthClientToken(
                client_key: $this->getAccount()->getAppId(),
                client_secret: $this->getAccount()->getSecret(),
                cache: $this->getCache(),
                isSandbox: $this->getAccount()->isSandbox(),
            );
        }

        return $this->accessToken;
    }

    public function getOAuth(): ProviderInterface
    {
        if (!$this->oauthFactory) {
            $this->oauthFactory = fn(self $app): ProviderInterface => (new DouYin(
                [
                    'client_id' => $this->getAccount()->getAppId(),
                    'client_secret' => $this->getAccount()->getSecret(),
                    'redirect_url' => $this->config->get('oauth.redirect_url'),
                ]
            ))->scopes((array)$this->config->get('oauth.scopes', ['user_info']));
        }

        $provider = call_user_func($this->oauthFactory, $this);

        if (!$provider instanceof ProviderInterface) {
            throw new InvalidArgumentException(sprintf(
                'The factory must return a %s instance.',
                ProviderInterface::class
            ));
        }

        return $provider;
    }

    /**
     * jsb_ticket
     */
    public function jsbTicket(AccessTokenInterface $oauthClientToken): string
    {
        $response = $this->getClient()->request(
            'GET',
            'js/getticket',
            [
                'query' => [
                    'Scope' => 'js.ticket',
                ],
                'headers' => [
                    'access-token' => $oauthClientToken->getToken(),
                    'content-type' => 'application/json',
                ],
            ]
        )->toArray(false);

        if (empty($response['data']['ticket'])) {
            throw new HttpException('Failed to get ticket: ' . json_encode(
                    $response,
                    JSON_UNESCAPED_UNICODE
                ));
        }

        return $response['data']['ticket'];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws BadResponseException
     */
    public function refreshAuthorizerToken(string $authorizerAppId, string $authorizerRefreshToken): array
    {
        $response = $this->getClient()->request(
            'POST',
            'cgi-bin/component/api_authorizer_token',
            [
                'json' => [
                    'component_appid' => $this->getAccount()->getAppId(),
                    'authorizer_appid' => $authorizerAppId,
                    'authorizer_refresh_token' => $authorizerRefreshToken,
                ],
            ]
        )->toArray(false);

        if (empty($response['authorizer_access_token'])) {
            throw new HttpException('Failed to get authorizer_access_token: ' . json_encode(
                    $response,
                    JSON_UNESCAPED_UNICODE
                ));
        }

        return $response;
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws BadResponseException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getMiniProgramWithRefreshToken(
        string $appId,
        string $refreshToken,
        array  $config = []
    ): MiniProgramApplication
    {
        return $this->getMiniProgramWithAccessToken(
            $appId,
            $this->getAuthorizerAccessToken($appId, $refreshToken),
            $config
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getMiniProgramWithAccessToken(
        string $appId,
        string $accessToken,
        array  $config = []
    ): MiniProgramApplication
    {
        return $this->getMiniProgram(new AuthorizerAccessToken($appId, $accessToken), $config);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getMiniProgram(AuthorizerAccessToken $authorizerAccessToken, array $config = []): MiniProgramApplication
    {
        $app = new MiniProgramApplication(
            array_merge(
                [
                    'app_id' => $authorizerAccessToken->getAppId(),
                    'token' => $this->config->get('token'),
                    'aes_key' => $this->config->get('aes_key'),
                    'logging' => $this->config->get('logging'),
                    'http' => $this->config->get('http'),
                ],
                $config
            )
        );

        $app->setAccessToken($authorizerAccessToken);
        $app->setEncryptor($this->getEncryptor());

        return $app;
    }

    public function createClient(): AccessTokenAwareClient
    {
        return (new AccessTokenAwareClient(
            client: $this->getHttpClient(),
            accessToken: $this->getAccessToken(),
            failureJudge: fn(Response $response) => (bool)($response->toArray()['errcode'] ?? 0),
            throw: (bool)$this->config->get('http.throw', true),
        ))->setPresets($this->config->all());
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws BadResponseException|\Psr\SimpleCache\InvalidArgumentException
     */
    public function getAuthorizerAccessToken(string $appId, string $refreshToken): string
    {
        $cacheKey = sprintf('open-platform.authorizer_access_token.%s.%s', $appId, md5($refreshToken));

        /** @phpstan-ignore-next-line */
        $authorizerAccessToken = (string)$this->getCache()->get($cacheKey);

        if (!$authorizerAccessToken) {
            $response = $this->refreshAuthorizerToken($appId, $refreshToken);
            $authorizerAccessToken = (string)$response['authorizer_access_token'];
            $this->getCache()->set($cacheKey, $authorizerAccessToken, intval($response['expires_in'] ?? 7200) - 500);
        }

        return $authorizerAccessToken;
    }

    /**
     * @param bool $isSandbox
     * @return array<string, mixed>
     */
    protected function getHttpClientDefaultOptions(bool $isSandbox = false): array
    {
        $is_sandbox = (bool)$this->config->get('is_sandbox');
        return array_merge(
            $is_sandbox ? ['base_uri' => 'https://open-sandbox.douyin.com/'] :
                ['base_uri' => 'https://open.douyin.com/'],
            (array)$this->config->get('http', [])
        );
    }
}
