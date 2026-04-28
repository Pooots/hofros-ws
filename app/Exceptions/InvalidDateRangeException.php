<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class InvalidDateRangeException extends ApiException
{
    protected $errorCode = 'invalid_date_range';
    protected $message = 'Date range is too large (max 400 days).';
    protected int $httpStatusCode = 422;
}
