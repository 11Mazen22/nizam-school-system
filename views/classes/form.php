<?php
/** @var ?string $error @var ?array $class @var array $grades */
$pageTitle = $class === null ? __('classes.add') : __('classes.edit');
$activeNav = 'classes';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form method="post" action="<?= $class === null ? '/classes' : '/classes/' . (int) $class['id'] ?>">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="grade_id"><?= e(__('classes.grade')) ?></label>
        <?php if ($class === null): ?>
          <select class="form-select" id="grade_id" name="grade_id" required>
            <?php foreach ($grades as $grade): ?>
              <option value="<?= (int) $grade['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" class="form-control" value="<?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?>" disabled>
        <?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name"><?= e(__('classes.name')) ?></label>
        <input type="text" class="form-control" id="name" name="name" value="<?= e($class['name'] ?? '') ?>" placeholder="A" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="capacity"><?= e(__('classes.capacity')) ?> (<?= e(__('common.optional')) ?>)</label>
        <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="<?= e((string) ($class['capacity'] ?? '')) ?>">
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
