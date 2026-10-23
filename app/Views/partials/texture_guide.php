<details class="texture-guide"><summary>See the texture guide <span class="menu-chevron" aria-hidden="true">⌄</span></summary>
 <p>Look at your natural pattern when dry, without styling. More than one pattern is common.</p>
 <div class="texture-guide-grid">
 <?php $patterns=['Straight'=>['M25 10 Q18 46 25 84','Mostly straight from root to tip.'],'Wavy'=>['M25 10 C2 30 48 55 25 84','Loose S-shaped bends.'],'Curly'=>['M25 10 C65 10 0 40 25 40 C65 40 0 70 25 70 C55 70 18 88 25 84','Visible loops or ringlets.'],'Coily'=>['M25 10 C65 10 0 25 25 25 C65 25 0 40 25 40 C65 40 0 55 25 55 C65 55 0 70 25 70 C65 70 0 85 25 85','Closely spaced coils or bends.']];foreach($patterns as $name=>[$path,$description]): ?>
 <figure><svg viewBox="0 0 110 100" role="img" aria-label="<?= e($name) ?> hair pattern illustration" width="220" height="200"><g fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><?php foreach([0,25,50] as $offset): ?><path d="<?= e($path) ?>" transform="translate(<?= $offset ?> 0)"/><?php endforeach; ?></g></svg><figcaption><strong><?= e($name) ?></strong><span><?= e($description) ?></span></figcaption></figure>
 <?php endforeach; ?></div><p class="muted small">These are simplified pattern illustrations. Texture alone does not determine your matches.</p>
</details>
