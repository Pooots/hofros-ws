<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class PortalNotPublishedException extends ApiException
{
    protected $errorCode = 'portal_not_published';
    protected $message = 'This booking link is not published yet.';
    protected int $httpStatusCode = 404;
}
