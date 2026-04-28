<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoPromoCodeFoundException extends ApiException
{
    protected $errorCode = 'no_promo_code_found';
    protected $message = 'No promo code found.';
    protected int $httpStatusCode = 404;
}
