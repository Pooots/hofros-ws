<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoPropertyFoundException extends ApiException
{
    protected $errorCode = 'no_property_found';
    protected $message = 'No property found.';
    protected int $httpStatusCode = 404;
}
