<?php
$title ??= __("app.import");
$action ??= "";
$templateUrl ??= "";
$modalId ??= "importModal";
?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-labelledby="<?= e($modalId) ?>Label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?= e($action) ?>" method="POST" enctype="multipart/form-data">
        <?= \App\CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="<?= e($modalId) ?>Label"><?= e($title) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__("app.close")) ?>"></button>
        </div>
        <div class="modal-body">
          <p><?= e(__("app.import_instructions")) ?></p>
          <?php if ($templateUrl): ?>
            <p><a href="<?= e($templateUrl) ?>" class="btn btn-sm btn-outline-secondary"><?= icon("download") ?> <?= e(__("app.download_template")) ?></a></p>
          <?php endif; ?>
          <div class="mb-3">
            <label for="import_file" class="form-label"><?= e(__("app.select_file")) ?> (.xlsx, .csv)</label>
            <input class="form-control" type="file" id="import_file" name="import_file" accept=".xlsx, .csv" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__("app.cancel")) ?></button>
          <button type="submit" class="btn btn-primary"><?= icon("upload") ?> <?= e(__("app.upload")) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

