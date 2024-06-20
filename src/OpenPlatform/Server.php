<?php

declare(strict_types=1);

namespace HelloDouYin\OpenPlatform;

use Closure;
use HelloDouYin\Kernel\Contracts\Server as ServerInterface;
use HelloDouYin\Kernel\Encryptor;
use HelloDouYin\Kernel\Exceptions\BadRequestException;
use HelloDouYin\Kernel\Exceptions\InvalidArgumentException;
use HelloDouYin\Kernel\Exceptions\RuntimeException;
use HelloDouYin\Kernel\HttpClient\RequestUtil;
use HelloDouYin\Kernel\ServerResponse;
use HelloDouYin\Kernel\Traits\DecryptXmlMessage;
use HelloDouYin\Kernel\Traits\InteractWithHandlers;
use HelloDouYin\Kernel\Traits\RespondXmlMessage;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function func_get_args;

class Server implements ServerInterface
{
    use DecryptXmlMessage;
    use InteractWithHandlers;
    use RespondXmlMessage;

    protected ?Closure $defaultVerifyTicketHandler = null;

    protected ServerRequestInterface $request;

    /**
     * @throws \Throwable
     */
    public function __construct(
        protected Encryptor $encryptor,
        ?ServerRequestInterface $request = null,
    ) {
        $this->request = $request ?? RequestUtil::createDefaultServerRequest();
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function serve(): ResponseInterface
    {
        if ((bool) ($str = $this->request->getQueryParams()['echostr'] ?? '')) {
            return new Response(200, [], $str);
        }

        $message = $this->getRequestMessage($this->request);

        $this->prepend($this->decryptRequestMessage());

        $response = $this->handle(new Response(200, [], 'success'), $message);

        if (! ($response instanceof ResponseInterface)) {
            $response = $this->transformToReply($response, $message, $this->encryptor);
        }

        return ServerResponse::make($response);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleAuthorized(callable $handler): static
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'authorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleUnauthorized(callable $handler): static
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'unauthorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleAuthorizeUpdated(callable $handler): static
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'updateauthorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withDefaultVerifyTicketHandler(callable $handler): void
    {
        $this->defaultVerifyTicketHandler = fn (): mixed => $handler(...func_get_args());
        $this->handleVerifyTicketRefreshed($this->defaultVerifyTicketHandler);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleVerifyTicketRefreshed(callable $handler): static
    {
        if ($this->defaultVerifyTicketHandler) {
            $this->withoutHandler($this->defaultVerifyTicketHandler);
        }

        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'component_verify_ticket' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    protected function decryptRequestMessage(): Closure
    {
        $query = $this->request->getQueryParams();

        return function (Message $message, Closure $next) use ($query): mixed {
            $message = $this->decryptMessage(
                message: $message,
                encryptor: $this->encryptor,
                signature: $query['signature'] ?? '',
                timestamp: $query['timestamp'] ?? '',
                nonce: $query['nonce'] ?? ''
            );

            return $next($message);
        };
    }

    /**
     * @throws BadRequestException
     */
    public function getRequestMessage(?ServerRequestInterface $request = null): \HelloDouYin\Kernel\Message
    {
        return Message::createFromRequest($request ?? $this->request);
    }

    /**
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function getDecryptedMessage(?ServerRequestInterface $request = null): \HelloDouYin\Kernel\Message
    {
        $request = $request ?? $this->request;
        $message = $this->getRequestMessage($request);
        $query = $request->getQueryParams();

        return $this->decryptMessage(
            message: $message,
            encryptor: $this->encryptor,
            signature: $query['signature'] ?? '',
            timestamp: $query['timestamp'] ?? '',
            nonce: $query['nonce'] ?? ''
        );
    }
}
