<?php

declare(strict_types=1);

namespace HelloDouYin\Kernel\Contracts;

use HelloDouYin\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface AccessTokenAwareHttpClient extends HttpClientInterface
{
    public function withAccessToken(AccessTokenInterface $accessToken): static;
}
