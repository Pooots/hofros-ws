<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class BookingValidationException extends ApiException
{
    protected $errorCode = 'booking_validation_error';
    protected $message = 'Booking is not valid.';
    protected int $httpStatusCode = 422;
}
