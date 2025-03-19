<?php
/**
 * Author david you
 * Date 2025/3/15
 * Time 17:12
 */

namespace HelloDouYin\VeGame;

use HelloDouYin\Kernel\Traits\InteractWithCache;
use HelloDouYin\Kernel\Traits\InteractWithConfig;
use HelloDouYin\VeGame\Traits\InteractWithClient;

class Application
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithClient;
}
