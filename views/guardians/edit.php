<?php
/** @var array $guardian */
$pageTitle = __('common.edit') . ' ' . $guardian['full_name'];
$activeNav = 'guardians';
ob_start(); ?>
<a href="/guardians/<?= (int) $guardian['id'] ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<div class="card col-lg-8 mx-auto">
  <div class="card-body p-4">
    <form method="post" action="/guardians/<?= (int) $guardian['id'] ?>">
      <?= CsrfMiddleware::field() ?>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="full_name"><?= e(__('guardians.full_name')) ?></label>
          <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($guardian['full_name']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="phone"><?= e(__('guardians.phone')) ?></label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= e($guardian['phone']) ?>" required dir="ltr">
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="email"><?= e(__('guardians.email')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= e($guardian['email'] ?? '') ?>" dir="ltr">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="national_id"><?= e(__('guardians.national_id')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="national_id" name="national_id" value="<?= e($guardian['national_id'] ?? '') ?>" dir="ltr">
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="relationship_type"><?= e(__('guardians.relationship')) ?></label>
          <select class="form-select" id="relationship_type" name="relationship_type">
            <option value="father" <?= $guardian['relationship_type'] === 'father' ? 'selected' : '' ?>><?= e(__('guardians.rel_father')) ?></option>
            <option value="mother" <?= $guardian['relationship_type'] === 'mother' ? 'selected' : '' ?>><?= e(__('guardians.rel_mother')) ?></option>
            <option value="guardian" <?= $guardian['relationship_type'] === 'guardian' ? 'selected' : '' ?>><?= e(__('guardians.rel_guardian')) ?></option>
            <option value="sponsor" <?= $guardian['relationship_type'] === 'sponsor' ? 'selected' : '' ?>><?= e(__('guardians.rel_sponsor')) ?></option>
            <option value="other" <?= $guardian['relationship_type'] === 'other' ? 'selected' : '' ?>><?= e(__('guardians.rel_other')) ?></option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="occupation"><?= e(__('guardians.occupation')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="occupation" name="occupation" value="<?= e($guardian['occupation'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="address"><?= e(__('guardians.address')) ?> (<?= e(__('common.optional')) ?>)</label>
        <input type="text" class="form-control" id="address" name="address" value="<?= e($guardian['address'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label" for="notes"><?= e(__('guardians.notes')) ?> (<?= e(__('common.optional')) ?>)</label>
        <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($guardian['notes'] ?? '') ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="/guardians/<?= (int) $guardian['id'] ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
