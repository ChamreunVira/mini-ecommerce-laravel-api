<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{

    private $shippingFee = 0.00;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::orderBy('created_at', 'desc')->paginate(10);
        return ApiResponse::success($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) abort(401, "Unauthentication.");

        $data = $request->validate([
            'address_id' => 'required|integer|exists:addresses,id',
        ]);

        $order = DB::transaction(function () use ($user, $data) {
            $cart = $user->cart()->lockForUpdate()->first();
            if (!$cart) {
                throw ValidationException::withMessages(['cart' => "Your cart is empty."]);
            }

            $items = $cart->items()->with('product')->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => "Your cart is empty."]);
            }

            $subtotal = 0;

            foreach ($items as $item) {
                if (! $item->product) {
                    throw new ValidationException(['cart' => 'A product in your cart is unavailable.']);
                }
                $subtotal += $item->quantity * $item->product->price;
            }

            $total = $subtotal + $this->shippingFee;

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $data['address_id'],
                'order_number' => self::generateOrderNumber(),
                'status' => OrderStatus::pending->value,
                'subtotal' => $subtotal,
                'shipping_fee' => $this->shippingFee,
                'total' => $total,
            ]);

            //save snapshort order-item
            foreach ($items as $item) {

                $subtotal = $item->product->price * $item->quantity;

                $order->items()->create([
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                    'subtotal' => $subtotal,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        return ApiResponse::success($order->load(['user:id,name,email', 'address:id,recipient_name,phone,address_line,city']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $order = Order::with(['items', 'user', 'address:id,recipient_name,phone,address_line,city'])->get();
        return ApiResponse::success($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();
        return ApiResponse::success('Order deleted successfully.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $status = $request->input('status');
        if (!$status . contains(OrderStatus::all())) throw new \Exception('Order status must be follow busenues rule.');

        $order->status = $status;

        return $order;
    }
    
    private function generateOrderNumber()
    {
        return $orderNumber = "#ORD-" . date("Ymd") . "-" . str_pad(Order::count() + 1, 3, "0", STR_PAD_LEFT);
    }
}
