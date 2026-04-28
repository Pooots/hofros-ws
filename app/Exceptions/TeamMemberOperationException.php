<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class TeamMemberOperationException extends ApiException
{
    protected $errorCode = 'team_member_operation_invalid';
    protected $message = 'Team member operation not allowed.';
    protected int $httpStatusCode = 422;
}
