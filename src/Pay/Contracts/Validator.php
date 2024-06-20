<?php

declare(strict_types=1);

namespace HelloDouYin\Pay\Contracts;

use Psr\Http\Message\MessageInterface;

interface Validator
{
    /**
     * @throws \HelloDouYin\Pay\Exceptions\InvalidSignatureException if signature validate failed.
     */
    public function validate(MessageInterface $message): void;
}
