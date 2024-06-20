<?php

namespace HelloDouYin\Kernel\Contracts;

interface RefreshableAccessToken extends AccessToken
{
    public function refresh(): string;
}
