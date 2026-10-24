<?php
declare(strict_types=1);
namespace App\Services;

/** A payment outcome and a delivery stage are independent facts. */
final class OrderPaymentStatus
{
    public static function label(array $order): string
    {
        if(($order['payment_method']??'')==='cod')return match($order['status']??'pending'){
            'cancelled'=>'Cancelled · no payment due',
            'delivered'=>'Delivery completed · collection not recorded',
            default=>'Due on delivery',
        };
        $payment=$order['maya_payment']??$order;
        if(!empty($order['simulated_payment'])||MayaPaymentService::simulatedReference($payment))return 'Local test · no payment collected';
        $state=$payment['state']??$order['maya_state']??null;
        if($state==='paid'&&empty($payment['verified_at']))return 'Payment awaiting verification';
        return match($state){
            'paid'=>'Payment confirmed', 'failed'=>'Payment failed', 'cancelled'=>'Payment cancelled',
            'expired'=>'Checkout expired', 'review'=>'Payment awaiting review',
            'pending','initiating'=>'Awaiting payment confirmation', default=>'Payment on arrangement',
        };
    }
}
