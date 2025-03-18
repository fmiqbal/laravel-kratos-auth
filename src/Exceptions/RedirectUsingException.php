<?php

namespace Fmiqbal\KratosAuth\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class RedirectUsingException extends HttpException
{
    public function __construct($to)
    {
        parent::__construct(
            statusCode: 302,
            message: "Redirecting...",
            headers: ['Location' => $to],
        );
    }
}
