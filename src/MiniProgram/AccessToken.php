<?php

declare(strict_types=1);

namespace HelloDouYin\MiniProgram;

use HelloDouYin\OpenPlatform\AccessToken as BaseAccessToken;

class AccessToken extends BaseAccessToken
{
    const CACHE_KEY_PREFIX = 'douyin_mini_program';
}
