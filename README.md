# [HelloDouYin](https://HelloDouYin.com)

📦 一个 PHP 抖音开发 SDK。


## 环境需求

- PHP >= 8.0.2
- [Composer](https://getcomposer.org/) >= 2.0

## 安装

```bash
composer require davidyou/hello-douyin
```

## 使用示例

基本使用（以小程序服务端为例）:

```php
<?php

use HelloDouYin\MiniProgram\Application;

$config = [
    'app_id' => '5660f39249esdfxd',
    'secret' => '42f4f28f73423dfdsd072xxx',
    'aes_key' => 'opqrstuvwxyz01267EdSBCDEFG',
    'token' => 'HelloDouYin',
];

$app = new Application($config);

$server = $app->getServer();

$server->with(fn() => "您好！HelloDouYin！");

$response = $server->serve();
```

## License

MIT
