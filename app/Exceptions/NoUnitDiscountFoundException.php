<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoUnitDiscountFoundException extends ApiException
{
    protected $errorCode = 'no_unit_discount_found';
    protected $message = 'No unit discount found.';
    protected int $httpStatusCode = 404;
}
