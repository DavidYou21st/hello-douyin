<?php

declare(strict_types=1);

namespace HelloDouYin\Pay;

use HelloDouYin\Kernel\Exceptions\BadResponseException;
use HelloDouYin\Kernel\HttpClient\Response as HttpClientResponse;
use HelloDouYin\Pay\Contracts\Merchant as MerchantInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

class ResponseValidator implements \HelloDouYin\Pay\Contracts\ResponseValidator
{
    public function __construct(protected MerchantInterface $merchant)
    {
    }

    /**
     * @throws \HelloDouYin\Kernel\Exceptions\BadResponseException
     * @throws \HelloDouYin\Kernel\Exceptions\InvalidConfigException
     * @throws \HelloDouYin\Pay\Exceptions\InvalidSignatureException
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function validate(PsrResponse|HttpClientResponse $response): void
    {
        if ($response instanceof HttpClientResponse) {
            $response = $response->toPsrResponse();
        }

        if ($response->getStatusCode() !== 200) {
            throw new BadResponseException('Request Failed');
        }

        (new Validator($this->merchant))->validate($response);
    }
}
