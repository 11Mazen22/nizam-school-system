<?php
/** @var array<string, mixed> $block */
?>
<section class="doc-block">
  <?php if (!empty($block['title'])): ?>
    <h2 class="doc-block-title"><?= e((string) $block['title']) ?></h2>
  <?php endif; ?>
  <?php if (!empty($block['text'])): ?>
    <div class="doc-notes"><?= nl2br(e((string) $block['text'])) ?></div>
  <?php endif; ?>
  <?php if (!empty($block['items'])): ?>
    <div class="doc-notes">
      <?php foreach ($block['items'] as $item): ?>
        <div>• <?= e((string) $item) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
