<?php
/** @var ?string $error @var ?array $school */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'profile';

require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.profile')) ?></h2>
    <p><?= e(__('settings.desc.profile')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <form method="post" action="/settings/profile" enctype="multipart/form-data" data-unsaved-guard data-loading-submit>
      <?= CsrfMiddleware::field() ?>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="name"><?= e(__('setup.school.name')) ?></label>
          <input type="text" class="form-control" id="name" name="name" value="<?= e($school['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="name_ar"><?= e(__('setup.school.name_ar')) ?></label>
          <input type="text" class="form-control" id="name_ar" name="name_ar" value="<?= e($school['name_ar'] ?? '') ?>" dir="rtl" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="address"><?= e(__('setup.school.address')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="address" name="address" value="<?= e($school['address'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="phone"><?= e(__('setup.school.phone')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= e($school['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="email"><?= e(__('settings.profile.email')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= e($school['email'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="website"><?= e(__('settings.profile.website')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="url" class="form-control" id="website" name="website" value="<?= e($school['website'] ?? '') ?>" placeholder="https://">
        </div>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="school_type"><?= e(__('settings.profile.school_type')) ?></label>
          <select class="form-select" id="school_type" name="school_type">
            <option value="language_school" <?= ($school['school_type'] ?? '') === 'language_school' ? 'selected' : '' ?>><?= e(__('settings.type.language_school')) ?></option>
            <option value="private_school" <?= ($school['school_type'] ?? '') === 'private_school' ? 'selected' : '' ?>><?= e(__('settings.type.private_school')) ?></option>
            <option value="public_school" <?= ($school['school_type'] ?? '') === 'public_school' ? 'selected' : '' ?>><?= e(__('settings.type.public_school')) ?></option>
            <option value="international" <?= ($school['school_type'] ?? '') === 'international' ? 'selected' : '' ?>><?= e(__('settings.type.international')) ?></option>
            <option value="primary_only" <?= ($school['school_type'] ?? '') === 'primary_only' ? 'selected' : '' ?>><?= e(__('settings.type.primary_only')) ?></option>
            <option value="secondary_only" <?= ($school['school_type'] ?? '') === 'secondary_only' ? 'selected' : '' ?>><?= e(__('settings.type.secondary_only')) ?></option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="school_structure"><?= e(__('settings.profile.school_structure')) ?></label>
          <select class="form-select" id="school_structure" name="school_structure">
            <option value="k12_combined" <?= ($school['school_structure'] ?? '') === 'k12_combined' ? 'selected' : '' ?>><?= e(__('settings.structure.k12')) ?></option>
            <option value="primary" <?= ($school['school_structure'] ?? '') === 'primary' ? 'selected' : '' ?>><?= e(__('settings.structure.primary')) ?></option>
            <option value="preparatory" <?= ($school['school_structure'] ?? '') === 'preparatory' ? 'selected' : '' ?>><?= e(__('settings.structure.preparatory')) ?></option>
            <option value="secondary" <?= ($school['school_structure'] ?? '') === 'secondary' ? 'selected' : '' ?>><?= e(__('settings.structure.secondary')) ?></option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="currency"><?= e(__('settings.profile.currency')) ?></label>
          <input type="text" class="form-control" id="currency" name="currency" value="<?= e($school['currency'] ?? 'EGP') ?>" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="country"><?= e(__('settings.profile.country')) ?></label>
          <input type="text" class="form-control" id="country" name="country" value="<?= e($school['country'] ?? 'EG') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="timezone"><?= e(__('settings.profile.timezone')) ?></label>
          <input type="text" class="form-control" id="timezone" name="timezone" value="<?= e($school['timezone'] ?? 'Africa/Cairo') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label d-block"><?= e(__('settings.profile.school_week')) ?></label>
          <?php
            $workingDays = array_map('intval', array_filter(explode(',', (string) ($school['school_week'] ?? '0,1,2,3,4')), 'strlen'));
            $weekLabels = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
          ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($weekLabels as $dayNumber => $dayKey): ?>
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" name="school_week_days[]"
                       id="school_week_<?= $dayNumber ?>" value="<?= $dayNumber ?>"
                       <?= in_array($dayNumber, $workingDays, true) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="school_week_<?= $dayNumber ?>">
                  <?= e(__('weekday.' . $dayKey)) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="school_week" value="<?= e($school['school_week'] ?? '0,1,2,3,4') ?>">
          <div class="form-text"><?= e(__('settings.profile.school_week_help')) ?></div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="tax_number"><?= e(__('settings.profile.tax_number')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?= e($school['tax_number'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="affiliation_code"><?= e(__('settings.profile.affiliation_code')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="affiliation_code" name="affiliation_code" value="<?= e($school['affiliation_code'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="footer_text"><?= e(__('settings.report_footer_text')) ?> (<?= e(__('common.optional')) ?>)</label>
        <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?= e(\App\Services\SettingsService::get('reports.school_footer_text', '') ?? '') ?>">
        <div class="form-text"><?= e(__('settings.report_footer_help')) ?></div>
      </div>

      <hr>
      <label class="form-label d-block" for="logo"><?= e(__('settings.logo')) ?></label>
      <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <img src="<?= !empty($school['logo_path']) ? '/logo?v=' . rawurlencode((string) $school['logo_path']) : '/assets/img/hadaba-logo.png' ?>" alt="" style="height:64px; max-width:160px; object-fit:contain;" class="border rounded p-1 bg-white">
        <div class="flex-grow-1" style="min-width:240px;">
          <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg">
          <div class="form-text"><?= e(__('settings.logo_help')) ?></div>
        </div>
        <?php if (!empty($school['logo_path'])): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
            <label class="form-check-label" for="remove_logo"><?= e(__('settings.logo_remove')) ?></label>
          </div>
        <?php endif; ?>
      </div>

      <div class="n-settings-save">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>
