<?php
/**
 * The one document layout every NIZAM document is composed into.
 *
 * @var \App\Services\Documents\Document $document
 * @var \App\Services\Documents\DocumentIdentity $identity
 * @var array<int, array<int, array<string, mixed>>> $pages
 * @var int $totalPages
 * @var bool $forExport  inline CSS/fonts (used by the PDF/PNG pipeline)
 * @var bool $embed      framed inside the in-app preview modal
 * @var array{signatures?:bool, watermark?:bool, compact?:bool} $options
 * @var list<array<string,mixed>> $signatories resolved authorized signatories
 * @var list<string> $signatureFonts typefaces used by those signatories
 */
$isLandscape = $document->isLandscape();
$pageLabel = static fn (int $page): string => __('document.page_of', ['page' => (string) $page, 'total' => (string) $totalPages]);
$signatories = $signatories ?? [];
$signatureFonts = $signatureFonts ?? [];
$showSignatures = (bool) ($options['signatures'] ?? $identity->showSignatures);
$showWatermark = (bool) ($options['watermark'] ?? $identity->showWatermark);
$compact = (bool) ($options['compact'] ?? false);
$bodyClass = 'doc-body-shell'
    . ($showSignatures ? '' : ' doc-hide-signatures')
    . ($showWatermark ? '' : ' doc-hide-watermark')
    . ($compact ? ' doc-compact' : '')
    . ($embed ? ' doc-embedded' : '');
?>
<!DOCTYPE html>
<html lang="<?= e($identity->locale) ?>" dir="<?= e($identity->direction()) ?>" class="<?= $isLandscape ? 'doc-landscape' : 'doc-portrait' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="light only">
<meta name="generator" content="NIZAM Document Engine">
<title><?= e($document->title) ?><?= $identity->yearLabel !== '' ? ' — ' . e($identity->yearLabel) : '' ?></title>
<?php if ($forExport): ?>
<style><?= \App\Services\Documents\DocumentAssets::documentCss(true) ?></style>
<?php else: ?>
<link rel="stylesheet" href="<?= e(\App\Services\Documents\DocumentAssets::stylesheetHref()) ?>">
<?php endif; ?>
<style>@page { size: <?= $isLandscape ? '297mm 210mm' : '210mm 297mm' ?>; margin: 0; }</style>
<?php if ($identity->logoDataUri !== null): ?>
<?php /* The seal is declared once as a CSS background rather than repeated as
         an <img data: URI> in every page header: on a fifty-page register that
         repetition alone made the document several megabytes larger than the
         same document needs to be. */ ?>
<style>.doc-logo{background-image:url("<?= e($identity->logoDataUri) ?>");background-repeat:no-repeat;background-position:center;background-size:contain}</style>
<?php endif; ?>
<?php if ($signatureFonts !== []): ?>
<style><?= \App\Services\Documents\DocumentAssets::signatureFontCss($forExport, $signatureFonts) ?></style>
<?php endif; ?>
<?php
/* Signature and stamp images are also declared once per role, not per page.
   Each is a data: URI on the export path, and the footer only carries a
   small empty <span> that the class paints. */
foreach ($signatories as $signatory):
    $slug = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $signatory['role']));
    if ($slug === '') {
        continue;
    }
    if ($signatory['signature'] !== null):
?>
<style>.doc-signature-image[data-role="<?= e($slug) ?>"]{background-image:url("<?= e($signatory['signature']) ?>")}</style>
<?php endif; ?>
<?php if ($signatory['stamp'] !== null): ?>
<style>.doc-signature-stamp[data-role="<?= e($slug) ?>"]{background-image:url("<?= e($signatory['stamp']) ?>")}</style>
<?php endif; ?>
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>"
      data-document-key="<?= e($document->key) ?>"
      data-year="<?= e($identity->yearLabel) ?>"
      data-print="<?= !empty($options['print']) ? '1' : '0' ?>">

<?php foreach ($pages as $index => $blocks): ?>
  <?php $pageNumber = $index + 1; ?>
  <article class="doc-page<?= $isLandscape ? ' doc-page--landscape' : '' ?>"
           data-page="<?= $pageNumber ?>"
           aria-label="<?= e($pageLabel($pageNumber)) ?>">
    <?php /* Always rendered; visibility is a body class so the preview modal's
             watermark toggle can switch it without regenerating the document. */ ?>
    <div class="doc-watermark" aria-hidden="true"><span><?= e($identity->name()) ?></span></div>

    <?php require __DIR__ . '/partials/header.php'; ?>

    <?php if ($index === 0 || ($document->meta['repeat_title'] ?? false)): ?>
      <div class="doc-titleband">
        <div>
          <h1 class="doc-title"><?= e($document->title) ?></h1>
          <?php if ($document->subtitle !== ''): ?>
            <p class="doc-subtitle"><?= e($document->subtitle) ?></p>
          <?php endif; ?>
        </div>
        <?php if ($document->scopePairs() !== []): ?>
          <div class="doc-scope">
            <?php foreach ($document->scopePairs() as $pair): ?>
              <span class="doc-scope-pair"><span class="doc-scope-label"><?= e($pair['label']) ?>:</span> <span class="doc-scope-value"><?= e($pair['value']) ?></span></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="doc-flow">
      <?php if ($blocks === []): ?>
        <div class="doc-empty"><?= e(__('document.no_data')) ?></div>
      <?php endif; ?>
      <?php foreach ($blocks as $block): ?>
        <?php
        switch ((string) ($block['type'] ?? '')) {
            case 'summary':
                require __DIR__ . '/partials/block-summary.php';
                break;
            case 'grid':
                require __DIR__ . '/partials/block-grid.php';
                break;
            case 'fields':
                require __DIR__ . '/partials/block-fields.php';
                break;
            case 'notes':
                require __DIR__ . '/partials/block-notes.php';
                break;
            default:
                require __DIR__ . '/partials/block-table.php';
        }
        ?>
      <?php endforeach; ?>
    </div>

    <?php require __DIR__ . '/partials/footer.php'; ?>
  </article>
<?php endforeach; ?>

<?php if ($embed || !empty($options['print'])): ?>
<script src="<?= e(assetUrl('/assets/js/document-viewer.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
