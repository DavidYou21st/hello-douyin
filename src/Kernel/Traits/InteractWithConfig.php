<?php

declare(strict_types=1);

namespace HelloDouYin\Kernel\Traits;

use HelloDouYin\Kernel\Config;
use HelloDouYin\Kernel\Contracts\Config as ConfigInterface;
use HelloDouYin\Kernel\Exceptions\InvalidArgumentException;

use function is_array;

trait InteractWithConfig
{
    protected ConfigInterface $config;

    /**
     * @param  array<string,mixed>|ConfigInterface  $config
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array|ConfigInterface $config)
    {
        $this->config = is_array($config) ? new Config($config) : $config;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function setConfig(ConfigInterface $config): static
    {
        $this->config = $config;

        return $this;
    }
}
