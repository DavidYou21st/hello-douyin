<?php

declare(strict_types=1);

namespace HelloDouYin\Pay;

use HelloDouYin\Kernel\Contracts\Config as ConfigInterface;
use HelloDouYin\Kernel\Contracts\Server as ServerInterface;
use HelloDouYin\Kernel\Support\PrivateKey;
use HelloDouYin\Kernel\Support\PublicKey;
use HelloDouYin\Kernel\Traits\InteractWithConfig;
use HelloDouYin\Kernel\Traits\InteractWithHttpClient;
use HelloDouYin\Kernel\Traits\InteractWithServerRequest;
use HelloDouYin\Pay\Contracts\Validator as ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Application implements \HelloDouYin\Pay\Contracts\Application
{
    use InteractWithConfig;
    use InteractWithHttpClient;
    use InteractWithServerRequest;

    protected ?ServerInterface $server = null;

    protected ?ValidatorInterface $validator = null;

    protected ?HttpClientInterface $client = null;

    protected ?Merchant $merchant = null;

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     */
    public function getUtils(): Utils
    {
        return new Utils($this->getMerchant());
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     */
    public function getMerchant(): Merchant
    {
        if (! $this->merchant) {
            $this->merchant = new Merchant(
                mchId: $this->config['mch_id'], 
                privateKey: new PrivateKey((string) $this->config['private_key']), 
                certificate: new PublicKey((string) $this->config['certificate']), 
                secretKey: (string) $this->config['secret_key'], 
                v2SecretKey: (string) $this->config['v2_secret_key'], 
                platformCerts: $this->config->has('platform_certs') ? (array) $this->config['platform_certs'] : [],
            );
        }

        return $this->merchant;
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     */
    public function getValidator(): ValidatorInterface
    {
        if (! $this->validator) {
            $this->validator = new Validator($this->getMerchant());
        }

        return $this->validator;
    }

    public function setValidator(ValidatorInterface $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): Server|ServerInterface
    {
        if (! $this->server) {
            $this->server = new Server(
                merchant: $this->getMerchant(),
                request: $this->getRequest(),
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function setConfig(ConfigInterface $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client ?? $this->client = (new Client(
            $this->getMerchant(),
            $this->getHttpClient(),
            (array) $this->config->get('http', [])
        ))->setPresets($this->config->all());
    }

    public function setClient(HttpClientInterface $client): static
    {
        $this->client = $client;

        return $this;
    }
}
