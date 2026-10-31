<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthenticationException;
use App\Helpers\ApiResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $field = $request->validate([
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6'
        ]);

        $user = User::create([
            'name' => $field['name'],
            'email' => $field['email'],
            'password' => $field['password']
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function login(Request $request)
    {
        $field = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $field['email'])->first();

        if (!$user || !Hash::check($field['password'], $user->password)) {
            throw new AuthenticationException("Email or Password incorect.", Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken("auth_token")->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return ApiResponse::success($user);
    }

    public function updateProfile(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|min:3|max:255',
        ]);

        $user = $request->user();
        $user->update($fields);

        return ApiResponse::success(new UserResource($user->fresh()), 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $fields = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($fields['current_password'], $user->password)) {
            return ApiResponse::error(null, 'The current password is incorrect.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update(['password' => $fields['password']]);

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout success fully.'
        ]);
    }
}
