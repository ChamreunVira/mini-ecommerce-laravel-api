<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Helpers\KHQRBakongPayment;
use App\Http\Resources\PaginationResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Log;

class PaymentController extends Controller
{
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

        $payments = Payment::orderBy($sortBy, $direction)->paginate($limit);
        $now = Carbon::now();
        $currentStart = $now->copy()->startOfMonth();
        $previousStart = $currentStart->copy()->subMonth()->startOfMonth();
        $currentMetrics = $this->paymentMetricsBetween($currentStart, $now->copy()->endOfMonth());
        $previousMetrics = $this->paymentMetricsBetween(
            $previousStart,
            $currentStart->copy()->subMonth()->endOfMonth(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Success to retreive payments.',
            'data' => PaymentResource::collection($payments),
            'meta' => new PaginationResource($payments),
            'metrics' => [
                [
                    'key' => 'paymentsReceived',
                    'title' => 'Payments Received',
                    'value' => $currentMetrics['paid']['amount'],
                    'format' => 'currency',
                    'changePercent' => $this->changePercent($currentMetrics['paid']['amount'], $previousMetrics['paid']['amount']),
                ],
                [
                    'key' => 'pendingPayments',
                    'title' => 'Pending Payments',
                    'value' => $currentMetrics['pending']['amount'],
                    'format' => 'currency',
                    'changePercent' => $this->changePercent($currentMetrics['pending']['amount'], $previousMetrics['pending']['amount']),
                ],
                [
                    'key' => 'refundedPayments',
                    'title' => 'Refunded Payments',
                    'value' => $currentMetrics['refunded']['amount'],
                    'format' => 'currency',
                    'changePercent' => $this->changePercent($currentMetrics['refunded']['amount'], $previousMetrics['refunded']['amount']),
                ],
                [
                    'key' => 'failedPayments',
                    'title' => 'Failed Payments',
                    'value' => $currentMetrics['failed']['count'],
                    'format' => 'number',
                    'changePercent' => $this->changePercent($currentMetrics['failed']['count'], $previousMetrics['failed']['count']),
                ],
            ],
            'timestamp' => date('Ymd')
        ]);
    }

    private function paymentMetricsBetween(Carbon $start, Carbon $end): array
    {
        $summary = Payment::query()
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('payments')
                    ->groupBy('transaction_id');
            })
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(['paid', 'pending', 'refunded', 'failed'])
            ->mapWithKeys(function (string $status) use ($summary) {
                $row = $summary->get($status);

                return [$status => [
                    'count' => (int) ($row?->count ?? 0),
                    'amount' => (float) ($row?->amount ?? 0),
                ]];
            })
            ->all();
    }

    private function changePercent(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function check(string $transactionID)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionID)->first()->load("order");

            if (!$payment) throw new ResourceNotFoundException("Payment transaction not found.");

            $orderStatus = $payment->order->status;
            if ($orderStatus == OrderStatus::cancelled->value) throw new Exception("Order cancelled cann't make payment.");

            if ($payment->status == 'paid') return ApiResponse::success([
                'id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
            ]);

            $BAKONG_URL = "https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5";

            $response = Http::timeout(5)->withToken(env('BAKONG_TOKEN'))->post($BAKONG_URL, [
                'md5' => $payment->md5
            ]);

            if ($response->successful()) {
                $responseBody = $response->json();
                if ($responseBody['responseCode'] == 0) {
                    $payment->update([
                        'status' => 'paid'
                    ]);

                    $payment->order()->update([
                        'status' => OrderStatus::comfirmed->value
                    ]);

                    return ApiResponse::success([
                        'id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'status' => $payment->status,
                    ]);
                }
            }

            return ApiResponse::success(null, "Success");
        } catch (Exception $e) {
            Log::info("Something went wrong.");
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'method' => ['required', 'string', Rule::in(['cod', 'khqr'])],
        ]);

        $order = Order::where('id', $data['order_id'])->first();

        if ($order->status == OrderStatus::cancelled->value || $order->status == OrderStatus::paid->value) throw new \Exception('The payment cannot created with contain this status.');


        if ($data['method'] == 'cod') {
            $payment = Payment::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'method' => $data['method'],
                'transaction_id' => self::generateTransactionId(),
                'amount' => $order->total,
            ]);
            return $payment->load('order');
        }

        $response = KHQRBakongPayment::generate($order->total, $order->order_number);

        $payment = Payment::forceCreate([
            'order_id' => $order->id,
            'status' => 'pending',
            'method' => $data['method'],
            'transaction_id' => self::generateTransactionId(),
            'qr_string' => $response['qr'],
            'md5' => $response['md5'],
            'amount' => $order->total
        ]);

        return ApiResponse::success([
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'qr_string' => $response['qr'],
            'qr_expiry' => now()->addMinutes(5),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        return $payment->load('order');
    }

    public function updateStatus(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'failed', 'paid', 'refunded'])],
        ]);

        $payment->update(['status' => $data['status']]);

        return ApiResponse::success(new PaymentResource($payment->fresh()));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        $payment->delete();
        return ApiResponse::success(null, "Payment deleted successfully.");
    }

    private function generateTransactionId()
    {
        return "TXN-" . date('Ymd') . "-" . str_pad(Payment::count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
