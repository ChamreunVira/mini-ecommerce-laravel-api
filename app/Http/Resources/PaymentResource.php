<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->payment_method,
            'status' => $this->status,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,

            'qr_string' => $this->qr_string,
            'qr_expiry' => $this->qr_expiry?->toISOString(),
        ];
    }
}
