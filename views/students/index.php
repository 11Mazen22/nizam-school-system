<?php
/** @var array $students @var string $q @var int $page @var int $perPage @var int $total @var int $lastPage */
$pageTitle = __('students.title');
$activeNav = 'students';
$locale = currentLocale();
$canCreate = hasPermission('students.create');
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('students.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-stat-mini"><span class="n-stat-mini-value" data-count-up><?= (int) $total ?></span><span class="n-stat-mini-label"><?= e(__('students.total_students')) ?></span></div>
      <?php if ($canCreate): ?><button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><?= icon('upload') ?><span><?= e(__('app.import')) ?></span></button><?php endif; ?>
      <?php if (hasPermission('students.promote')): ?><a href="/students/promotion" class="btn btn-outline-primary"><?= icon('trending-up') ?><span><?= e(__('promotion.title')) ?></span></a><?php endif; ?>
      <?php if ($canCreate): ?><a href="/students/create" class="btn btn-primary"><?= icon('plus') ?><span><?= e(__('students.add')) ?></span></a><?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<form method="get" action="/students" class="n-filter-panel" aria-label="<?= e(__('common.filter')) ?>">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field">
      <?= icon('search') ?>
      <label class="visually-hidden" for="student-search"><?= e(__('common.search')) ?></label>
      <input id="student-search" type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>">
    </div>
    <div class="n-filter-field">
      <label class="visually-hidden" for="student-grade-filter"><?= e(__('classes.grade')) ?></label>
      <select id="student-grade-filter" name="grade_id" class="form-select">
        <option value="0"><?= e(__('reports.all_grades')) ?></option>
        <?php foreach ($grades ?? [] as $grade): ?>
          <option value="<?= (int) $grade['id'] ?>" <?= (int) $grade['id'] === (int) $gradeId ? 'selected' : '' ?>><?= e($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="n-filter-field">
      <label class="visually-hidden" for="student-class-filter"><?= e(__('classes.class')) ?></label>
      <select id="student-class-filter" name="class_id" class="form-select">
        <option value="0"><?= e(__('reports.all_classes')) ?></option>
        <?php foreach ($classes ?? [] as $class): ?>
          <?php if ($gradeId > 0 && (int) $class['grade_id'] !== (int) $gradeId) continue; ?>
          <option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === (int) $classId ? 'selected' : '' ?>><?= e($class['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?><span><?= e(__('common.filter')) ?></span></button>
    <?php if ($q !== '' || $gradeId > 0 || $classId > 0): ?><a href="/students" class="btn btn-outline-secondary"><?= icon('close') ?><span><?= e(__('common.clear_filters')) ?></span></a><?php endif; ?>
    <a href="/students/archived" class="btn btn-ghost ms-auto"><?= icon('archive') ?><span><?= e(__('common.archived_list')) ?></span></a>
  </div>
</form>

<?php if (empty($students)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('users', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('students.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e($q !== '' || $gradeId > 0 || $classId > 0 ? __('common.no_results') : __('students.empty_description')) ?></p>
    <?php if ($canCreate && $q === '' && $gradeId === 0 && $classId === 0): ?><a href="/students/create" class="btn btn-primary"><?= icon('plus') ?><?= e(__('students.add')) ?></a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="n-data-panel">
    <div class="table-responsive">
      <table class="table table-hover align-middle n-table-stack">
        <thead><tr><th><?= e(__('students.full_name')) ?></th><th><?= e(__('students.code')) ?></th><th><?= e(__('classes.grade')) ?> / <?= e(__('classes.class')) ?></th><th><?= e(__('students.religion')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($students as $student): ?>
            <tr>
              <td data-label="<?= e(__('students.full_name')) ?>">
                <a href="/students/<?= (int) $student['id'] ?>" class="n-person-cell">
                  <?php if (!empty($student['photo_path'])): ?><img src="/students/<?= (int) $student['id'] ?>/photo" alt="" class="n-person-avatar"><?php else: ?><span class="n-person-avatar n-person-initial"><?= e(mb_substr($student['full_name'], 0, 1)) ?></span><?php endif; ?>
                  <span><strong><?= e($student['full_name']) ?></strong><small><?= e(__('common.status_active')) ?></small></span>
                </a>
              </td>
              <td data-label="<?= e(__('students.code')) ?>" class="mono" dir="ltr"><?= e($student['student_code']) ?></td>
              <td data-label="<?= e(__('classes.grade')) ?> / <?= e(__('classes.class')) ?>">
                <?php if (!empty($student['grade_name_en'])): ?><span class="badge text-bg-light border"><?= e($locale === 'ar' ? $student['grade_name_ar'] : $student['grade_name_en']) ?> · <?= e($student['class_name']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td data-label="<?= e(__('students.religion')) ?>"><?= e(__('students.religion_' . $student['religion'])) ?></td>
              <td data-label="<?= e(__('common.actions')) ?>"><div class="n-table-actions"><a href="/students/<?= (int) $student['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= icon('eye') ?><span><?= e(__('common.view')) ?></span></a><?php if (hasPermission('students.edit')): ?><a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-sm btn-ghost" aria-label="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($lastPage > 1): ?>
    <?php $queryString = $q !== '' ? '&q=' . urlencode($q) : ''; if ($gradeId > 0) $queryString .= '&grade_id=' . $gradeId; if ($classId > 0) $queryString .= '&class_id=' . $classId; ?>
    <nav aria-label="Students pagination" class="mt-3"><ul class="pagination justify-content-center">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="/students?page=<?= $page - 1 ?><?= $queryString ?>"><?= icon('chevron-left', 'n-flip-rtl') ?></a></li>
      <?php for ($p = 1; $p <= $lastPage; $p++): ?><li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="/students?page=<?= $p ?><?= $queryString ?>"><?= $p ?></a></li><?php endfor; ?>
      <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>"><a class="page-link" href="/students?page=<?= $page + 1 ?><?= $queryString ?>"><?= icon('chevron-right', 'n-flip-rtl') ?></a></li>
    </ul></nav>
    <p class="text-center text-muted small" dir="ltr"><?= e(sprintf('%d–%d / %d', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total)) ?></p>
  <?php endif; ?>
<?php endif; ?>

<?php
if ($canCreate) {
    ob_start();
    $title = __('app.import') . ' — ' . __('students.title');
    $action = '/import/students';
    $templateUrl = '/import/students/template';
    require dirname(__DIR__) . '/partials/import_modal.php';
    $pageModals = ob_get_clean();
}
require dirname(__DIR__) . '/layout/end.php';
?>
