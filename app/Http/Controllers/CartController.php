<?php

namespace App\Http\Controllers;

use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\StockException;
use App\Helpers\ApiResponse;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $cart = Cart::where("user_id", $user->id)->first();

        if (empty($cart)) {
            return ApiResponse::success(['items' => []]);
        }

        $response = new CartResource($cart->load('items.product:id,name,price,image'));

        return ApiResponse::success($response);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'sometimes|integer|min:1'
        ]);

        $quantity = $data['quantity'] ?? 1;

        $cart = DB::transaction(function () use ($request, $data, $quantity) {
            $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $data['product_id'])
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $quantity;
                $cartItem->save();
            } else {
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $data['product_id'],
                    'quantity' => $quantity
                ]);
            }
            return $cart->load('items.product:id,name,image,price');
        }, 1);

        return ApiResponse::success(new CartResource($cart), "Product have add to cart successfully.", 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $user = $request->user();

        $data = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $ownsItem = $cartItem->cart()->where('user_id', $user->id)->exists();

        if ($ownsItem) {
            throw new ResourceNotFoundException("Cart not found.");
        }

        $product = Product::whereId($cartItem->product_id)->first();

        if (empty($product)) throw new ResourceNotFoundException("Product not found.");

        if ($product->quantity < 0) throw new StockException("Insuffience stock.");

        if ($product->quantity < $data['quantity']) throw new StockException("Stock is not enought.");

        $cartItem->update(['quantity' => $data['quantity']]);

        $response = $cartItem->load('product:id,name,image,price');

        return ApiResponse::success($response);
    }

    public function destroy(CartItem $cartItem)
    {
        $cartItem->product()->delete();
        return ApiResponse::success(null, "Cart item deleted successfully.");
    }

    public function clear(Request $request)
    {
        $user = $request->user();
        $cart = Cart::where("user_id", $user->id)->first();
        if (empty($cart)) {
            throw new \Exception("Cart not found.");
        }
        $cart->delete();
        return ApiResponse::success(null, "Cart deleted successfully.");
    }
}
