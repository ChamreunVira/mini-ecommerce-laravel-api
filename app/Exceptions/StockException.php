<?php

namespace App\Exceptions;

use App\Helpers\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StockException extends Exception
{
    protected int $status;
    protected $message;

    public function __construct(string $message = "Error", int $code = 404)
    {
        parent::__construct($message, $code);
        $this->status = $code;
        $this->message = $message;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->status, $this->message, Response::HTTP_BAD_REQUEST);
    }
}
