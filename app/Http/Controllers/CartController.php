<?php

namespace App\Http\Controllers;

use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            throw new ResourceNotFoundException("User not found.");
        }
        $cart = Cart::where('user_id', $user->id)->first();

        if (! $cart) {
            throw new ResourceNotFoundException("Cart is empty.");
        }

        return ApiResponse::success($cart);
    }

    public function add(Request $request) {
        
    }

    public function update(Request $request, string $productId)
    {
        $user = $request->user();
        $this->validateUser($user);
        $product = Product::find($productId);
    }

    public function destroy(Request $request, string $cartId) {}

    public function clear(Request $request)
    {
        $this->validateUser($request);
    }

    private function validateUser(mixed $user)
    {
        if (! $user) {
            throw new UnauthorizedException("User unauthentication.");
        }
    }
}
