<?php

namespace HelloDouYin\OpenPlatform;

use ArrayAccess;
use JsonSerializable;
use HelloDouYin\Kernel\Contracts;
use HelloDouYin\Kernel\Exceptions\Exception;

class User implements ArrayAccess, Contracts\UserInterface, JsonSerializable
{
    use Traits\HasAttributes;

    public function __construct(array $attributes, protected ?Contracts\ProviderInterface $provider = null)
    {
        $this->attributes = $attributes;
    }

    public function getOpenId(): ?string
    {
        return $this->getAttribute(Contracts\ABNF_OPEN_ID);
    }

    public function getNickname(): ?string
    {
        return $this->getAttribute(Contracts\ABNF_NICKNAME);
    }

    public function getUnionId(): ?string
    {
        return $this->getAttribute(Contracts\ABNF_UNION_ID);
    }

    public function getAvatarLarger(): ?string
    {
        return $this->getAttribute(Contracts\ABNF_AVATAR_LARGER);
    }

    public function getAvatar(): ?string
    {
        return $this->getAttribute(Contracts\ABNF_AVATAR);
    }

    public function setAccessToken(string $token): self
    {
        $this->setAttribute(Contracts\RFC6749_ABNF_ACCESS_TOKEN, $token);

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->getAttribute(Contracts\RFC6749_ABNF_ACCESS_TOKEN);
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->setAttribute(Contracts\RFC6749_ABNF_REFRESH_TOKEN, $refreshToken);

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->getAttribute(Contracts\RFC6749_ABNF_REFRESH_TOKEN);
    }

    public function setExpiresIn(int $expiresIn): self
    {
        $this->setAttribute(Contracts\RFC6749_ABNF_EXPIRES_IN, $expiresIn);

        return $this;
    }

    public function getExpiresIn(): ?int
    {
        return $this->getAttribute(Contracts\RFC6749_ABNF_EXPIRES_IN);
    }

    public function setRaw(array $user): self
    {
        $this->setAttribute('raw', $user);

        return $this;
    }

    public function getRaw(): array
    {
        return $this->getAttribute('raw', []);
    }

    public function setTokenResponse(array $response): self
    {
        $this->setAttribute('token_response', $response);

        return $this;
    }

    public function getTokenResponse(): mixed
    {
        return $this->getAttribute('token_response');
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function __serialize(): array
    {
        return $this->attributes;
    }

    public function __unserialize(array $serialized): void
    {
        $this->attributes = $serialized ?: [];
    }

    public function getProvider(): Contracts\ProviderInterface
    {
        return $this->provider ?? throw new Exception('The provider instance doesn\'t initialized correctly.');
    }

    public function setProvider(Contracts\ProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }
}
