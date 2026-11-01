<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaginationResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const SORTABLE_COLUMNS = ['created_at', 'total', 'status', 'order_number'];

    /**
     * Statuses in which an order has already taken its items out of stock.
     * Adjust the case names to match your OrderStatus enum.
     */
    private const STOCK_HOLDING_STATUSES = [
        OrderStatus::comfirmed,
        OrderStatus::shipped,
        OrderStatus::paid,
    ];

    private float $shippingFee = 0.00;

    /**
     * Display a listing of all orders (admin).
     */
    public function index(Request $request): JsonResponse
    {
        [$sortBy, $direction, $limit] = $this->validateListingOptions($request);

        $orders = Order::query()
            ->with(['address', 'items', 'payment'])
            ->orderBy($sortBy, $direction)
            ->paginate($limit);

        return $this->orderListResponse($orders);
    }

    public function userOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) abort(401, 'Unauthenticated.');

        [$sortBy, $direction, $limit] = $this->validateListingOptions($request);

        $orders = $user->orders()
            ->with(['address', 'items', 'payment'])
            ->orderBy($sortBy, $direction)
            ->paginate($limit);

        return $this->orderListResponse($orders);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(401, 'Unauthenticated.');

        $data = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $user->id),
            ],
        ]);

        $order = DB::transaction(function () use ($user, $data): Order {
            $cart = $user->cart()->lockForUpdate()->first();
            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $cartItems = $cart->items()->with('product')->get();
            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;

                if (! $product) {
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is unavailable.']);
                }

                if ($product->quantity < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => sprintf('Only %d left in stock for "%s".', $product->quantity, $product->name),
                    ]);
                }

                $subtotal += $cartItem->quantity * $product->price;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $data['address_id'],
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::pending->value,
                'subtotal' => $subtotal,
                'shipping_fee' => $this->shippingFee,
                'total' => $subtotal + $this->shippingFee,
            ]);

            foreach ($cartItems as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product->id,
                    'product_name' => $cartItem->product->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->product->price,
                    'subtotal' => $cartItem->product->price * $cartItem->quantity,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        return ApiResponse::success([
            'id' => $order->id,
            'status' => $order->status,
            'currency' => 'USD',
            'ammount_due' => $order->total,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        if ($request->user()->id !== $order->user_id && $request->user()->role !== 'admin') {
            abort(403, 'You cannot view this order.');
        }

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'address:id,recipient_name,phone,address_line,city', 'payment']))
        );
    }

    public function update(Request $request, Order $order) {}

    public function destroy(Order $order)
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->syncStockWithStatusChange($lockedOrder, OrderStatus::cancelled);
            $lockedOrder->delete();
        });

        return ApiResponse::success('Order deleted successfully.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(OrderStatus::class)],
        ]);

        $newStatus = OrderStatus::from($data['status']);

        $updatedOrder = DB::transaction(function () use ($order, $newStatus): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->syncStockWithStatusChange($lockedOrder, $newStatus);

            $lockedOrder->status = $newStatus->value;
            $lockedOrder->save();

            return $lockedOrder;
        });

        return ApiResponse::success(new OrderResource($updatedOrder->load(['address', 'items', 'payment'])));
    }

    private function syncStockWithStatusChange(Order $order, OrderStatus $newStatus): void
    {
        $previousStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);

        $wasHoldingStock = in_array($previousStatus, self::STOCK_HOLDING_STATUSES, true);
        $willHoldStock = in_array($newStatus, self::STOCK_HOLDING_STATUSES, true);

        if (! $wasHoldingStock && $willHoldStock) {
            $this->deductStock($order);
        } elseif ($wasHoldingStock && ! $willHoldStock) {
            $this->restoreStock($order);
        }
    }

    private function deductStock(Order $order): void
    {
        foreach ($this->getQuantityByProductId($order) as $productId => $quantity) {
            $affectedRows = Product::query()
                ->whereKey($productId)
                ->where('quantity', '>=', $quantity)
                ->decrement('quantity', $quantity);

            if ($affectedRows === 0) {
                $productName = $order->items->firstWhere('product_id', $productId)?->product_name;
                $availableQuantity = (int) Product::query()->whereKey($productId)->value('quantity');

                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Not enough stock for "%s". Required: %d, available: %d.',
                        $productName,
                        $quantity,
                        $availableQuantity,
                    ),
                ]);
            }
        }
    }

    private function restoreStock(Order $order): void
    {
        foreach ($this->getQuantityByProductId($order) as $productId => $quantity) {
            Product::query()->whereKey($productId)->increment('quantity', $quantity);
        }
    }

    private function getQuantityByProductId(Order $order): Collection
    {
        $order->loadMissing('items');

        return $order->items
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->map(fn(Collection $items): int => (int) $items->sum('quantity'))
            ->sortKeys();
    }

    private function validateListingOptions(Request $request): array
    {
        $validated = $request->validate([
            'sort_by' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            $validated['sort_by'] ?? 'created_at',
            $validated['direction'] ?? 'asc',
            (int) ($validated['limit'] ?? 10),
        ];
    }

    private function orderListResponse($orders): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'meta' => new PaginationResource($orders),
            'timestamp' => date('Ymd'),
        ]);
    }

    private function generateOrderNumber(): string
    {
        return '#ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
