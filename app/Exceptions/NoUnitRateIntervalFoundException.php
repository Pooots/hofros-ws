<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoUnitRateIntervalFoundException extends ApiException
{
    protected $errorCode = 'no_unit_rate_interval_found';
    protected $message = 'No rate interval found.';
    protected int $httpStatusCode = 404;
}
