<section class="page-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Orders</h1><p>Review orders and record their next fulfillment status.</p></div></section>
<?php if($error): ?><p class="alert alert-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="get" action="<?= e(url('/admin/orders')) ?>" class="staff-list-tools">
 <label class="field">Order or recipient<input type="search" name="q" maxlength="100" value="<?= e($history['q']) ?>"></label>
 <label class="field">Status<select name="status"><option value="">All statuses</option><?php foreach(['pending','processing','shipped','delivered','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= $history['status']===$status?'selected':'' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
 <button class="btn btn-primary" type="submit">Filter orders</button><a href="<?= e(url('/admin/orders')) ?>">Clear</a>
</form>
<p><?= (int)$history['total'] ?> matching orders</p>
<?php if(!$history['items']): ?><p class="empty-state">No orders match these filters.</p><?php else: ?>
<div class="order-table-wrap admin-order-table" tabindex="0" role="region" aria-label="Scrollable order list"><table><thead><tr><th>Order</th><th>Recipient</th><th>Placed</th><th>Total</th><th>Status</th><th>Next update</th></tr></thead><tbody>
<?php foreach($history['items'] as $item): ?><tr>
 <td><a href="<?= e(url('/admin/orders/view?id='.(int)$item['id'])) ?>"><?= e(\App\Services\OrderReference::display($item)) ?></a></td><td><?= e($item['recipient_name_snapshot']??'Not recorded') ?></td><td><?= e($item['created_at']) ?></td><td>₱<?= e(number_format((float)$item['total'],2)) ?></td><td><?= e(ucfirst($item['status'])) ?></td>
 <td><?php $next=\App\Services\OrderWorkflow::NEXT[$item['status']]??null; $canAdvance=$item['maya_state']===null||$item['maya_state']==='paid'; if($next&&$canAdvance): ?><form method="post" action="<?= e(url('/admin/orders/status')) ?>" data-confirm="Record this fulfillment update?">
 <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="expected_status" value="<?= e($item['status']) ?>"><input type="hidden" name="status" value="<?= e($next) ?>">
 <button class="btn btn-secondary btn-sm" type="submit">Mark <?= e($next) ?> <span class="sr-only">for <?= e(\App\Services\OrderReference::display($item)) ?></span></button>
 </form><?php else: ?><?= $next&&!$canAdvance?'Awaiting verified payment':'No further transition' ?><?php endif; ?>
 <?php if(in_array($item['status'],['pending','processing'],true)&&$item['payment_method']==='cod'): ?><form method="post" action="<?= e(url('/admin/orders/status')) ?>" data-confirm="Cancel this order and restore its stock?">
 <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="expected_status" value="<?= e($item['status']) ?>"><input type="hidden" name="status" value="cancelled">
 <button class="btn btn-secondary btn-sm" type="submit">Cancel &amp; restore stock <span class="sr-only">for <?= e(\App\Services\OrderReference::display($item)) ?></span></button>
 </form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<nav class="order-pagination" aria-label="Administrator order pages">
 <?php foreach(['Previous'=>$history['page']-1,'Next'=>$history['page']+1] as $label=>$page): if($page<1||$page>$history['pages'])continue; ?><a href="<?= e(url('/admin/orders?'.http_build_query(['q'=>$history['q'],'status'=>$history['status'],'page'=>$page]))) ?>"><?= e($label) ?></a><?php endforeach; ?>
 <span>Page <?= (int)$history['page'] ?> of <?= (int)$history['pages'] ?></span>
</nav>
<?php endif; ?>
<p class="muted small">Record actual updates only. Pending or processing COD orders can be cancelled when their original stock deduction is confirmed. Shipped, delivered and older unreconciled orders require separate review.</p>
