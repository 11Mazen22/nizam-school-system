<?php
/** @var array $teachers @var string $q */
$pageTitle = __('teachers.title');
$activeNav = 'teachers';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form method="get" action="/teachers" class="d-flex gap-2">
    <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>">
    <button type="submit" class="btn btn-outline-secondary"><?= e(__('common.search')) ?></button>
  </form>
  <div class="d-flex gap-2">
    <a href="/teachers/archived" class="btn btn-outline-secondary"><?= e(__('common.archived_list')) ?></a>
    <a href="/teachers/create" class="btn btn-primary"><?= e(__('teachers.add')) ?></a>
  </div>
</div>

<?php if (empty($teachers)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('teachers.code')) ?></th>
          <th><?= e(__('teachers.full_name')) ?></th>
          <th><?= e(__('teachers.phone')) ?></th>
          <th><?= e(__('teachers.email')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $teacher): ?>
          <tr>
            <td class="mono"><?= e($teacher['teacher_code']) ?></td>
            <td>
              <a href="/teachers/<?= (int) $teacher['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <?php if (!empty($teacher['photo_path'])): ?>
                  <img src="/teachers/<?= (int) $teacher['id'] ?>/photo" alt="" style="width:28px; height:28px; object-fit:cover;" class="rounded-circle">
                <?php endif; ?>
                <?= e($teacher['full_name']) ?>
              </a>
            </td>
            <td><?= e($teacher['phone'] ?? '—') ?></td>
            <td><?= e($teacher['email'] ?? '—') ?></td>
            <td class="text-nowrap">
              <a href="/teachers/<?= (int) $teacher['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= e(__('common.view')) ?></a>
              <a href="/teachers/<?= (int) $teacher['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.edit')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
