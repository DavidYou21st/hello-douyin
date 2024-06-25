<?php

namespace HelloDouYin;

use GuzzleHttp\Exception\GuzzleException;
use HelloDouYin\Kernel\Exceptions\AuthorizeFailedException;
use HelloDouYin\Kernel\Exceptions\InvalidArgumentException;
use HelloDouYin\Kernel\OAuth;
use Psr\Http\Message\ResponseInterface;
use HelloDouYin\Kernel\Contracts;
use HelloDouYin\OpenPlatform\User;

class DouYin extends OAuth
{
    public const NAME = 'douyin';

    protected string $baseUrl = 'https://open.douyin.com/';

    protected array $scopes = ['user_info'];

    protected ?string $optionalScope = null;

    protected ?array $component = null;

    protected ?string $openid = null;

    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/platform/oauth/connect/');
    }

    public function getCodeFields(): array
    {
        return [
            'client_key' => $this->getClientId(),
            Contracts\RFC6749_ABNF_REDIRECT_URI => $this->redirectUrl,
            Contracts\RFC6749_ABNF_SCOPE => $this->formatScopes($this->scopes, $this->scopeSeparator),
            Contracts\RFC6749_ABNF_RESPONSE_TYPE => Contracts\RFC6749_ABNF_CODE,
        ];
    }

    public function withOpenid(string $openid): self
    {
        $this->openid = $openid;

        return $this;
    }

    public function withOptionalScope(string $optionalScope): self//such as optionalScope=friend_relation,1,message,0
    {
        $this->optionalScope = $optionalScope;

        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws AuthorizeFailedException
     */
    public function tokenFromCode(string $code): array
    {
        $response = $this->getTokenFromCode($code);

        return $this->normalizeAccessTokenResponse($response->getBody());
    }

    protected function buildAuthUrlFromBase(string $url): string
    {
        $query = \http_build_query($this->getCodeFields(), '', '&', $this->encodingType);

        return $url . '?' . $query;
    }


    protected function getTokenUrl(): string
    {
        return $this->baseUrl . '/oauth/access_token/';
    }

    /**
     * @throws GuzzleException
     * @throws AuthorizeFailedException
     */
    public function userFromCode(string $code): Contracts\UserInterface
    {
        $token = $this->tokenFromCode($code);

        $this->withOpenid($token['open_id']);

        $user = $this->userFromToken($token[$this->accessTokenKey]);

        return $user->setRefreshToken($token[Contracts\RFC6749_ABNF_REFRESH_TOKEN])
            ->setExpiresIn($token[Contracts\RFC6749_ABNF_EXPIRES_IN])
            ->setTokenResponse($token);
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->post($this->baseUrl . '/oauth/userinfo/', [
            'form_params' => [
                Contracts\RFC6749_ABNF_ACCESS_TOKEN => $token,
                'open_id' => $this->openid
            ],
            'headers' => ['content-type' => 'application/x-www-form-urlencoded']
        ]);
        $body = $this->fromJsonBody($response);

        return $body['data'] ?? [];
    }

    protected function mapUserToObject(array $user): Contracts\UserInterface
    {
        return new User([
            'open_id' => $user['open_id'] ?? null,
            Contracts\ABNF_NICKNAME => $user['nickname'] ?? null,
            Contracts\ABNF_AVATAR_LARGER => $user['avatar_larger'] ?? null,
            Contracts\ABNF_AVATAR => $user['avatar'] ?? null,
            Contracts\ABNF_CLIENT_KEY => $user['client_key'] ?? null,
            Contracts\ABNF_UNION_ID => $user['union_id'] ?? null,
        ]);
    }

    protected function getTokenFields(string $code): array
    {
        return [
            'client_key' => $this->getClientId(),
            Contracts\RFC6749_ABNF_CLIENT_SECRET => $this->getClientSecret(),
            Contracts\RFC6749_ABNF_CODE => $code,
            Contracts\RFC6749_ABNF_GRANT_TYPE => Contracts\RFC6749_ABNF_AUTHORATION_CODE,
        ];
    }

    /**
     * @throws GuzzleException
     */
    protected function getTokenFromCode(string $code): ResponseInterface
    {
        return $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'form_params' => $this->getTokenFields($code),
                'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
            ]
        );
    }
}