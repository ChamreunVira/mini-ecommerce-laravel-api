<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success($data = null, string $message = "Success", int $code = 200): JsonResponse
    {
        return response()->json([
            "success" => true,
            'status' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    public static function error($data = null, string $message = "Error", int $code = 500): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'error' => $data,
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }
}
