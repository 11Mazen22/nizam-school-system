<?php
/** @var ?string $error @var ?array $teacher @var array $subjects @var int[] $selected */
$isEdit = $teacher !== null;
$pageTitle = $isEdit ? __('teachers.edit') : __('teachers.add');
$activeNav = 'teachers';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head"><div class="n-page-head-main"><span class="n-page-head-icon"><?= icon($isEdit ? 'edit' : 'plus') ?></span><div><h1><?= e($pageTitle) ?></h1><p><?= e(__('teachers.description')) ?></p></div></div><div class="n-page-head-actions"><a href="<?= $isEdit ? '/teachers/' . (int) $teacher['id'] : '/teachers' ?>" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('common.cancel')) ?></a></div></div>
<?php if ($error !== null): ?><div class="alert alert-danger"><?= icon('alert-circle', 'n-icon-lg') ?><span><?= e($error) ?></span></div><?php endif; ?>

<div class="n-form-workspace">
  <form method="post" action="<?= $isEdit ? '/teachers/' . (int) $teacher['id'] : '/teachers' ?>" enctype="multipart/form-data" data-unsaved-guard data-loading-submit>
    <?= CsrfMiddleware::field() ?>
    <section class="n-form-section pt-0">
      <h2 class="n-form-section-title"><?= e(__('teachers.profile_overview')) ?></h2>
      <div class="n-upload-row">
        <?php if (!empty($teacher['photo_path'])): ?><img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" class="n-upload-preview"><?php else: ?><span class="n-upload-preview n-upload-placeholder"><?= icon('image', 'n-icon-lg') ?></span><?php endif; ?>
        <div class="flex-grow-1"><label class="form-label" for="teacher_photo"><?= e(__('teachers.photo')) ?></label><input type="file" class="form-control" id="teacher_photo" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png"><div class="form-text"><?= e(__('uploads.photo_help')) ?></div><?php if (!empty($teacher['photo_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label" for="remove_photo"><?= e(__('common.remove_photo')) ?></label></div><?php endif; ?></div>
      </div>
    </section>
    <section class="n-form-section">
      <h2 class="n-form-section-title"><?= e(__('teachers.contact_information')) ?></h2>
      <div class="row g-3"><div class="col-12"><label class="form-label" for="full_name"><?= e(__('teachers.full_name')) ?></label><input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($teacher['full_name'] ?? '') ?>" required autocomplete="name"></div><div class="col-md-6"><label class="form-label" for="phone"><?= e(__('teachers.phone')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><input type="tel" class="form-control" id="phone" name="phone" dir="ltr" value="<?= e($teacher['phone'] ?? '') ?>" autocomplete="tel"></div><div class="col-md-6"><label class="form-label" for="email"><?= e(__('teachers.email')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label><input type="email" class="form-control" id="email" name="email" value="<?= e($teacher['email'] ?? '') ?>" autocomplete="email"></div></div>
    </section>
    <section class="n-form-section">
      <h2 class="n-form-section-title"><?= e(__('teachers.qualifications')) ?></h2>
      <?php if (empty($subjects)): ?><div class="alert alert-info"><?= icon('info') ?><span><?= e(__('teachers.no_qualifications')) ?></span></div><?php else: ?><div class="n-check-grid"><?php foreach ($subjects as $subject): ?><label class="n-check-card" for="subj-<?= (int) $subject['id'] ?>"><input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int) $subject['id'] ?>" id="subj-<?= (int) $subject['id'] ?>" <?= in_array((int) $subject['id'], $selected, true) ? 'checked' : '' ?>><span><?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?></span></label><?php endforeach; ?></div><?php endif; ?>
    </section>
    <div class="n-form-actions"><a href="<?= $isEdit ? '/teachers/' . (int) $teacher['id'] : '/teachers' ?>" class="btn btn-secondary"><?= e(__('common.cancel')) ?></a><button type="submit" class="btn btn-primary"><?= icon('check') ?><?= e(__('common.save')) ?></button></div>
  </form>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
