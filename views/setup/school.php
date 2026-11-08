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
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="school_type"><?= e(__('settings.profile.type')) ?></label>
            <select class="form-select" id="school_type" name="school_type">
              <option value="language_school" selected><?= e(__('settings.type.language_school')) ?></option>
              <option value="general"><?= e(__('settings.type.general')) ?></option>
              <option value="international"><?= e(__('settings.type.international')) ?></option>
              <option value="religious"><?= e(__('settings.type.religious')) ?></option>
              <option value="vocational"><?= e(__('settings.type.vocational')) ?></option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="school_structure"><?= e(__('settings.profile.structure')) ?></label>
            <select class="form-select" id="school_structure" name="school_structure">
              <option value="k12_combined" selected><?= e(__('settings.structure.k12_combined')) ?></option>
              <option value="primary_only"><?= e(__('settings.structure.primary_only')) ?></option>
              <option value="secondary_only"><?= e(__('settings.structure.secondary_only')) ?></option>
              <option value="multi_campus"><?= e(__('settings.structure.multi_campus')) ?></option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="currency"><?= e(__('settings.currency')) ?></label>
          <input type="text" class="form-control" id="currency" name="currency" value="EGP" maxlength="10">
        </div>
        <div class="mb-3">
          <label class="form-label" for="address"><?= e(__('setup.school.address')) ?></label>
          <input type="text" class="form-control" id="address" name="address">
        </div>
        <div class="mb-3">
          <label class="form-label" for="phone"><?= e(__('setup.school.phone')) ?></label>
          <input type="text" class="form-control" id="phone" name="phone">
        </div>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('arrow-forward', 'n-flip-rtl') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>
