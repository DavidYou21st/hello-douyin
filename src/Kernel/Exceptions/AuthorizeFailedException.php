<?php

namespace HelloDouYin\Kernel\Exceptions;

class AuthorizeFailedException extends Exception
{
    public array $body;

    public function __construct(string $message, mixed $body)
    {
        parent::__construct($message, -1);

        $this->body = (array) $body;
    }
}
