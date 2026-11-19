<?php
/** @var array<string, mixed> $block */
$perRow = max(1, (int) ($block['per_row'] ?? 4));
?>
<section class="doc-block">
  <?php if (!empty($block['title'])): ?>
    <h2 class="doc-block-title"><?= e((string) $block['title']) ?></h2>
  <?php endif; ?>
  <div class="doc-summary" style="--cards:<?= $perRow ?>">
    <?php foreach ($block['cards'] ?? [] as $card): ?>
      <div class="doc-card">
        <span class="doc-card-label"><?= e((string) ($card['label'] ?? '')) ?></span>
        <span class="doc-card-value"<?= !empty($card['numeric']) ? ' dir="ltr"' : '' ?>><?= e((string) ($card['value'] ?? '')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
