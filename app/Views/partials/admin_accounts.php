<?php
$filters=$listing['filters'];$roles=['admin'=>'Administrator','catalog_manager'=>'Catalog Manager'];
$path=$staffMode?'/admin/staff':'/admin/users';$self=(int)(auth_user()['id']??0);
?>
<?php if($error): ?><p class="alert alert-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="get" action="<?= e(url($path)) ?>" class="staff-list-tools" role="search">
 <label class="field">Search <?= $staffMode?'staff':'customers' ?><input type="search" name="q" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="Name, username or email"></label>
 <label class="field">Status<select name="status"><option value="">All statuses</option><?php foreach(['active','inactive','locked',...($staffMode?[]:['pending'])] as $status): ?><option value="<?= e($status) ?>" <?= $filters['status']===$status?'selected':'' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
 <?php if($staffMode): ?><label class="field">Role<select name="role"><option value="">All staff roles</option><?php foreach($roles as $role=>$label): ?><option value="<?= e($role) ?>" <?= $filters['role']===$role?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><?php endif; ?>
 <div class="admin-account-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="<?= e(url($path)) ?>">Clear</a></div>
</form>
<p><?= (int)$listing['total'] ?> matching accounts</p>
<?php if(!$listing['items']): ?><p class="empty-state">No accounts match these filters.</p><?php else: ?>
<div class="order-table-wrap admin-accounts-table <?= $staffMode?'admin-staff-table':'' ?>" tabindex="0" role="region" aria-label="Scrollable account list"><table><thead><tr><th scope="col">Account</th><th scope="col">Email</th><?php if($staffMode): ?><th scope="col">Role</th><?php endif; ?><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach($listing['items'] as $item): $isSelf=(int)$item['id']===$self;$locked=\App\Models\User::isLocked($item); ?>
<tr><td><strong><?= e($item['full_name']) ?></strong><br><span class="muted small"><?= e($item['username']) ?><?= $isSelf?' (you)':'' ?></span></td><td><?= e($item['email']) ?></td>
<?php if($staffMode): ?><td><?php if($isSelf): ?><?= e($roles[$item['role']]) ?><?php else: ?>
<form method="post" action="<?= e(url('/admin/staff/role')) ?>" class="admin-account-action">
 <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="context" value="staff"><input type="hidden" name="expected" value="<?= e($item['role']) ?>">
 <label class="sr-only" for="role-<?= (int)$item['id'] ?>">Role for <?= e($item['username']) ?></label>
 <select id="role-<?= (int)$item['id'] ?>" name="role"><?php foreach($roles as $role=>$label): ?><option value="<?= e($role) ?>" <?= $item['role']===$role?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
 <button class="btn btn-secondary btn-sm" type="submit">Save role</button>
</form><?php endif; ?></td><?php endif; ?>
<td><?= staff_status($locked?'Locked':$item['status']) ?><?= $locked&&$item['status']!=='active'?' · '.e(ucfirst($item['status'])):'' ?></td>
<td><div class="admin-account-actions"><?php if($isSelf): ?><span class="muted small">Your current account</span><?php else: ?>
 <?php if($locked && is_string($item['locked_until'])): ?><form method="post" action="<?= e(url('/admin/users/unlock')) ?>">
 <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="context" value="<?= $staffMode?'staff':'users' ?>"><input type="hidden" name="expected_lock" value="<?= e($item['locked_until']) ?>">
 <button class="btn btn-secondary btn-sm" type="submit">Unlock <span class="sr-only"><?= e($item['username']) ?></span></button></form><?php endif; ?>
 <?php if(in_array($item['status'],['active','inactive'],true)): ?><form method="post" action="<?= e(url('/admin/users/status')) ?>">
 <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="context" value="<?= $staffMode?'staff':'users' ?>"><input type="hidden" name="expected" value="<?= e($item['status']) ?>"><input type="hidden" name="status" value="<?= $item['status']==='active'?'inactive':'active' ?>">
 <button class="btn btn-secondary btn-sm" type="submit"><?= $item['status']==='active'?'Deactivate':'Activate' ?> <span class="sr-only"><?= e($item['username']) ?></span></button></form><?php endif; ?>
<?php endif; ?></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?= pagination_nav((int)$listing['page'],(int)$listing['pages'],static fn(int $n):string=>url($path.'?'.http_build_query($filters+['page'=>$n])),($staffMode?'Staff':'Customer').' account pages') ?>
