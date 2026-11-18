<?php
/** @var array $categories @var array $structures @var array $grades */
$pageTitle = __('finance.fee_structures');
$activeNav = 'finance';
ob_start(); ?>
<a href="/finance" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<div class="row g-4">
  <div class="col-lg-7">
    <?php $structures = filterRowsBySearch($structures, (string) ($_GET['q'] ?? ''), ['name_ar', 'name_en', 'grade_name_ar', 'grade_name_en', 'category_name_ar', 'category_name_en']); ?>
    <?php if (!empty($structures)) { $listSearchTarget = '#structures-table'; $listSearchAction = '/finance/structures'; require dirname(__DIR__) . '/partials/list-search.php'; } ?>
    <div class="card">
      <div class="card-header fw-semibold"><?= e(__('finance.active_structures')) ?></div>
      <div class="card-body p-0">
        <?php if (empty($structures)): ?>
          <div class="n-empty py-5">
            <div class="n-empty-icon"><?= icon('layers') ?></div>
            <p class="text-muted small mb-0"><?= e(__('finance.no_structures_yet')) ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table id="structures-table" class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('finance.fee_title')) ?></th>
                  <th><?= e(__('grades.grade')) ?></th>
                  <th><?= e(__('finance.category')) ?></th>
                  <th><?= e(__('finance.amount')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($structures as $st): ?>
                  <tr>
                    <td class="fw-semibold">
                      <?= e(currentLocale() === 'ar' ? $st['name_ar'] : $st['name_en']) ?>
                    </td>
                    <td><?= e(currentLocale() === 'ar' ? $st['grade_name_ar'] : $st['grade_name_en']) ?></td>
                    <td>
                      <span class="badge text-bg-light border">
                        <?= e(currentLocale() === 'ar' ? $st['category_name_ar'] : $st['category_name_en']) ?>
                      </span>
                    </td>
                    <td dir="ltr" class="font-monospace fw-bold text-primary">
                      <?= number_format((float) $st['amount'], 2) ?>
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

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold"><?= e(__('finance.add_fee_structure')) ?></div>
      <div class="card-body">
        <form method="post" action="/finance/structures">
          <?= CsrfMiddleware::field() ?>
          <div class="mb-3">
            <label class="form-label" for="grade_id"><?= e(__('grades.grade')) ?></label>
            <select class="form-select" id="grade_id" name="grade_id" required>
              <?php foreach ($grades as $g): ?>
                <option value="<?= (int) $g['id'] ?>">
                  <?= e(currentLocale() === 'ar' ? $g['name_ar'] : $g['name_en']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="fee_category_id"><?= e(__('finance.category')) ?></label>
            <select class="form-select" id="fee_category_id" name="fee_category_id" required>
              <?php if (empty($categories)): ?>
                <!-- Auto seed default tuition category if empty -->
                <option value="1"><?= e(__('finance.category_tuition')) ?></option>
              <?php endif; ?>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>">
                  <?= e(currentLocale() === 'ar' ? $cat['name_ar'] : $cat['name_en']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="name_en"><?= e(__('finance.name_en')) ?></label>
            <input type="text" class="form-control" id="name_en" name="name_en" required placeholder="Annual Tuition 2026/2027">
          </div>

          <div class="mb-3">
            <label class="form-label" for="name_ar"><?= e(__('finance.name_ar')) ?></label>
            <input type="text" class="form-control" id="name_ar" name="name_ar" required dir="rtl" placeholder="المصروفات الدراسية السنوية">
          </div>

          <div class="mb-3">
            <label class="form-label" for="amount"><?= e(__('finance.amount')) ?></label>
            <input type="number" step="0.01" min="1" class="form-control" id="amount" name="amount" required dir="ltr">
          </div>

          <div class="mb-4">
            <label class="form-label" for="due_date"><?= e(__('finance.due_date')) ?> (<?= e(__('common.optional')) ?>)</label>
            <input type="date" class="form-control" id="due_date" name="due_date">
          </div>

          <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2">
            <?= icon('plus') ?>
            <span><?= e(__('finance.save_structure')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
