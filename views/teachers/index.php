<?php
/** @var array $teachers @var string $q */
$pageTitle = __('teachers.title');
$activeNav = 'teachers';
$canCreate = hasPermission('teachers.create');
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('teachers.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-stat-mini"><span class="n-stat-mini-value" data-count-up><?= count($teachers) ?></span><span class="n-stat-mini-label"><?= e(__('teachers.total_teachers')) ?></span></div><?php if ($canCreate): ?><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#importModal"><?= icon('upload') ?><?= e(__('app.import')) ?></button><a href="/teachers/create" class="btn btn-primary"><?= icon('plus') ?><?= e(__('teachers.add')) ?></a><?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<form method="get" action="/teachers" class="n-filter-panel" data-live-filter data-live-target="#teacher-results"><div class="n-filter-row">
  <div class="n-filter-field n-search-field"><?= icon('search') ?><label class="visually-hidden" for="teacher-search"><?= e(__('common.search')) ?></label><input id="teacher-search" type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>" autocomplete="off"></div>
  <button type="submit" class="btn btn-primary"><?= icon('search') ?><?= e(__('common.search')) ?></button>
  <?php if ($q !== ''): ?><a href="/teachers" class="btn btn-outline-secondary"><?= icon('close') ?><?= e(__('common.clear_filters')) ?></a><?php endif; ?>
  <a href="/teachers/archived" class="btn btn-ghost ms-auto"><?= icon('archive') ?><?= e(__('common.archived_list')) ?></a>
</div></form>

<div id="teacher-results" data-live-results>
<?php if (empty($teachers)): ?>
  <div class="n-empty-state"><div class="n-empty-icon"><?= icon('graduation-cap', 'n-icon-xl') ?></div><h2 class="n-empty-title"><?= e(__('teachers.empty_title')) ?></h2><p class="n-empty-description"><?= e($q !== '' ? __('common.no_results') : __('teachers.empty_description')) ?></p><?php if ($canCreate && $q === ''): ?><a href="/teachers/create" class="btn btn-primary"><?= icon('plus') ?><?= e(__('teachers.add')) ?></a><?php endif; ?></div>
<?php else: ?>
  <div class="n-data-panel"><div class="table-responsive"><table class="table table-hover align-middle n-table-stack">
    <thead><tr><th><?= e(__('teachers.full_name')) ?></th><th><?= e(__('teachers.code')) ?></th><th><?= e(__('teachers.phone')) ?></th><th><?= e(__('teachers.email')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th></tr></thead>
    <tbody><?php foreach ($teachers as $teacher): ?><tr>
      <td data-label="<?= e(__('teachers.full_name')) ?>"><a href="/teachers/<?= (int) $teacher['id'] ?>" class="n-person-cell"><?php if (!empty($teacher['photo_path'])): ?><img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" class="n-person-avatar"><?php else: ?><span class="n-person-avatar n-person-initial"><?= e(mb_substr($teacher['full_name'], 0, 1)) ?></span><?php endif; ?><span><strong><?= e($teacher['full_name']) ?></strong><small><?= e(__('common.status_active')) ?></small></span></a></td>
      <td data-label="<?= e(__('teachers.code')) ?>" class="mono" dir="ltr"><?= e($teacher['teacher_code']) ?></td>
      <td data-label="<?= e(__('teachers.phone')) ?>" dir="ltr"><?= e($teacher['phone'] ?? '—') ?></td>
      <td data-label="<?= e(__('teachers.email')) ?>"><?= e($teacher['email'] ?? '—') ?></td>
      <td data-label="<?= e(__('common.actions')) ?>"><div class="n-table-actions"><a href="/teachers/<?= (int) $teacher['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= icon('eye') ?><?= e(__('common.view')) ?></a><?php if (hasPermission('teachers.edit')): ?><a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-sm btn-ghost" aria-label="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?></div></td>
    </tr><?php endforeach; ?></tbody>
  </table></div></div>
<?php endif; ?>
</div>

<?php
if ($canCreate) {
    ob_start(); $title = __('app.import') . ' — ' . __('teachers.title'); $action = '/import/teachers'; $templateUrl = '/import/teachers/template'; require dirname(__DIR__) . '/partials/import_modal.php'; $pageModals = ob_get_clean();
}
require dirname(__DIR__) . '/layout/end.php';
?>
