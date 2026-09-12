<?php
/** @var ?string $error @var array $years */
$pageTitle = __('promotion.title');
$activeNav = 'students';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('trending-up', 'n-icon-lg text-body-secondary') ?>
      <?= e($pageTitle) ?>
    </h1>
  </div>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form method="get" action="/students/promotion">
      <div class="mb-3">
        <label class="form-label" for="source"><?= e(__('promotion.source_year')) ?></label>
        <select class="form-select" id="source" name="source" required>
          <?php foreach ($years as $year): ?>
            <option value="<?= (int) $year['id'] ?>"><?= e($year['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="target"><?= e(__('promotion.target_year')) ?></label>
        <select class="form-select" id="target" name="target" required>
          <?php foreach ($years as $year): ?>
            <option value="<?= (int) $year['id'] ?>"><?= e($year['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
        <?= icon('arrow-forward', 'n-flip-rtl') ?>
        <span><?= e(__('promotion.start')) ?></span>
      </button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
