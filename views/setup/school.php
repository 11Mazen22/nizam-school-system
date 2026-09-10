<?php
/** @var string|null $error */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.school.title');
$stepNumber = 3;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <form method="post" action="/setup/school">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="name"><?= e(__('setup.school.name')) ?></label>
          <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="name_ar"><?= e(__('setup.school.name_ar')) ?></label>
          <input type="text" class="form-control" id="name_ar" name="name_ar" dir="rtl" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="address"><?= e(__('setup.school.address')) ?></label>
          <input type="text" class="form-control" id="address" name="address">
        </div>
        <div class="mb-3">
          <label class="form-label" for="phone"><?= e(__('setup.school.phone')) ?></label>
          <input type="text" class="form-control" id="phone" name="phone">
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>
