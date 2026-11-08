<?php
/**
 * Shared Excel/CSV import modal (§Q "validating, atomic bulk import" --
 * the server-side importer re-validates everything; this modal is the
 * guided entry point). Overridables: $title, $action, $templateUrl, $modalId.
 */
$title ??= __("app.import");
$action ??= "";
$templateUrl ??= "";
$modalId ??= "importModal";
?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-labelledby="<?= e($modalId) ?>Label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?= e($action) ?>" method="POST" enctype="multipart/form-data" data-loading-submit>
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title d-flex align-items-center gap-2" id="<?= e($modalId) ?>Label">
            <?= icon('upload') ?>
            <span><?= e($title) ?></span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__("common.close")) ?>"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3"><?= e(__("app.import_instructions")) ?></p>
          <ol class="n-import-steps">
            <li><strong><?= e(__("app.import_step1")) ?></strong><?= e(__("app.download_template")) ?></li>
            <li><strong><?= e(__("app.import_step2")) ?></strong><?= e(__("app.select_file")) ?></li>
            <li><strong><?= e(__("app.import_step3")) ?></strong><?= e(__("app.upload")) ?></li>
          </ol>
          <?php if ($templateUrl): ?>
            <a href="<?= e($templateUrl) ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center gap-2 mb-3">
              <?= icon("download") ?>
              <span><?= e(__("app.download_template")) ?></span>
            </a>
          <?php endif; ?>
          <div class="mb-0">
            <label for="import_file" class="form-label"><?= e(__("app.select_file")) ?> (.xlsx, .csv)</label>
            <input class="form-control" type="file" id="import_file" name="import_file" accept=".xlsx, .csv" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__("common.cancel")) ?></button>
          <button type="submit" class="btn btn-primary"><?= icon("upload") ?> <?= e(__("app.upload")) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
