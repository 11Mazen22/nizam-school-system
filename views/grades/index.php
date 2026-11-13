<?php
/** @var array $grades */
$pageTitle = __('grades.title');
$activeNav = 'grades';
$pageScripts = ['/assets/js/grades.js'];
$canManage = hasPermission('grades.manage');
$activeGrades = array_filter($grades, static fn (array $grade): bool => (int) $grade['is_active'] === 1);
$archivedGrades = array_filter($grades, static fn (array $grade): bool => (int) $grade['is_active'] === 0);
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('grades.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-grades-stats-mini"><div class="n-stat-mini"><span class="n-stat-mini-value"><?= count($activeGrades) ?></span><span class="n-stat-mini-label"><?= e(__('grades.stat_active')) ?></span></div><div class="n-stat-mini archived"><span class="n-stat-mini-value"><?= count($archivedGrades) ?></span><span class="n-stat-mini-label"><?= e(__('grades.stat_archived')) ?></span></div></div><?php if ($canManage): ?><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#importModal"><?= icon('upload') ?><?= e(__('app.import')) ?></button><?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<ul class="nav nav-tabs mb-4" role="tablist">
  <li class="nav-item"><button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button"><?= icon('list') ?><?= e(__('grades.tab_list')) ?><span class="badge"><?= count($grades) ?></span></button></li>
  <?php if ($canManage): ?><li class="nav-item"><button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button"><?= icon('plus') ?><?= e(__('grades.tab_add')) ?></button></li><li class="nav-item d-none" id="edit-tab-wrapper"><button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button"><?= icon('edit') ?><?= e(__('grades.tab_edit')) ?></button></li><?php endif; ?>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($grades)): ?>
      <div class="n-empty-state"><div class="n-empty-icon"><?= icon('layers') ?></div><h2 class="n-empty-title"><?= e(__('grades.empty_title')) ?></h2><p class="n-empty-description"><?= e(__('grades.empty_description')) ?></p><?php if ($canManage): ?><button class="btn btn-primary" type="button" data-tab-switch="add-tab"><?= icon('plus') ?><?= e(__('grades.add')) ?></button><?php endif; ?></div>
    <?php else: ?>
      <div class="n-data-panel"><div class="table-responsive"><table class="table table-hover align-middle n-table-stack">
        <thead><tr><th><?= e(__('grades.sort_order')) ?></th><th><?= e(__('grades.name_en')) ?></th><th><?= e(__('grades.name_ar')) ?></th><th><?= e(__('academic_years.status')) ?></th><?php if ($canManage): ?><th class="text-end"><?= e(__('common.actions')) ?></th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($grades as $grade): ?><tr>
          <td data-label="<?= e(__('grades.sort_order')) ?>"><span class="n-order-chip"><?= (int) $grade['sort_order'] ?></span></td>
          <td data-label="<?= e(__('grades.name_en')) ?>" lang="en" dir="ltr"><strong><?= e($grade['name_en']) ?></strong></td>
          <td data-label="<?= e(__('grades.name_ar')) ?>" lang="ar" dir="rtl"><?= e($grade['name_ar']) ?></td>
          <td data-label="<?= e(__('academic_years.status')) ?>"><span class="badge <?= (int) $grade['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e((int) $grade['is_active'] === 1 ? __('common.status_active') : __('common.status_archived')) ?></span></td>
          <?php if ($canManage): ?><td data-label="<?= e(__('common.actions')) ?>"><div class="n-table-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-grade-edit data-grade-id="<?= (int) $grade['id'] ?>" data-grade-name-en="<?= e($grade['name_en']) ?>" data-grade-name-ar="<?= e($grade['name_ar']) ?>" data-grade-sort-order="<?= (int) $grade['sort_order'] ?>"><?= icon('edit') ?><?= e(__('common.edit')) ?></button><?php if ((int) $grade['is_active'] === 1): ?><form method="post" action="/grades/<?= (int) $grade['id'] ?>/archive" data-confirm="<?= e(__('common.confirm_archive')) ?>"><?= CsrfMiddleware::field() ?><button class="btn btn-sm btn-ghost text-danger" type="submit" aria-label="<?= e(__('common.archive')) ?>"><?= icon('archive') ?></button></form><?php else: ?><form method="post" action="/grades/<?= (int) $grade['id'] ?>/restore" data-confirm="<?= e(__('common.confirm_restore')) ?>"><?= CsrfMiddleware::field() ?><button class="btn btn-sm btn-outline-success" type="submit"><?= icon('unarchive') ?><?= e(__('common.restore')) ?></button></form><?php endif; ?></div></td><?php endif; ?>
        </tr><?php endforeach; ?></tbody>
      </table></div></div>
    <?php endif; ?>
  </div>

  <?php if ($canManage): ?>
  <div class="tab-pane fade" id="add-panel" role="tabpanel">
    <div class="n-form-card-powerful"><div class="n-form-card-header"><span class="n-form-card-icon"><?= icon('plus') ?></span><div><h2 class="n-form-card-title"><?= e(__('grades.tab_add')) ?></h2><p class="n-form-card-subtitle"><?= e(__('grades.form_add_subtitle')) ?></p></div></div><form method="post" action="/grades" class="n-form-powerful" data-unsaved-guard data-loading-submit><?= CsrfMiddleware::field() ?><div class="row g-3"><div class="col-md-6"><label class="form-label" for="name_en_new"><?= e(__('grades.name_en')) ?></label><input class="form-control" id="name_en_new" name="name_en" lang="en" dir="ltr" required></div><div class="col-md-6"><label class="form-label" for="name_ar_new"><?= e(__('grades.name_ar')) ?></label><input class="form-control" id="name_ar_new" name="name_ar" lang="ar" dir="rtl" required></div><div class="col-md-4"><label class="form-label" for="sort_order_new"><?= e(__('grades.sort_order')) ?></label><input type="number" class="form-control" id="sort_order_new" name="sort_order" value="<?= count($grades) + 1 ?>" min="1" required></div></div><div class="n-form-actions"><button type="button" class="btn btn-secondary" data-tab-switch="list-tab"><?= e(__('common.cancel')) ?></button><button class="btn btn-primary" type="submit"><?= icon('check') ?><?= e(__('common.save')) ?></button></div></form></div>
  </div>
  <div class="tab-pane fade" id="edit-panel" role="tabpanel">
    <div class="n-form-card-powerful"><div class="n-form-card-header"><span class="n-form-card-icon"><?= icon('edit') ?></span><div><h2 class="n-form-card-title"><?= e(__('grades.tab_edit')) ?></h2><p class="n-form-card-subtitle"><?= e(__('grades.form_edit_subtitle')) ?></p></div></div><form method="post" action="/grades/" id="editGradeForm" class="n-form-powerful" data-unsaved-guard data-loading-submit><?= CsrfMiddleware::field() ?><input type="hidden" id="edit_grade_id" name="grade_id"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="name_en_edit"><?= e(__('grades.name_en')) ?></label><input class="form-control" id="name_en_edit" name="name_en" lang="en" dir="ltr" required></div><div class="col-md-6"><label class="form-label" for="name_ar_edit"><?= e(__('grades.name_ar')) ?></label><input class="form-control" id="name_ar_edit" name="name_ar" lang="ar" dir="rtl" required></div><div class="col-md-4"><label class="form-label" for="sort_order_edit"><?= e(__('grades.sort_order')) ?></label><input type="number" class="form-control" id="sort_order_edit" name="sort_order" min="1" required></div></div><div class="n-form-actions"><button type="button" class="btn btn-secondary" data-tab-switch="list-tab"><?= e(__('common.cancel')) ?></button><button class="btn btn-primary" type="submit"><?= icon('check') ?><?= e(__('common.update')) ?></button></div></form></div>
  </div>
  <?php endif; ?>
</div>

<?php
if ($canManage) { ob_start(); $title = __('app.import') . ' — ' . __('grades.title'); $action = '/import/grades'; $templateUrl = '/import/grades/template'; require dirname(__DIR__) . '/partials/import_modal.php'; $pageModals = ob_get_clean(); }
require dirname(__DIR__) . '/layout/end.php';
?>
