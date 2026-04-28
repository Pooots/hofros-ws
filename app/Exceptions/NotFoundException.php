<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NotFoundException extends ApiException
{
    protected $errorCode = 'not_found';
    protected $message = 'Not found';
    protected int $httpStatusCode = 404;

    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        parent::__construct($message ?? $this->message, $previous);
    }
}
