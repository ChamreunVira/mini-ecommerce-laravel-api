<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource    return await http.post('/product', buildProductFormData(payload), MULTIPART_CONFIG); into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'method' => $this->method,
            'order_number' => $this->order?->order_number,
            'status' => $this->status,
            'amount' => (string) $this->amount,
            'currency' => $this->currency ?? "USD",
            'updated_at' => $this->updated_at
        ];
    }
}
