<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaginationResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{

    private $shippingFee = 0.00;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data  = $request->validate([
            'sort_by' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer']
        ]);

        $sortBy = $data['sort_by'] ?? "created_at";
        $direction = $data['direction'] ?? "asc";
        $limit = $data['limit'] ?? 10;

        $orders = Order::orderBy($sortBy, $direction)->paginate($limit);

        $orders->load([
            'address',
            'items',
            'payment'
        ]);

        // return $orders;
        return response()->json([
            'success' => true,
            'message' => 'Success to retreive payments.',
            'data' => OrderResource::collection($orders),
            'meta' => new PaginationResource($orders),
            'timestamp' => date('Ymd')
        ]);
    }

    public function userOrder(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(401, "Un authentication.");

        $data  = $request->validate([
            'sort_by' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer']
        ]);

        $sortBy = $data['sort_by'] ?? "created_at";
        $direction = $data['direction'] ?? "asc";
        $limit = $data['limit'] ?? 10;
    
        $orders = $user->orders()
            ->orderBy($sortBy, $direction)
            ->with(['address', 'items', 'payment'])
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Success to retreive payments.',
            'data' => OrderResource::collection($orders),
            'meta' => new PaginationResource($orders),
            'timestamp' => date('Ymd')
        ]);
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

        return ApiResponse::success([
            'id' => $order->id,
            'status' => $order->status,
            'currency' => "USD",
            'ammount_due' => $order->total
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order)
    {
        if ($request->user()->id !== $order->user_id && $request->user()->role !== 'admin') {
            abort(403, 'You cannot view this order.');
        }

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'address:id,recipient_name,phone,address_line,city', 'payment']))
        );
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
        $data = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(array_map(static fn(OrderStatus $status): string => $status->value, OrderStatus::cases())),
            ],
        ]);

        $order->status = $data['status'];
        $order->save();

        return ApiResponse::success(new OrderResource($order->load(['address', 'items', 'payment'])));
    }

    private function generateOrderNumber()
    {
        return $orderNumber = "#ORD-" . date("Ymd") . "-" . str_pad(Order::count() + 1, 3, "0", STR_PAD_LEFT);
    }
}
