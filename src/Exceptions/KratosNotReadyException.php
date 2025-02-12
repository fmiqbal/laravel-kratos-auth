<?php

namespace Fmiqbal\KratosAuth\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class KratosNotReadyException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        if ($message === '') {
            $message = 'Ory Kratos Status Not Ready';
        }

        parent::__construct(404, $message, $previous, $headers, $code);
    }
}
