<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoUnitFoundException extends ApiException
{
    protected $errorCode = 'no_unit_found';
    protected $message = 'No unit found.';
    protected int $httpStatusCode = 404;
}
