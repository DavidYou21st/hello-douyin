<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform;

class Config extends \HelloDouYin\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected array $requiredKeys = [
        'app_id',
        'secret',
        'aes_key',
    ];
}
