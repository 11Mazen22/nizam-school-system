<?php
/** @var array $departments @var array $designations @var array $leaves @var array $teachers */
$pageTitle = __('hr.title');
$activeNav = 'hr';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('hr.subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/hr/leaves" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('calendar') ?>
      <span><?= e(__('hr.leave_management')) ?></span>
    </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(__('hr.departments')) ?></span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#deptModal">
          <?= icon('plus') ?>
          <span><?= e(__('hr.add_department')) ?></span>
        </button>
      </div>
      <div class="card-body p-0">
        <?php if (empty($departments)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('briefcase') ?></div>
            <p class="text-muted small mb-0"><?= e(__('hr.no_departments_yet')) ?></p>
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($departments as $d): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold"><?= e(currentLocale() === 'ar' ? $d['name_ar'] : $d['name_en']) ?></div>
                  <span class="font-monospace small text-muted"><?= e($d['code']) ?></span>
                </div>
                <span class="badge text-bg-light border"><?= e(__('hr.active')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(__('hr.recent_leaves')) ?></span>
        <a href="/hr/leaves" class="btn btn-sm btn-link text-decoration-none"><?= e(__('hr.view_all_leaves')) ?></a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($leaves)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('calendar') ?></div>
            <p class="text-muted small mb-0"><?= e(__('hr.no_leaves_yet')) ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('teachers.name')) ?></th>
                  <th><?= e(__('hr.leave_type')) ?></th>
                  <th><?= e(__('hr.dates')) ?></th>
                  <th><?= e(__('hr.status')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($leaves, 0, 5) as $l): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($l['teacher_name']) ?></td>
                    <td><?= e(__('hr.type_' . $l['leave_type'])) ?></td>
                    <td dir="ltr" class="small"><?= e($l['start_date']) ?> → <?= e($l['end_date']) ?></td>
                    <td>
                      <?php if ($l['status'] === 'approved'): ?>
                        <span class="badge text-bg-success"><?= e(__('hr.status_approved')) ?></span>
                      <?php elseif ($l['status'] === 'rejected'): ?>
                        <span class="badge text-bg-danger"><?= e(__('hr.status_rejected')) ?></span>
                      <?php else: ?>
                        <span class="badge text-bg-warning"><?= e(__('hr.status_pending')) ?></span>
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
  </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-labelledby="deptModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/hr/departments">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="deptModalLabel"><?= e(__('hr.add_department')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="code"><?= e(__('hr.dept_code')) ?></label>
            <input type="text" class="form-control" id="code" name="code" placeholder="MATH, SCI, ADM" required dir="ltr">
          </div>
          <div class="mb-3">
            <label class="form-label" for="name_en"><?= e(__('hr.dept_name_en')) ?></label>
            <input type="text" class="form-control" id="name_en" name="name_en" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="name_ar"><?= e(__('hr.dept_name_ar')) ?></label>
            <input type="text" class="form-control" id="name_ar" name="name_ar" required dir="rtl">
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
