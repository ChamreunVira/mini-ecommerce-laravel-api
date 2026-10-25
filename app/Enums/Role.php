<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Customer = 'customer';

    public static function all(): array
    {
        return [
            self::Admin,
            self::Customer
        ];
    }
}
