<?php
/** @var array $grades */
$pageTitle = __('grades.title');
$activeNav = 'grades';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal-new">
    <?= e(__('grades.add')) ?>
  </button>
</div>

<?php if (empty($grades)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('grades.sort_order')) ?></th>
          <th><?= e(__('grades.name_en')) ?></th>
          <th><?= e(__('grades.name_ar')) ?></th>
          <th><?= e(__('academic_years.status')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grades as $grade): ?>
          <tr>
            <td class="font-variant-numeric-tabular"><?= (int) $grade['sort_order'] ?></td>
            <td><?= e($grade['name_en']) ?></td>
            <td><?= e($grade['name_ar']) ?></td>
            <td>
              <?php if ((int) $grade['is_active'] === 1): ?>
                <span class="badge text-bg-success"><?= e(__('common.status_active')) ?></span>
              <?php else: ?>
                <span class="badge text-bg-secondary"><?= e(__('common.status_archived')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gradeModal-<?= (int) $grade['id'] ?>">
                <?= e(__('common.edit')) ?>
              </button>
              <?php if ((int) $grade['is_active'] === 1): ?>
                <form method="post" action="/grades/<?= (int) $grade['id'] ?>/archive" class="d-inline" data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.archive')) ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/grades/<?= (int) $grade['id'] ?>/restore" class="d-inline" data-confirm="<?= e(__('common.confirm_restore')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-success"><?= e(__('common.restore')) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <div class="modal fade" id="gradeModal-<?= (int) $grade['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post" action="/grades/<?= (int) $grade['id'] ?>">
                  <?= CsrfMiddleware::field() ?>
                  <div class="modal-header"><h2 class="h6 m-0"><?= e(__('common.edit')) ?></h2></div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('grades.name_en')) ?></label>
                      <input type="text" class="form-control" name="name_en" value="<?= e($grade['name_en']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('grades.name_ar')) ?></label>
                      <input type="text" class="form-control" name="name_ar" value="<?= e($grade['name_ar']) ?>" dir="rtl" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('grades.sort_order')) ?></label>
                      <input type="number" class="form-control" name="sort_order" value="<?= (int) $grade['sort_order'] ?>" min="1" required>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="modal fade" id="gradeModal-new" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/grades">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header"><h2 class="h6 m-0"><?= e(__('grades.add')) ?></h2></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><?= e(__('grades.name_en')) ?></label>
            <input type="text" class="form-control" name="name_en" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(__('grades.name_ar')) ?></label>
            <input type="text" class="form-control" name="name_ar" dir="rtl" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(__('grades.sort_order')) ?></label>
            <input type="number" class="form-control" name="sort_order" value="<?= count($grades) + 1 ?>" min="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
