<?php
/** @var array<string, mixed> $block */
$continued = !empty($block['continuation']);
?>
<section class="doc-block">
  <?php if (!empty($block['title'])): ?>
    <h2 class="doc-block-title">
      <?= e((string) $block['title']) ?>
      <?php if ($continued): ?><span class="doc-block-continued">(<?= e(__('document.continued')) ?>)</span><?php endif; ?>
    </h2>
  <?php endif; ?>
  <?php foreach ($block['sections'] ?? [] as $section): ?>
    <div class="doc-section">
      <?php if (!empty($section['title'])): ?>
        <h3 class="doc-section-title"><?= e((string) $section['title']) ?></h3>
      <?php endif; ?>
      <div class="doc-fields">
        <?php foreach ($section['fields'] ?? [] as $field): ?>
          <div class="doc-field<?= !empty($field['wide']) ? ' doc-field--wide' : '' ?>">
            <span class="doc-field-label"><?= e((string) ($field['label'] ?? '')) ?></span>
            <span class="doc-field-value<?= !empty($field['missing']) ? ' doc-field-value--missing' : '' ?>"<?= !empty($field['ltr']) ? ' dir="ltr"' : '' ?>><?= e((string) ($field['value'] ?? '')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
