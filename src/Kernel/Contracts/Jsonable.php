<?php

declare(strict_types=1);

namespace HelloDouYin\Kernel\Contracts;

interface Jsonable
{
    public function toJson(): string|false;
}
