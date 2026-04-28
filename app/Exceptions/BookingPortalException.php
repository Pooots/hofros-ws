<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class BookingPortalException extends ApiException
{
    protected $errorCode = 'booking_portal_error';
    protected $message = 'Booking portal operation failed.';
    protected int $httpStatusCode = 422;
}
