<?php

namespace HelloDouYin\Kernel\Support;

use const OPENSSL_RAW_DATA;

use HelloDouYin\Kernel\Contracts\Aes;
use HelloDouYin\Kernel\Exceptions\InvalidArgumentException;

use function base64_decode;
use function openssl_decrypt;
use function openssl_error_string;

class AesCbc implements Aes
{
    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     */
    public static function encrypt(string $plaintext, string $key, ?string $iv = null): string
    {
        $ciphertext = \openssl_encrypt($plaintext, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, (string) $iv);

        if ($ciphertext === false) {
            throw new InvalidArgumentException(openssl_error_string() ?: 'Encrypt AES CBC error.');
        }

        return base64_encode($ciphertext);
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidArgumentException
     */
    public static function decrypt(string $ciphertext, string $key, ?string $iv = null): string
    {
        $plaintext = openssl_decrypt(
            base64_decode($ciphertext),
            'aes-128-cbc',
            $key,
            OPENSSL_RAW_DATA,
            (string) $iv
        );

        if ($plaintext === false) {
            throw new InvalidArgumentException(openssl_error_string() ?: 'Decrypt AES CBC error.');
        }

        return $plaintext;
    }
}
