<?php
$authentication=$kind==='auth';
$title=$authentication?'Sign-in activity':'Audit trail';
$path=$authentication?'/admin/auth-logs':'/admin/audit-logs';
$paginationUrl=static fn(int $number):string=>url($path).'?'.http_build_query(array_filter($filters,static fn($v)=>$v!=='')+['page'=>$number]);
?>
<section class="page-head"><div><h1><?= e($title) ?></h1>
<p><?= $authentication?'Sign-in and account security events.':'Business and management changes.' ?> <?= e(env('APP_TIMEZONE','Asia/Manila')) ?>.</p></div></section>
<section class="panel">
  <?php if($error): ?><p role="alert" class="field-error"><?= e($error) ?></p><?php endif; ?>
  <form method="get" action="<?= e(url($path)) ?>" class="form-stack">
    <div class="field-grid two staff-log-filter-grid">
      <label class="field">Search activity<input type="search" name="q" maxlength="100" placeholder="<?= $authentication?'Account or event':'Account, action or module' ?>" value="<?= e($filters['q']) ?>"></label>
      <label class="field">Event / action<select name="event"><option value="">All events</option><?php if($filters['event']!==''&&!in_array($filters['event'],$events,true)): ?><option value="<?= e($filters['event']) ?>" selected><?= e($filters['event']) ?></option><?php endif; ?><?php foreach($events as $event): ?><option value="<?= e($event) ?>" <?= $filters['event']===$event?'selected':'' ?>><?= e(ucwords(str_replace(['_','.'],' ',$event))) ?></option><?php endforeach; ?></select></label>
      <?php if($authentication): ?><label class="field">Result<select name="result"><option value="">All results</option><option value="success" <?= $filters['result']==='success'?'selected':'' ?>>Success</option><option value="failure" <?= $filters['result']==='failure'?'selected':'' ?>>Failure</option></select></label><?php endif; ?>
      <label class="field">From date<input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
      <label class="field">Through date<input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
      <label class="field">Sort<select name="sort"><?php foreach(['newest'=>'Newest first','oldest'=>'Oldest first','actor'=>'Account','event'=>'Event / action'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['sort']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    </div>
    <div><button class="btn btn-primary" type="submit">Apply filters</button> <a class="btn btn-secondary" href="<?= e(url($path)) ?>">Clear</a></div>
  </form>
  <p role="status"><?= (int)$total ?> record(s) · Page <?= (int)$page ?> of <?= (int)$pages ?>.</p>
  <?php if(!$records): ?><p>No records match these filters.</p><?php else: ?>
  <div class="security-table-wrap" tabindex="0" role="region" aria-label="<?= e($title) ?> table">
    <table class="security-table staff-log-table"><thead><tr><th scope="col">Date / time</th><th scope="col">Account</th><th scope="col">Event / action</th><th scope="col"><?= $authentication?'Result':'Related record' ?></th><th scope="col"><?= $authentication?'Network address':'Details' ?></th></tr></thead><tbody>
      <?php foreach($records as $record): ?><tr>
        <td><time><?= e($record['created_at']) ?></time></td><td><?= e($record['actor_name']??'System / unrecognized account') ?></td>
        <td><?= e(ucwords(str_replace(['_','.'],' ',$record[$authentication?'event':'action']))) ?></td>
        <td><?= $authentication?staff_status($record['result']):e($record['target_type'].' #'.($record['target_id']??'—')) ?></td>
        <td><?php if($authentication): ?><?= e($record['ip_address']??'—') ?><?php else: $details=trim((string)($record['details']??''));if(in_array($details,['','[]','{}'],true)): ?><span class="muted">—</span><?php else: ?><details class="staff-record-details"><summary>View details</summary><pre><?= e($details) ?></pre></details><?php endif;endif; ?></td>
      </tr><?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>
  <?= pagination_nav((int)$page,(int)$pages,$paginationUrl,'Log pages') ?>
</section>
