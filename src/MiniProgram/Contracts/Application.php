<?php

declare(strict_types=1);

namespace HelloDouYin\MiniProgram\Contracts;

use HelloDouYin\Kernel\Contracts\AccessToken;
use HelloDouYin\Kernel\Contracts\Config;
use HelloDouYin\Kernel\Contracts\Server;
use HelloDouYin\Kernel\Encryptor;
use HelloDouYin\Kernel\HttpClient\AccessTokenAwareClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface Application
{
    public function getAccount(): Account;

    public function getEncryptor(): Encryptor;

    public function getServer(): Server;

    public function getRequest(): ServerRequestInterface;

    public function getClient(): AccessTokenAwareClient;

    public function getHttpClient(): HttpClientInterface;

    public function getConfig(): Config;

    public function getAccessToken(): AccessToken;

    public function getCache(): CacheInterface;
}
