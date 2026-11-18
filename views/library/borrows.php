<?php
/** @var array $borrows @var array $books @var array $students @var array $teachers @var ?string $activeStatus */
$pageTitle = __('library.borrows_desk');
$activeNav = 'library';
ob_start(); ?>
<a href="/library" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <?php if (hasPermission('library.manage')): ?>
      <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#checkoutModal">
        <?= icon('plus') ?>
        <span><?= e(__('library.issue_book')) ?></span>
      </button>
    <?php endif; ?>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<?php $borrows = filterRowsBySearch($borrows, (string) ($_GET['q'] ?? ''), ['book_title', 'book_author', 'student_name', 'student_code', 'teacher_name', 'teacher_code']); ?>
<?php if (!empty($borrows)) { $listSearchTarget = '#borrows-table'; $listSearchAction = '/library/borrows'; $listSearchHidden = ['status' => $activeStatus ?? '']; require dirname(__DIR__) . '/partials/list-search.php'; } ?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="btn-group btn-group-sm" role="group">
      <a href="/library/borrows" class="btn btn-outline-secondary <?= $activeStatus === null ? 'active' : '' ?>"><?= e(__('common.all')) ?></a>
      <a href="/library/borrows?status=borrowed" class="btn btn-outline-secondary <?= $activeStatus === 'borrowed' ? 'active' : '' ?>"><?= e(__('library.status_borrowed')) ?></a>
      <a href="/library/borrows?status=returned" class="btn btn-outline-secondary <?= $activeStatus === 'returned' ? 'active' : '' ?>"><?= e(__('library.status_returned')) ?></a>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($borrows)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('book-open') ?></div>
        <p class="text-muted small mb-0"><?= e(__('library.no_borrows_yet')) ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table id="borrows-table" class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('library.book_title')) ?></th>
              <th><?= e(__('library.borrower')) ?></th>
              <th><?= e(__('library.borrow_date')) ?></th>
              <th><?= e(__('library.due_date')) ?></th>
              <th><?= e(__('library.status')) ?></th>
              <th class="text-end"><?= e(__('common.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($borrows as $b): ?>
              <tr>
                <td class="fw-semibold">
                  <?= e($b['book_title']) ?>
                  <div class="small text-muted"><?= e($b['book_author']) ?></div>
                </td>
                <td>
                  <?php if ($b['borrower_type'] === 'student'): ?>
                    <div><?= e($b['student_name'] ?? '—') ?></div>
                    <span dir="ltr" class="font-monospace small text-muted"><?= e($b['student_code'] ?? '') ?></span>
                  <?php else: ?>
                    <div><?= e($b['teacher_name'] ?? '—') ?></div>
                    <span dir="ltr" class="font-monospace small text-muted"><?= e($b['teacher_code'] ?? '') ?></span>
                  <?php endif; ?>
                </td>
                <td dir="ltr" class="small"><?= e($b['borrow_date']) ?></td>
                <td dir="ltr" class="small"><?= e($b['due_date']) ?></td>
                <td>
                  <?php if ($b['status'] === 'returned'): ?>
                    <span class="badge text-bg-success"><?= e(__('library.status_returned')) ?></span>
                  <?php elseif (strtotime($b['due_date']) < time()): ?>
                    <span class="badge text-bg-danger"><?= e(__('library.status_overdue')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-primary"><?= e(__('library.status_borrowed')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($b['status'] === 'borrowed' && hasPermission('library.manage')): ?>
                    <form method="post" action="/library/borrows/<?= (int) $b['id'] ?>/return" class="d-inline">
                      <?= CsrfMiddleware::field() ?>
                      <button type="submit" class="btn btn-sm btn-outline-success">
                        <?= icon('check') ?> <?= e(__('library.return_action')) ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="small text-muted"><?= e($b['return_date'] ?? '—') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/library/borrow">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="checkoutModalLabel"><?= e(__('library.issue_book')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="book_id"><?= e(__('library.book_title')) ?></label>
            <select class="form-select" id="book_id" name="book_id" required>
              <?php foreach ($books as $bk): ?>
                <?php if ($bk['available_copies'] > 0): ?>
                  <option value="<?= (int) $bk['id'] ?>">
                    <?= e($bk['title']) ?> (<?= (int) $bk['available_copies'] ?> <?= e(__('library.available')) ?>)
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="student_id"><?= e(__('students.name')) ?></label>
            <select class="form-select" id="student_id" name="student_id" required>
              <?php foreach ($students as $st): ?>
                <option value="<?= (int) $st['id'] ?>"><?= e($st['full_name']) ?> (<?= e($st['student_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="due_date"><?= e(__('library.due_date')) ?></label>
            <input type="date" class="form-control" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
