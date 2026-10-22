<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Payment::orderBy("created_at", "desc")->paginate(10);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'method' => 'required|string|',
        ]);

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

        $payment = Payment::create([
            'order_id' => $order->id,
            'status' => 'pending',
            'method' => $data['method'],
            'transaction_id' => self::generateTransactionId(),
            'amount' => $order->total,
        ]);
        return [
            ...$payment->toArray(),
            'qr_string' => 'sjfksfnskfnjsfn2n4324j24k2j34',
            'qr_expiry' => now()->addMinutes(5),
        ];
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
        return "#TRX-" . date('Ymd') . "-" . str_pad(Payment::count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
