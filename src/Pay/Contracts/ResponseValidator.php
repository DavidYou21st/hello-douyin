<?php

declare(strict_types=1);

namespace HelloDouYin\Pay\Contracts;

use HelloDouYin\Kernel\HttpClient\Response;
use Psr\Http\Message\ResponseInterface;

interface ResponseValidator
{
    public function validate(ResponseInterface|Response $response): void;
}
