<?php
declare(strict_types=1);
namespace App\Services;

/** Email-safe, self-contained HTML and plain-text counterparts; no remote assets. */
final class OrderReceipt
{
    public static function build(array $order,array $items): array
    {
        $card=($order['payment_method']??'')==='maya_sandbox';
        if($card&&empty($order['payment_verified']))throw new \InvalidArgumentException('Verified payment evidence is required.');
        if(!$items||($order['status']??'pending')==='cancelled')throw new \InvalidArgumentException('Order confirmation unavailable.');
        $e=static fn(mixed $s):string=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $money=static fn(int $c):string=>'PHP '.ShippingService::decimal($c);
        $cents=static function(mixed $v):int{
            if(!is_string($v)&&!is_int($v))throw new \InvalidArgumentException('Invalid order amount.');
            if(!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D',(string)$v,$m))throw new \InvalidArgumentException('Invalid order amount.');
            return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
        };
        $reference=OrderReference::display($order);$subtotal=0;$rows='';$lines=[];
        foreach($items as $item){
            $qty=filter_var($item['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4294967295]]);
            if($qty===false)throw new \InvalidArgumentException('Invalid order quantity.');
            $unit=$cents($item['unit_price_snapshot']);$line=$unit*$qty;$subtotal+=$line;
            $name=(string)$item['product_name_snapshot'];
            $rows.='<tr><td style="padding:18px 0;border-bottom:1px solid #e3ddd5">'.$e($name).'<br><span style="color:#756b62;font-size:13px">'.$qty.' × '.$money($unit).'</span></td><td align="right" style="padding:18px 0;border-bottom:1px solid #e3ddd5;white-space:nowrap">'.$money($line).'</td></tr>';
            $lines[]=$qty.' × '.$name.' — '.$money($line).' ('.$money($unit).' each)';
        }
        $discount=(int)($order['discount']['amount_cents']??0);$shipping=$cents($order['shipping_fee']??'0.00');$total=$cents($order['total']);
        if($discount<0||$subtotal-$discount+$shipping!==$total)throw new \InvalidArgumentException('Order totals require review.');
        $title=$card?'Your Sandbox payment receipt':'Your order is confirmed';
        $method=$card?'Debit/Credit Card':'Cash on delivery';
        $notice=$card?'Maya Sandbox payment confirmed. This test transaction did not charge real money.':'Pay when your order arrives. This is an order confirmation, not proof of payment.';
        $date=(new \DateTimeImmutable((string)$order['created_at'],new \DateTimeZone((string)env('APP_TIMEZONE','Asia/Manila'))))->format('j F Y · g:i a \U\T\C P');
        $totals=['Subtotal'=>$money($subtotal)];
        if($discount)$totals['Discount · '.($order['discount']['code_snapshot']??'Applied')]='−'.$money($discount);
        $totals['Shipping']=$money($shipping);$totals['Total']=$money($total);
        $totalRows='';foreach($totals as $key=>$value){$style=$key==='Total'?'padding:16px 0 7px;border-top:1px solid #e3ddd5;font-weight:700;font-size:18px':'padding:7px 0';$totalRows.='<tr><td style="'.$style.'">'.$e($key).'</td><td align="right" style="'.$style.';white-space:nowrap">'.$e($value).'</td></tr>';}
        $origin=rtrim((string)env('APP_URL',''),'/');$parts=parse_url($origin);$link='';
        if(is_array($parts)&&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment'])&&(($parts['scheme']??'')==='https'||(($parts['scheme']??'')==='http'&&in_array($parts['host']??'',['localhost','127.0.0.1'],true)))){
            $link=$origin.'/orders/view?'.http_build_query(['id'=>(int)$order['id']]);
        }
        $text=$title."\n".$reference."\n".$date."\n\n".implode("\n",$lines)."\n\n";
        foreach($totals as $key=>$value)$text.=$key.': '.$value."\n";
        $text.="\n".$method."\n".$notice."\n\n".($link!==''?'View your order: '.$link:'Sign in to Avela to view your order.')."\nThis email is not a tax invoice.\n";
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($title).'</title></head><body style="margin:0;background:#f6f3ee;color:#392f28;font-family:Arial,Helvetica,sans-serif"><div style="display:none;max-height:0;overflow:hidden">'.$e($title.' · '.$reference).'</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 12px"><table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#fffdfa;border:1px solid #e3ddd5;border-radius:16px"><tr><td style="padding:30px 24px"><img src="cid:avela-wordmark" width="130" height="50" alt="Avela" style="display:block;width:130px;max-width:100%;height:auto;border:0"><p style="font-size:12px;color:#756b62">Individual hair. Considered care.</p><hr style="border:0;border-top:1px solid #e3ddd5;margin:24px 0"><h1 style="font-size:27px;font-weight:500;line-height:1.25">'.$e($title).'</h1><p style="font-size:14px;line-height:1.6">'.$e($reference).'<br><span style="color:#756b62">'.$e($date).'</span></p><p style="font-size:14px">Thank you, '.$e($order['recipient_name_snapshot']??'').'.</p><table width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;line-height:1.6"><thead><tr><th align="left" style="border-bottom:1px solid #e3ddd5;padding:12px 0">Item</th><th align="right" style="border-bottom:1px solid #e3ddd5;padding:12px 0">Amount</th></tr></thead><tbody>'.$rows.'</tbody></table><table width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;margin:20px 0">'.$totalRows.'</table><div style="padding:18px;background:#f1ece5;border-radius:10px;font-size:14px;line-height:1.6"><strong>'.$e($method).'</strong><br>'.$e($notice).'</div>'.($link!==''?'<p style="margin:28px 0"><a href="'.$e($link).'" style="display:inline-block;background:#51443a;color:#ffffff;padding:15px 22px;border-radius:8px;text-decoration:none;font-size:14px">View your order</a></p>':'').'<p style="color:#756b62;font-size:12px;line-height:1.6">Sign in to view delivery updates. This email is not a tax invoice.</p></td></tr></table></td></tr></table></body></html>';
        return ['subject'=>'Avela '.$reference.($card?' · Sandbox payment receipt':' · Order confirmation'),'text'=>$text,'html'=>$html];
    }
}
