<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform;

use ArrayAccess;
use HelloDouYin\Kernel\Contracts\Arrayable;
use HelloDouYin\Kernel\Contracts\Jsonable;
use HelloDouYin\Kernel\Traits\HasAttributes;
use JetBrains\PhpStorm\Pure;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Authorization implements Arrayable, ArrayAccess, Jsonable
{
    use HasAttributes;

    public function getAppId(): string
    {
        /** @phpstan-ignore-next-line */
        return (string) $this->attributes['authorization_info']['authorizer_appid'] ?? '';
    }

    #[Pure]
    public function getAccessToken(): AuthorizerAccessToken
    {
        return new AuthorizerAccessToken(
            /** @phpstan-ignore-next-line */
            $this->attributes['authorization_info']['authorizer_appid'] ?? '',

            /** @phpstan-ignore-next-line */
            $this->attributes['authorization_info']['authorizer_access_token'] ?? ''
        );
    }

    public function getRefreshToken(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->attributes['authorization_info']['authorizer_refresh_token'] ?? '';
    }
}
