<?php

namespace HelloDouYin\Kernel\Contracts;

const ABNF_CLIENT_KEY = 'client_key';

const ABNF_NICKNAME = 'nickname';
const ABNF_AVATAR_LARGER = 'avatar_larger';

const ABNF_AVATAR = 'avatar';
const ABNF_UNION_ID = 'union_id';

const ABNF_OPEN_ID = 'open_id';


interface UserInterface
{
    public function getOpenId(): ?string;

    public function getUnionId(): ?string;

    public function getNickname(): ?string;

    public function getAvatarLarger(): ?string;

    public function getAvatar(): ?string;

    public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresIn(): ?int;

    public function getProvider(): ProviderInterface;

    public function setRefreshToken(?string $refreshToken): self;

    public function setExpiresIn(int $expiresIn): self;

    public function setTokenResponse(array $response): self;

    public function getTokenResponse(): mixed;

    public function setProvider(ProviderInterface $provider): self;

    public function setRaw(array $user): self;

    public function setAccessToken(string $token): self;
}
