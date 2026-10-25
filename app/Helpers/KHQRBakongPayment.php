<?php

namespace App\Helpers;

use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class KHQRBakongPayment
{

    public static function generate($amount, $order_number)
    {

        $individualInfo = new IndividualInfo(
            bakongAccountID: env('BAKONG_ACCOUNT_ID'),
            merchantName: env('BAKONG_MERCHANT_NAME'),
            merchantCity: 'Phnom Penh',
            storeLabel: "vSt4re KH",
            mobileNumber: env('BAKONG_MOBILE_NUMBER'),
            billNumber: $order_number,
            purposeOfTransaction: 'Purchase product.',
            currency: KHQRData::CURRENCY_USD,
            amount: $amount,
            expirationTimestamp: (string) ((int) floor(microtime(true) * 1000) + 5 * 60 * 1000)
        );

        $response = BakongKHQR::generateIndividual($individualInfo);
        if ($response->status['code'] == 0) {
            return $response->data;
        }

        throw new \Exception("Failed to generated QR code.");
    }
}
