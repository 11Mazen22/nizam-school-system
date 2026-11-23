<?php
/**
 * NIZAM ExportActionMenu.
 *
 * One consistent command bar for every printable screen: print, preview the
 * real document, and download it in every format the platform supports.
 *
 * The menu is built from ExportFormat rather than from hand-written markup, so
 * a format can never appear here and then be refused by the controller, and a
 * new format appears on every screen at once. Bulk export is offered only
 * where the document exposes a real, non-empty scope.
 *
 * @var string $docKey
 * @var array<string,string> $docParams
 * @var string|null $docLabel       optional short label override
 * @var bool|null $docBulk          force bulk availability on/off
 * @var bool|null $docCompact       render the smaller toolbar variant
 * @var array<int,string>|null $docFormats
 */
use App\Services\Documents\DocumentRegistry;
use App\Services\Documents\ExportFormat;

$docKey = $docKey ?? '';
$docParams = array_filter($docParams ?? [], static fn ($value): bool => trim((string) $value) !== '');
$docCompact = $docCompact ?? false;
$registry = new DocumentRegistry();

if ($docKey === '' || !$registry->has($docKey)) {
    return;
}

$docType = $registry->make($docKey);
$docFormats = $docFormats ?? $docType->formats();
$docBulk = $docBulk ?? ($docType->bulkScopes($docParams) !== []);
$payload = e(json_encode($docParams, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

$formats = [];
foreach ($docFormats as $format) {
    try {
        $formats[] = ExportFormat::meta((string) $format);
    } catch (Throwable) {
        // A type that lists a retired format simply does not show it; the
        // controller would refuse it anyway.
    }
}
$title = __('document.title.' . $docKey);
?>
<div class="n-export<?= $docCompact ? ' n-export--compact' : '' ?>" data-document-export
     data-doc-key="<?= e($docKey) ?>"
     data-doc-params="<?= $payload ?>"
     data-doc-title="<?= e($title) ?>"
     data-doc-bulk="<?= $docBulk ? '1' : '0' ?>"
     data-doc-formats="<?= e(implode(',', array_column($formats, 'key'))) ?>">

  <div class="n-export-shell dropdown">
    <button type="button" class="n-export-main" data-doc-action="print"
            title="<?= e(__('document.format.print_desc')) ?>">
      <?= icon('printer', 'n-icon-sm') ?>
      <span><?= e($docLabel ?? __('document.print')) ?></span>
    </button>
    <button type="button" class="n-export-toggle" data-bs-toggle="dropdown"
            data-bs-auto-close="true" aria-expanded="false" aria-haspopup="menu"
            aria-label="<?= e(__('document.options')) ?>">
      <?= icon('chevron-down', 'n-icon-sm') ?>
    </button>

    <ul class="dropdown-menu dropdown-menu-end n-export-menu">
      <li class="n-export-heading"><?= e(__('document.preview_title')) ?></li>
      <li>
        <button type="button" class="n-export-item" data-doc-action="preview">
          <span class="n-export-item-icon"><?= icon('eye', 'n-icon-sm') ?></span>
          <span class="n-export-item-copy">
            <strong><?= e(__('document.preview')) ?></strong>
            <small><?= e(__('document.preview_hint_short')) ?></small>
          </span>
        </button>
      </li>

      <li><hr class="dropdown-divider"></li>
      <li class="n-export-heading"><?= e(__('document.download_as')) ?></li>

      <?php foreach ($formats as $format): ?>
        <li>
          <button type="button" class="n-export-item" data-doc-action="<?= e($format['key']) ?>">
            <span class="n-export-item-icon"><?= icon((string) $format['icon'], 'n-icon-sm') ?></span>
            <span class="n-export-item-copy">
              <strong><?= e((string) $format['label']) ?></strong>
              <small><?= e((string) $format['description']) ?></small>
            </span>
            <b class="n-export-ext"><?= e(strtoupper((string) $format['extension'])) ?></b>
          </button>
        </li>
      <?php endforeach; ?>

      <?php if ($docBulk): ?>
        <li><hr class="dropdown-divider"></li>
        <li>
          <button type="button" class="n-export-item" data-doc-action="bulk">
            <span class="n-export-item-icon"><?= icon('archive', 'n-icon-sm') ?></span>
            <span class="n-export-item-copy">
              <strong><?= e(__('document.bulk.open')) ?></strong>
              <small><?= e(__('document.format.zip_desc')) ?></small>
            </span>
            <b class="n-export-ext">ZIP</b>
          </button>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</div>
