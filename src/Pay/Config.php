<?php

declare(strict_types=1);

namespace HelloDouYin\Pay;

class Config extends \HelloDouYin\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected array $requiredKeys = [
        'mch_id',
        'secret_key',
        'private_key',
        'certificate',
    ];
}
