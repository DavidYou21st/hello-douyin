<?php
/**
 * Author david you
 * Date 2025/3/15
 * Time 17:12
 */

namespace HelloDouYin\VeGame;

use HelloDouYin\Kernel\Contracts\AccessToken as AccessTokenInterface;
use HelloDouYin\Kernel\Traits\InteractWithCache;
use HelloDouYin\Kernel\Traits\InteractWithConfig;
use HelloDouYin\Kernel\Traits\InteractWithHttpClient;
use HelloDouYin\VeGame\Contracts\Account as AccountInterface;

class Application
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithHttpClient;

    protected ?AccessTokenInterface $stsToken = null;

    protected ?AccountInterface $account = null;

    /**
     * 签发临时Token
     *
     * @return AccessTokenInterface
     */
    public function getSTSToken(): AccessTokenInterface
    {
        if (!$this->stsToken) {
            $this->stsToken = new STSToken(
                account: new Account(
                    ak: (string)$this->config->get('ak'),
                    sk: (string)$this->config->get('sk'),
                    version: (string)$this->config->get('version'),
                ),
                cache: $this->getCache(),
                httpClient: $this->getHttpClient(),
            );
        }

        return $this->stsToken;
    }
}