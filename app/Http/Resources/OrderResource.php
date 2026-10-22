<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,

            'subtotal' => (string) $this->subtotal,
            'total' => (string) $this->total,

            'created_at' => $this->created_at?->toISOString(),

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'address' => $this->whenLoaded(
                'address',
                fn() => $this->address ? [
                    'id' => $this->address->id,
                    'recipient_name' => $this->address->recipient_name,
                    'phone' => $this->address->phone,
                    'address_line' => $this->address->address_line,
                    'city' => $this->address->city,
                ] : null
            ),

            'payment' => PaymentResource::make(
                $this->whenLoaded('payment')
            ),
        ];
    }
}
