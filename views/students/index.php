<?php
/** @var array $students @var string $q @var int $page @var int $perPage @var int $total @var int $lastPage */
$pageTitle = __('students.title');
$activeNav = 'students';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form method="get" action="/students" class="d-flex gap-2">
    <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>">
    <button type="submit" class="btn btn-outline-secondary"><?= e(__('common.search')) ?></button>
  </form>
  <div class="d-flex gap-2">
    <a href="/students/archived" class="btn btn-outline-secondary"><?= e(__('common.archived_list')) ?></a>
    <a href="/students/promotion" class="btn btn-outline-primary"><?= e(__('promotion.title')) ?></a>
    <a href="/students/create" class="btn btn-primary"><?= e(__('students.add')) ?></a>
  </div>
</div>

<?php if (empty($students)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('students.code')) ?></th>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('students.religion')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td class="mono"><?= e($student['student_code']) ?></td>
            <td>
              <a href="/students/<?= (int) $student['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <?php if (!empty($student['photo_path'])): ?>
                  <img src="/students/<?= (int) $student['id'] ?>/photo" alt="" style="width:28px; height:28px; object-fit:cover;" class="rounded-circle">
                <?php endif; ?>
                <?= e($student['full_name']) ?>
              </a>
            </td>
            <td><?= e(__('students.religion_' . $student['religion'])) ?></td>
            <td class="text-nowrap">
              <a href="/students/<?= (int) $student['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= e(__('common.view')) ?></a>
              <a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.edit')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($lastPage > 1): ?>
    <?php
      // Build base query string preserving search term
      $qs = $q !== '' ? '&q=' . urlencode($q) : '';
    ?>
    <nav aria-label="Students pagination">
      <ul class="pagination justify-content-center mt-3">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="/students?page=<?= $page - 1 ?><?= $qs ?>">&laquo;</a>
        </li>
        <?php for ($p = 1; $p <= $lastPage; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="/students?page=<?= $p ?><?= $qs ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
          <a class="page-link" href="/students?page=<?= $page + 1 ?><?= $qs ?>">&raquo;</a>
        </li>
      </ul>
    </nav>
    <p class="text-center text-muted small">
      <?= e(sprintf('%d–%d of %d', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total)) ?>
    </p>
  <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
