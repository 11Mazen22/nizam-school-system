<?php
/**
 * Three-column institutional header: Arabic identity, seal and document
 * identity, English identity. Optional lines simply do not render when the
 * school has not configured them -- no placeholders, no empty labels.
 *
 * @var \App\Services\Documents\DocumentIdentity $identity
 */
$arabicLines = $identity->institutionalLines();
$englishLines = $identity->contactLines();
?>
<header class="doc-header">
  <div class="doc-head-right" lang="ar" dir="rtl">
    <?php foreach ($arabicLines as $line): ?>
      <div class="doc-head-line"><?= e($line) ?></div>
    <?php endforeach; ?>
    <div class="doc-head-line doc-head-line--strong"><?= e($identity->nameAr) ?></div>
  </div>

  <div class="doc-head-brand">
    <?php if ($identity->logoDataUri !== null): ?>
      <span class="doc-logo" role="img" aria-label="<?= e($identity->nameAr) ?>"></span>
    <?php endif; ?>
    <?php if ($identity->yearLabel !== ''): ?>
      <span class="doc-year-badge"><?= e($identity->yearLabel) ?></span>
    <?php endif; ?>
  </div>

  <div class="doc-head-left" lang="en" dir="ltr">
    <div class="doc-head-line doc-head-line--strong"><?= e($identity->nameEn) ?></div>
    <?php foreach ($englishLines as $line): ?>
      <div class="doc-head-line"><?= e($line) ?></div>
    <?php endforeach; ?>
  </div>
</header>
