<?php

namespace App\Http\Controllers;

use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Models\User;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            throw new AuthenticationException("User is unauthentication.");
        }

        return ApiResponse::success($user);
    }
}
