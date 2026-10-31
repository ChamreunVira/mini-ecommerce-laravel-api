<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Carbon\Carbon;

class OverviewController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $previousStart = $currentStart->copy()->subMonth()->startOfMonth();
        $previousEnd = $currentStart->copy()->subMonth()->endOfMonth();

        $current = $this->periodMetrics($currentStart, $currentEnd);
        $previous = $this->periodMetrics($previousStart, $previousEnd);

        $monthlyRevenue = collect(range(1, 12))->map(function (int $month) use ($now) {
            $start = $now->copy()->startOfYear()->month($month)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'month' => $start->format('M'),
                'revenue' => (float) Order::query()
                    ->where('status', '!=', OrderStatus::cancelled->value)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total'),
            ];
        })->values();

        $statusCounts = collect(OrderStatus::cases())->map(function (OrderStatus $status) {
            return [
                'status' => $status->value,
                'count' => Order::query()->where('status', $status->value)->count(),
            ];
        })->values();

        $recentOrders = Order::query()
            ->with(['address', 'items', 'payment'])
            ->latest()
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'metrics' => [
                [
                    'key' => 'revenue',
                    'title' => 'Revenue',
                    'value' => $current['revenue'],
                    'format' => 'currency',
                    'changePercent' => $this->changePercent($current['revenue'], $previous['revenue']),
                ],
                [
                    'key' => 'orders',
                    'title' => 'Orders',
                    'value' => $current['orders'],
                    'format' => 'number',
                    'changePercent' => $this->changePercent($current['orders'], $previous['orders']),
                ],
                [
                    'key' => 'customers',
                    'title' => 'Customers',
                    'value' => $current['customers'],
                    'format' => 'number',
                    'changePercent' => $this->changePercent($current['customers'], $previous['customers']),
                ],
                [
                    'key' => 'averageOrderValue',
                    'title' => 'Avg. Order Value',
                    'value' => $current['averageOrderValue'],
                    'format' => 'currency',
                    'changePercent' => $this->changePercent($current['averageOrderValue'], $previous['averageOrderValue']),
                ],
            ],
            'monthly_revenue' => $monthlyRevenue,
            'order_status_counts' => $statusCounts,
            'recent_orders' => OrderResource::collection($recentOrders),
        ]);
    }

    private function periodMetrics(Carbon $start, Carbon $end): array
    {
        $orders = Order::query()
            ->where('status', '!=', OrderStatus::cancelled->value)
            ->whereBetween('created_at', [$start, $end]);

        $orderCount = (clone $orders)->count();
        $revenue = (float) (clone $orders)->sum('total');
        return [
            'revenue' => $revenue,
            'orders' => $orderCount,
            'customers' => (clone $orders)->distinct('user_id')->count('user_id'),
            'averageOrderValue' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0,
        ];
    }

    private function changePercent(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
