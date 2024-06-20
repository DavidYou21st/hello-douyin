<?php

declare(strict_types=1);

namespace HelloDouYin\MiniProgram;

use HelloDouYin\Kernel\Exceptions\RuntimeException;
use HelloDouYin\MiniProgram\Contracts\Account as AccountInterface;

class Account implements AccountInterface
{
    public function __construct(
        protected string $appId,
        protected ?string $secret,
        protected ?string $token = null,
        protected ?string $aesKey = null
    ) {
    }

    public function getAppId(): string
    {
        if ($this->appId === null) {
            throw new RuntimeException('No app_id configured.');
        }
        return $this->appId;
    }

    public function getSecret(): string
    {
        if ($this->secret === null) {
            throw new RuntimeException('No secret configured.');
        }

        return $this->secret;
    }

    public function getToken(): ?string
    {
        if ($this->token === null) {
            throw new RuntimeException('No token configured.');
        }
        return $this->token;
    }

    public function getAesKey(): ?string
    {
        return $this->aesKey;
    }
}
