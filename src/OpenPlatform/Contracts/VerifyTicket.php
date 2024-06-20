<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform\Contracts;

interface VerifyTicket
{
    public function getTicket(): string;
}
