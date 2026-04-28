<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoUnitDateBlockFoundException extends ApiException
{
    protected $errorCode = 'no_unit_date_block_found';
    protected $message = 'No unit date block found.';
    protected int $httpStatusCode = 404;
}
