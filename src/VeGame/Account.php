<?php

declare(strict_types=1);

namespace HelloDouYin\VeGame;

use HelloDouYin\Kernel\Exceptions\RuntimeException;
use HelloDouYin\VeGame\Contracts\Account as AccountInterface;

class Account implements AccountInterface
{
    public function __construct(
        protected string  $ak,
        protected ?string $sk,
        protected ?string $version,
    )
    {
    }

    public function getAK(): string
    {
        if ($this->ak === null) {
            throw new RuntimeException('No AK configured.');
        }
        return $this->ak;
    }

    public function getSK(): string
    {
        if ($this->sk === null) {
            throw new RuntimeException('No SK configured.');
        }

        return $this->sk;
    }

    public function getVersion(): string
    {
        if ($this->version === null) {
            throw new RuntimeException('No Version configured.');
        }
        return $this->version;
    }
}
