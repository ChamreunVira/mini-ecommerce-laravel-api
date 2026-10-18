<?php

namespace App\Exceptions;

use App\Helpers\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthenticationException extends Exception
{
    protected $status;
    protected $message;

    public function __construct($message = "", $code = Response::HTTP_UNAUTHORIZED)
    {
        parent::__construct($message, $code);
        $this->message = $message;
        $this->status = $code;
    }
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(null, $this->message, $this->status);
    }
}
