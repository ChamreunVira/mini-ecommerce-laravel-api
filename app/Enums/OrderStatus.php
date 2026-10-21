<?php

namespace App\Enums;

enum OrderStatus: string
{
    case pending = "pending";
    case processing = "processing";
    case comfirmed = "comfirmed";
    case shipped = "shipped";
    case paid = "completed";
    case cancelled = "cancelled";

    public static function all(): array
    {
        return [
            self::pending,
            self::processing,
            self::comfirmed,
            self::shipped,
            self::paid,
            self::cancelled
        ];
    }
}
