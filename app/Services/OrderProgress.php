<?php
declare(strict_types=1);
namespace App\Services;

/** Customer projection of saved fulfillment evidence; never derives delivery from payment/time. */
final class OrderProgress
{
 public static function label(array $order):string{
  if(($order['status']??'')==='shipped'&&!empty($order['delivery_event_id']))return 'Out for delivery';
  return ['pending'=>'Order confirmed','processing'=>'Preparing your order','shipped'=>'Order shipped','delivered'=>'Order delivered','cancelled'=>'Order cancelled'][$order['status']??'']??'Order saved';
 }
 public static function journey(array $order):array{
  $status=$order['status']??'pending';$payment=$order['maya_payment']['state']??null;
  $halted=$status==='cancelled'||in_array($payment,['failed','cancelled','expired'],true);
  $recorded=['pending'=>$order['created_at']??null];
  foreach($order['events']??[] as $event)$recorded[$event['to_status']]=$event['created_at'];
  if(in_array($status,['shipped','delivered'],true))$recorded[$status]=$recorded[$status]??true;
  if(!empty($order['out_for_delivery_at']))$recorded['out_for_delivery']=$order['out_for_delivery_at'];
  $names=['pending'=>'Order confirmed','shipped'=>'Order shipped','out_for_delivery'=>'Out for delivery','delivered'=>'Order delivered'];
  $keys=array_keys($names);$current=0;
  foreach($keys as $i=>$key)if(!empty($recorded[$key]))$current=$i;
  $stages=[];
  foreach($keys as $i=>$key){
   $exists=!empty($recorded[$key]);
   $state=$halted?'stopped':($i===$current?'current':($i<$current?($exists?'complete':'unrecorded'):'upcoming'));
   $stages[]=['key'=>$key,'label'=>$names[$key],'state'=>$state,'time'=>is_string($recorded[$key]??null)?$recorded[$key]:null];
  }
  return ['halted'=>$halted,'message'=>$status==='cancelled'?'This order was cancelled. The delivery journey has stopped.':($halted?'Payment did not complete. This order is not progressing to delivery.':null),'stages'=>$stages];
 }
}
