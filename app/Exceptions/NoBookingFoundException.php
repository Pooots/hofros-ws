<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoBookingFoundException extends ApiException
{
    protected $errorCode = 'no_booking_found';
    protected $message = 'No booking found.';
    protected int $httpStatusCode = 404;
}
