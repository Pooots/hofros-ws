<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class InvalidCredentialsException extends ApiException
{
    protected $errorCode = 'invalid_credentials';
    protected $message = 'Invalid credentials.';
    protected int $httpStatusCode = 422;
}
