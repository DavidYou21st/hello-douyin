<?php

declare(strict_types=1);

namespace HelloDouYin\VeGame\Contracts;

interface Account
{
    public function getAK(): string;

    public function getSK(): string;

    public function getVersion(): string;
}
