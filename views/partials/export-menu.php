<?php
/**
 * NIZAM ExportActionMenu.
 *
 * One consistent action bar for every printable screen: print, preview the
 * real document, and export to the formats that document actually supports
 * (read from the document registry, never guessed in the view). Bulk export
 * appears only where the document has a real bulk scope.
 *
 * @var string $docKey
 * @var array<string,string> $docParams
 * @var string|null $docLabel       optional short label override
 * @var bool|null $docBulk          force bulk availability on/off
 * @var bool|null $docCompact       render the smaller toolbar variant
 * @var array<int,string>|null $docFormats
 */
use App\Services\Documents\DocumentRegistry;

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
$buttonClass = $docCompact ? 'btn btn-sm btn-outline-secondary' : 'btn btn-primary';
?>
<div class="n-export" data-document-export
     data-doc-key="<?= e($docKey) ?>"
     data-doc-params="<?= $payload ?>"
     data-doc-bulk="<?= $docBulk ? '1' : '0' ?>"
     data-doc-formats="<?= e(implode(',', $docFormats)) ?>">

  <div class="btn-group n-export-group" role="group">
    <button type="button" class="<?= e($buttonClass) ?> n-export-main" data-doc-action="print"
            title="<?= e(__('document.print_hint')) ?>">
      <?= icon('printer', $docCompact ? 'n-icon-sm' : '') ?>
      <span><?= e($docLabel ?? __('document.export.print')) ?></span>
    </button>
    <button type="button" class="<?= e($buttonClass) ?> n-export-toggle dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="<?= e(__('document.options')) ?>">
      <span class="visually-hidden"><?= e(__('document.options')) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end n-export-menu">
      <li>
        <button type="button" class="dropdown-item" data-doc-action="preview">
          <?= icon('eye', 'n-icon-sm') ?><span><?= e(__('document.preview_title')) ?></span>
        </button>
      </li>
      <li><hr class="dropdown-divider"></li>
      <?php if (in_array('pdf', $docFormats, true)): ?>
        <li>
          <button type="button" class="dropdown-item" data-doc-action="pdf">
            <?= icon('file-text', 'n-icon-sm') ?><span><?= e(__('document.export.pdf')) ?></span>
          </button>
        </li>
      <?php endif; ?>
      <?php if (in_array('xlsx', $docFormats, true)): ?>
        <li>
          <button type="button" class="dropdown-item" data-doc-action="xlsx">
            <?= icon('grid', 'n-icon-sm') ?><span><?= e(__('document.export.excel')) ?></span>
          </button>
        </li>
      <?php endif; ?>
      <?php if (in_array('png', $docFormats, true)): ?>
        <li>
          <button type="button" class="dropdown-item" data-doc-action="png">
            <?= icon('image', 'n-icon-sm') ?><span><?= e(__('document.export.png')) ?></span>
          </button>
        </li>
      <?php endif; ?>
      <?php if ($docBulk): ?>
        <li><hr class="dropdown-divider"></li>
        <li>
          <button type="button" class="dropdown-item" data-doc-action="bulk">
            <?= icon('archive', 'n-icon-sm') ?><span><?= e(__('document.export.zip')) ?></span>
          </button>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</div>
