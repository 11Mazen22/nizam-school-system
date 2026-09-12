<?php
/** @var array $classes @var bool $hasActiveYear @var array $grades */
$pageTitle = __('classes.title');
$activeNav = 'classes';
$pageScripts = ['/assets/js/classes.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$totalEnrolled = array_sum(array_column($classes, 'enrolled'));
$totalCapacity = array_sum(array_filter(array_column($classes, 'capacity')));
$utilizationPercent = $totalCapacity > 0 ? (int) round(($totalEnrolled / $totalCapacity) * 100) : 0;
?>

<?php if (!$hasActiveYear): ?>
  <div class="n-empty" style="min-height: 400px;">
    <div class="n-empty-icon"><?= icon('calendar') ?></div>
    <h3><?= e(__('classes.no_active_year_title')) ?></h3>
    <p><?= e(__('classes.no_active_year')) ?></p>
    <a href="/academic-years" class="btn btn-primary"><?= e(__('academic_years.title')) ?></a>
  </div>
<?php else: ?>

  <!-- POWERFUL PAGE HEADER -->
  <div class="n-grades-header">
    <div class="n-grades-header-content">
      <div class="n-grades-header-text">
        <h1 class="n-grades-title">
          <div class="n-grades-icon-wrapper">
            <?= icon('chalkboard', 'n-grades-icon') ?>
          </div>
          <?= e(__('classes.title')) ?>
        </h1>
        <p class="n-grades-subtitle"><?= e(__('classes.description')) ?></p>
      </div>
      <div class="n-grades-header-actions">
        <div class="n-grades-stats-mini">
          <div class="n-stat-mini">
            <span class="n-stat-mini-value"><?= count($classes) ?></span>
            <span class="n-stat-mini-label"><?= e(__('classes.total_classes')) ?></span>
          </div>
          <div class="n-stat-mini">
            <span class="n-stat-mini-value"><?= $totalEnrolled ?></span>
            <span class="n-stat-mini-label"><?= e(__('classes.total_students')) ?></span>
          </div>
          <div class="n-stat-mini">
            <span class="n-stat-mini-value"><?= $utilizationPercent ?>%</span>
            <span class="n-stat-mini-label"><?= e(__('classes.utilization')) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TABS NAVIGATION -->
  <ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
        <?= icon('list', 'n-icon-sm') ?>
        <span><?= e(__('classes.tab_list')) ?></span>
        <span class="badge"><?= count($classes) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
        <?= icon('plus', 'n-icon-sm') ?>
        <span><?= e(__('classes.add')) ?></span>
      </button>
    </li>
    <li class="nav-item d-none" role="presentation" id="edit-tab-wrapper">
      <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button" role="tab">
        <?= icon('edit', 'n-icon-sm') ?>
        <span><?= e(__('classes.edit')) ?></span>
      </button>
    </li>
  </ul>

  <!-- TAB CONTENT -->
  <div class="tab-content">

    <!-- LIST TAB -->
    <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
      <?php if (empty($classes)): ?>
        <div class="n-empty-powerful">
          <div class="n-empty-powerful-bg">
            <div class="n-empty-blob n-empty-blob-1"></div>
            <div class="n-empty-blob n-empty-blob-2"></div>
            <div class="n-empty-blob n-empty-blob-3"></div>
          </div>
          <div class="n-empty-powerful-content">
            <div class="n-empty-powerful-icon">
              <?= icon('chalkboard', 'n-icon-massive') ?>
            </div>
            <h2 class="n-empty-powerful-title"><?= e(__('classes.empty_title')) ?></h2>
            <p class="n-empty-powerful-description"><?= e(__('classes.empty_description')) ?></p>
            <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
              <?= icon('plus', 'n-icon-lg') ?>
              <span><?= e(__('classes.add')) ?></span>
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="n-grades-grid">
          <?php foreach ($classes as $index => $class): ?>
            <?php
              $gradeLabel = currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en'];
              $percent = $class['capacity'] ? (int) round(($class['enrolled'] / $class['capacity']) * 100) : null;
              $barClass = $percent === null ? '' : ($percent >= 90 ? 'danger' : ($percent >= 70 ? 'warning' : 'success'));
            ?>
            <div class="n-grade-card <?= (int) $class['is_active'] === 0 ? 'archived' : '' ?>"
                 style="--card-index: <?= $index ?>;"
                 data-class-id="<?= (int) $class['id'] ?>">

              <div class="n-grade-card-header">
                <div class="n-grade-number"><?= icon('chalkboard', 'n-icon-lg') ?></div>
                <?php if ((int) $class['is_active'] === 1): ?>
                  <span class="badge text-bg-success d-inline-flex align-items-center gap-1">
                    <?= icon('check-circle', 'n-icon-sm') ?>
                    <?= e(__('common.status_active')) ?>
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary d-inline-flex align-items-center gap-1">
                    <?= icon('archive', 'n-icon-sm') ?>
                    <?= e(__('common.status_archived')) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="n-grade-card-body">
                <div class="n-grade-names">
                  <h3 class="n-grade-name-en"><?= e($class['name']) ?></h3>
                  <p class="n-grade-name-ar"><?= e($gradeLabel) ?></p>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2">
                  <?= icon('users', 'n-icon text-body-secondary') ?>
                  <span class="fw-semibold font-variant-numeric-tabular"><?= (int) $class['enrolled'] ?></span>
                  <?php if ($class['capacity']): ?>
                    <span class="text-body-secondary small">/ <?= (int) $class['capacity'] ?></span>
                    <div class="progress ms-auto" style="width: 70px; height: 6px;">
                      <div class="progress-bar bg-<?= $barClass ?>" style="width: <?= min($percent, 100) ?>%"></div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="n-grade-card-footer">
                <button type="button" class="btn btn-sm btn-outline-primary n-btn-card"
                   data-class-edit
                   data-class-id="<?= (int) $class['id'] ?>"
                   data-class-name="<?= e($class['name']) ?>"
                   data-class-capacity="<?= e((string) ($class['capacity'] ?? '')) ?>"
                   data-class-grade-label="<?= e($gradeLabel) ?>">
                  <?= icon('edit', 'n-icon-sm') ?>
                  <span><?= e(__('common.edit')) ?></span>
                </button>

                <?php if ((int) $class['is_active'] === 1): ?>
                  <form method="post" action="/classes/<?= (int) $class['id'] ?>/archive" class="d-inline"
                        data-confirm="<?= e(__('common.confirm_archive')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger n-btn-card">
                      <?= icon('archive', 'n-icon-sm') ?>
                      <span class="d-none d-lg-inline"><?= e(__('common.archive')) ?></span>
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" action="/classes/<?= (int) $class['id'] ?>/restore" class="d-inline"
                        data-confirm="<?= e(__('common.confirm_restore')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success n-btn-card">
                      <?= icon('unarchive', 'n-icon-sm') ?>
                      <span class="d-none d-lg-inline"><?= e(__('common.restore')) ?></span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>

              <div class="n-grade-card-gradient"></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ADD NEW TAB -->
    <div class="tab-pane fade" id="add-panel" role="tabpanel">
      <div class="n-form-card-powerful">
        <div class="n-form-card-header">
          <div class="n-form-card-icon">
            <?= icon('plus', 'n-icon-lg') ?>
          </div>
          <div>
            <h2 class="n-form-card-title"><?= e(__('classes.add')) ?></h2>
            <p class="n-form-card-subtitle"><?= e(__('classes.form_add_subtitle')) ?></p>
          </div>
        </div>

        <form method="post" action="/classes" class="n-form-powerful">
          <?= CsrfMiddleware::field() ?>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="grade_id_new"><span><?= e(__('classes.grade')) ?></span></label>
            <select class="form-select n-input-powerful" id="grade_id_new" name="grade_id" required>
              <?php foreach ($grades as $grade): ?>
                <option value="<?= (int) $grade['id'] ?>">
                  <?= e(currentLocale() === 'ar' ? $grade['name_ar'] : $grade['name_en']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="name_new"><span><?= e(__('classes.name')) ?></span></label>
            <input type="text" class="form-control n-input-powerful" id="name_new" name="name" placeholder="A" required>
          </div>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="capacity_new"><span><?= e(__('classes.capacity')) ?> (<?= e(__('common.optional')) ?>)</span></label>
            <input type="number" class="form-control n-input-powerful" id="capacity_new" name="capacity" min="1">
          </div>

          <div class="n-form-actions">
            <button type="button" class="btn btn-secondary n-btn-powerful-secondary" data-tab-switch="list-tab">
              <?= icon('close') ?>
              <span><?= e(__('common.cancel')) ?></span>
            </button>
            <button type="submit" class="btn btn-primary n-btn-powerful">
              <?= icon('check') ?>
              <span><?= e(__('common.save')) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT TAB -->
    <div class="tab-pane fade" id="edit-panel" role="tabpanel">
      <div class="n-form-card-powerful">
        <div class="n-form-card-header">
          <div class="n-form-card-icon">
            <?= icon('edit', 'n-icon-lg') ?>
          </div>
          <div>
            <h2 class="n-form-card-title"><?= e(__('classes.edit')) ?></h2>
            <p class="n-form-card-subtitle"><?= e(__('classes.form_edit_subtitle')) ?></p>
          </div>
        </div>

        <form method="post" action="/classes/" id="editClassForm" class="n-form-powerful">
          <?= CsrfMiddleware::field() ?>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="grade_id_edit"><span><?= e(__('classes.grade')) ?></span></label>
            <input type="text" class="form-control n-input-powerful" id="grade_id_edit" disabled>
          </div>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="name_edit"><span><?= e(__('classes.name')) ?></span></label>
            <input type="text" class="form-control n-input-powerful" id="name_edit" name="name" required>
          </div>

          <div class="n-form-group-powerful">
            <label class="n-label-powerful" for="capacity_edit"><span><?= e(__('classes.capacity')) ?> (<?= e(__('common.optional')) ?>)</span></label>
            <input type="number" class="form-control n-input-powerful" id="capacity_edit" name="capacity" min="1">
          </div>

          <div class="n-form-actions">
            <button type="button" class="btn btn-secondary n-btn-powerful-secondary" data-tab-switch="list-tab">
              <?= icon('close') ?>
              <span><?= e(__('common.cancel')) ?></span>
            </button>
            <button type="submit" class="btn btn-primary n-btn-powerful">
              <?= icon('check') ?>
              <span><?= e(__('common.update')) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>

<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
