<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Models\Order;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $data = $request->validate([
            'address_id' => 'required|integer|exists:addresses,id',
        ]);

        $order = DB::transaction(function () use ($user, $data) {

        });

        $user = $request->user();
        if (! $user) abort(401, "Unauthentication.");

        //patern #0RD-20250425-001
        $orderNumber = self::generateOrderNumber();

        // $subtotal = $cart->items() . map(function ($item) {
        // return $item->quantity;
        // });
        $subtotal = 0.00;

        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $data['address_id'],
            'order_number' => $orderNumber,
            'status' => OrderStatus::pending->value,
            'subtotal' => $subtotal,
            'shipping_fee' => $this->shippingFee,
            'total' => $subtotal,
        ]);

        return ApiResponse::success($order->load(['user:id,name,email', 'address:id,recipient_name,phone,address_line,city']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();
        return ApiResponse::success('Order deleted successfully.');
    }

    private function generateOrderNumber()
    {
        return $orderNumber = "#ORD-" . date("Y-m-d") . "-" . str_pad(Order::count() + 1, 3, "0", STR_PAD_LEFT);
    }
}
