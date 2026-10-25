<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Helpers\KHQRBakongPayment;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Log;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Payment::orderBy("created_at", "desc")->paginate(10);
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
            'method' => 'required|string',
        ]);

        $order = Order::where('id', $data['order_id'])->first();

        // if (!$data['method'] . contains(['khqr', 'cod'])) throw new \Exception('Method of payment must be container COD or KHQR.');

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
