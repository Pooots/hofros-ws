<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;

class NoTeamMemberFoundException extends ApiException
{
    protected $errorCode = 'no_team_member_found';
    protected $message = 'No team member found.';
    protected int $httpStatusCode = 404;
}
