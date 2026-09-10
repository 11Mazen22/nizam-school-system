<?php
/** @var ?string $error @var ?array $subject */
$pageTitle = $subject === null ? __('subjects.add') : __('subjects.edit');
$activeNav = 'subjects';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form method="post" action="<?= $subject === null ? '/subjects' : '/subjects/' . (int) $subject['id'] ?>">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="code"><?= e(__('subjects.code')) ?></label>
        <input type="text" class="form-control" id="code" name="code" value="<?= e($subject['code'] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name_en"><?= e(__('subjects.name_en')) ?></label>
        <input type="text" class="form-control" id="name_en" name="name_en" value="<?= e($subject['name_en'] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name_ar"><?= e(__('subjects.name_ar')) ?></label>
        <input type="text" class="form-control" id="name_ar" name="name_ar" dir="rtl" value="<?= e($subject['name_ar'] ?? '') ?>" required>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
