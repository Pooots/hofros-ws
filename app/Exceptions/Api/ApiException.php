<?php

namespace App\Exceptions\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

abstract class ApiException extends Exception
{
    /**
     * Stable error code (snake_case) used by API clients to dispatch on errors.
     */
    protected $errorCode = 'api_error';

    /**
     * Default user-facing message for the error.
     */
    protected $message = 'An error occurred.';

    /**
     * HTTP status to return when this exception is rendered.
     */
    protected int $httpStatusCode = 400;

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? $this->message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCode(),
        ], $this->getHttpStatusCode());
    }
}
