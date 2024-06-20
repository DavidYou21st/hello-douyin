<?php

declare(strict_types=1);

namespace HelloDouYin\Pay\Contracts;

use HelloDouYin\Kernel\Support\PrivateKey;
use HelloDouYin\Kernel\Support\PublicKey;

interface Merchant
{
    public function getMerchantId(): int;

    public function getPrivateKey(): PrivateKey;

    public function getSecretKey(): string;

    public function getV2SecretKey(): ?string;

    public function getCertificate(): PublicKey;

    public function getPlatformCert(string $serial): ?PublicKey;
}
