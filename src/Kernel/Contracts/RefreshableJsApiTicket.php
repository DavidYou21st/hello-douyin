<?php

namespace HelloDouYin\Kernel\Contracts;

interface RefreshableJsApiTicket extends JsApiTicket
{
    public function refreshTicket(): string;
}
