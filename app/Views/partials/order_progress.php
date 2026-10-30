<?php
$progressIcons=[
 'pending'=>'M5 4h14v17H5zM8 4V2h8v2M8 10h8M8 14h5',
 'shipped'=>'M2 6h12v12H2zM14 10h4l4 4v4h-8M5 18a2 2 0 1 0 4 0M16 18a2 2 0 1 0 4 0',
 'out_for_delivery'=>'M12 22s8-8 8-13a8 8 0 0 0-16 0c0 5 8 13 8 13ZM12 6a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
 'delivered'=>'M3 10l9-7 9 7v11H3zM8 14l3 3 5-5',
];
?>
<section class="order-journey" aria-label="Delivery progress">
 <?php if($journey['halted']): ?><p class="order-journey-stopped" role="status"><?= e($journey['message']) ?></p><?php endif; ?>
 <ol class="order-stages">
 <?php foreach($journey['stages'] as $stage): ?><li class="order-stage stage-<?= e($stage['state']) ?>" <?= $stage['state']==='current'?'aria-current="step"':'' ?>>
  <span class="order-stage-marker"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $stage['state']==='complete'?'M5 12l4 4L19 6':$progressIcons[$stage['key']] ?>"/></svg></span>
  <strong><?= e($stage['label']) ?></strong><span class="order-stage-state"><?= e(['complete'=>'Completed','current'=>'Current stage','upcoming'=>'Upcoming','unrecorded'=>'Not recorded','stopped'=>'Journey stopped'][$stage['state']]) ?></span>
  <?php if($stage['time']): ?><time datetime="<?= e(str_replace(' ','T',$stage['time'])) ?>"><?= e(date('j M, g:i a',strtotime($stage['time']))) ?></time><?php endif; ?>
 </li><?php endforeach; ?>
 </ol>
</section>
