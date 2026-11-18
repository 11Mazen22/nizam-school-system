<?php
/** @var array $students @var string $q @var int $page @var int $perPage @var int $total @var int $lastPage @var string $missing @var string $religion @var array{total:int,no_gender:int,no_dob:int,no_religion:int,no_personal:int,no_guardian:int} $quality */
$pageTitle = __('students.title');
$activeNav = 'students';
$locale = currentLocale();
$canCreate = hasPermission('students.create');
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('students.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-stat-mini"><span class="n-stat-mini-value" data-count-up><?= (int) $total ?></span><span class="n-stat-mini-label"><?= e(__('students.total_students')) ?></span></div>
      <?php if ($canCreate): ?><a class="btn btn-outline-secondary" href="/students/import/roster"><?= icon('file-text') ?><?= e(__('roster.short')) ?></a><button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><?= icon('upload') ?><span><?= e(__('app.import')) ?></span></button><?php endif; ?>
      <?php if (hasPermission('students.edit')): ?><a class="btn btn-outline-secondary" href="/students/export/enrichment"><?= icon('download') ?><?= e(__('students.enrichment_template')) ?></a><?php endif; ?>
      <?php if (hasPermission('students.promote')): ?><a href="/students/promotion" class="btn btn-outline-primary"><?= icon('trending-up') ?><span><?= e(__('promotion.title')) ?></span></a><?php endif; ?>
      <?php if ($canCreate): ?><a href="/students/create" class="btn btn-primary"><?= icon('plus') ?><span><?= e(__('students.add')) ?></span></a><?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<form method="get" action="/students" class="n-filter-panel" aria-label="<?= e(__('common.filter')) ?>" data-live-filter data-live-target="#student-results">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field">
      <?= icon('search') ?>
      <label class="visually-hidden" for="student-search"><?= e(__('common.search')) ?></label>
      <input id="student-search" type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>" autocomplete="off" aria-describedby="student-search-hint">
      <span id="student-search-hint" class="visually-hidden"><?= e(__('students.search_instant')) ?></span>
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
    <div class="n-filter-field">
      <label class="visually-hidden" for="student-religion-filter"><?= e(__('students.religion')) ?></label>
      <select id="student-religion-filter" name="religion" class="form-select">
        <option value=""><?= e(__('students.all_religions')) ?></option>
        <?php foreach (['muslim', 'christian', 'other'] as $option): ?>
          <option value="<?= e($option) ?>" <?= $religion === $option ? 'selected' : '' ?>><?= e(__('students.religion_' . $option)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="n-filter-field">
      <label class="visually-hidden" for="student-missing-filter"><?= e(__('students.data_quality')) ?></label>
      <select id="student-missing-filter" name="missing" class="form-select">
        <option value=""><?= e(__('students.all_records')) ?></option>
        <option value="personal" <?= $missing === 'personal' ? 'selected' : '' ?>><?= e(__('students.missing_personal')) ?></option>
        <option value="guardian" <?= $missing === 'guardian' ? 'selected' : '' ?>><?= e(__('students.missing_guardian')) ?></option>
        <option value="contact" <?= $missing === 'contact' ? 'selected' : '' ?>><?= e(__('students.missing_contact')) ?></option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?><span><?= e(__('common.filter')) ?></span></button>
    <?php if ($q !== '' || $gradeId > 0 || $classId > 0 || $religion !== ''): ?><a href="/students" class="btn btn-outline-secondary"><?= icon('close') ?><span><?= e(__('common.clear_filters')) ?></span></a><?php endif; ?>
    <a href="/students/archived" class="btn btn-ghost ms-auto"><?= icon('archive') ?><span><?= e(__('common.archived_list')) ?></span></a>
  </div>
</form>

<?php if ($quality['total'] > 0 && ($quality['no_gender'] > 0 || $quality['no_guardian'] > 0)): ?>
  <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="status">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <span class="flex-grow-1">
      <?= e(__('students.data_quality_hint')) ?>
      <a href="/students?missing=personal" class="alert-link"><?= e(__('students.missing_personal_count', ['count' => $quality['no_personal']])) ?></a>
      · <a href="/students?missing=guardian" class="alert-link"><?= e(__('students.missing_guardian_count', ['count' => $quality['no_guardian']])) ?></a>
    </span>
  </div>
<?php endif; ?>

<div id="student-results" data-live-results>
<?php if (empty($students)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('users', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('students.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e($q !== '' || $gradeId > 0 || $classId > 0 || $missing !== '' || $religion !== '' ? __('common.no_results') : __('students.empty_description')) ?></p>
    <?php if ($canCreate && $q === '' && $gradeId === 0 && $classId === 0 && $missing === '' && $religion === ''): ?><a href="/students/create" class="btn btn-primary"><?= icon('plus') ?><?= e(__('students.add')) ?></a><?php endif; ?>
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
              <td data-label="<?= e(__('students.religion')) ?>"><?= $student['religion'] ? e(__('students.religion_' . $student['religion'])) : '<span class="text-muted">—</span>' ?></td>
              <td data-label="<?= e(__('common.actions')) ?>"><div class="n-table-actions"><a href="/students/<?= (int) $student['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= icon('eye') ?><span><?= e(__('common.view')) ?></span></a><?php if (hasPermission('students.edit')): ?><a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-sm btn-ghost" aria-label="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($lastPage > 1): ?>
    <?php $queryString = $q !== '' ? '&q=' . urlencode($q) : ''; if ($gradeId > 0) $queryString .= '&grade_id=' . $gradeId; if ($classId > 0) $queryString .= '&class_id=' . $classId; if ($missing !== '') $queryString .= '&missing=' . urlencode($missing); if ($religion !== '') $queryString .= '&religion=' . urlencode($religion); ?>
    <?php
    // Keep large schools usable: first/last + a five-page window, with one
    // ellipsis per gap. Rendering all 39+ page buttons wraps across the card
    // and makes every in-place page change visually jumpy.
    $visiblePages = [1, $lastPage];
    for ($candidate = max(1, $page - 2); $candidate <= min($lastPage, $page + 2); $candidate++) {
        $visiblePages[] = $candidate;
    }
    $visiblePages = array_values(array_unique($visiblePages));
    sort($visiblePages);
    ?>
    <nav aria-label="Students pagination" class="mt-3"><ul class="pagination justify-content-center flex-wrap" data-compact-pagination>
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="/students?page=<?= max(1, $page - 1) ?><?= $queryString ?>" aria-label="<?= e(__('common.back')) ?>"><?= icon('chevron-left', 'n-flip-rtl') ?></a></li>
      <?php $previousPage = null; foreach ($visiblePages as $p): ?>
        <?php if ($previousPage !== null && $p > $previousPage + 1): ?><li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <?php if ($p === $page): ?><span class="page-link" aria-current="page"><?= $p ?></span><?php else: ?><a class="page-link" href="/students?page=<?= $p ?><?= $queryString ?>"><?= $p ?></a><?php endif; ?>
        </li>
      <?php $previousPage = $p; endforeach; ?>
      <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>"><a class="page-link" href="/students?page=<?= min($lastPage, $page + 1) ?><?= $queryString ?>" aria-label="<?= e(__('common.next')) ?>"><?= icon('chevron-right', 'n-flip-rtl') ?></a></li>
    </ul></nav>
    <p class="text-center text-muted small" dir="ltr"><?= e(sprintf('%d–%d / %d', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total)) ?></p>
  <?php endif; ?>
<?php endif; ?>
</div>

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
