<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoBookingPaymentFoundException extends ApiException
{
    protected $errorCode = 'no_booking_payment_found';
    protected $message = 'No booking payment found.';
    protected int $httpStatusCode = 404;
}
