<?php

namespace App\Http\Controllers;

use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'sometimes|integer|min:1'
        ]);

        $quantity = $data['quantity'] ?? 1;

        DB::transaction(function () use ($request, $data, $quantity) {

            $cart = $request->user()->cart()->firstOrCreate([]);

            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            Product::query()->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();

            $item = $cart->cartItems()->where('product_id', $data['product_id'])->firstOrFail();

            if ($item) {
                $item->increment('quantity', $quantity);
            } else {
                $cart->cartItems()->create([
                    'product_id' => $data['product_id'],
                    'quantity' => $quantity
                ]);
            }

            dd(["Items" => $item->load('')]);
        }, 1);
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
