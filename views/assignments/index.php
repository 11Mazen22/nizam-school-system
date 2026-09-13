<?php
/** @var array $assignments @var bool $hasActiveYear @var array $archivedCount @var array $teachers @var array $subjects @var array $classes */
$pageTitle = __('assignments.title');
$activeNav = 'assignments';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if (!$hasActiveYear): ?>
  <div class="n-empty-powerful" style="min-height: 400px;">
    <div class="n-empty-powerful-bg">
      <div class="n-empty-blob n-empty-blob-1"></div>
      <div class="n-empty-blob n-empty-blob-2"></div>
      <div class="n-empty-blob n-empty-blob-3"></div>
    </div>
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('calendar', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('assignments.no_active_year_title')) ?></h2>
      <p class="n-empty-powerful-description"><?= e(__('assignments.no_active_year')) ?></p>
      <a href="/academic-years" class="btn btn-primary btn-lg n-btn-powerful">
        <?= icon('calendar', 'n-icon-lg') ?>
        <span><?= e(__('academic_years.title')) ?></span>
      </a>
    </div>
  </div>
<?php else: ?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('clipboard-list', 'n-grades-icon') ?>
        </div>
        <?= e(__('assignments.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('assignments.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($assignments) ?></span>
          <span class="n-stat-mini-label"><?= e(__('assignments.total_active')) ?></span>
        </div>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count(array_unique(array_column($assignments, 'teacher_id'))) ?></span>
          <span class="n-stat-mini-label"><?= e(__('assignments.teachers_count')) ?></span>
        </div>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= array_sum(array_column($assignments, 'weekly_periods')) ?></span>
          <span class="n-stat-mini-label"><?= e(__('assignments.total_periods')) ?></span>
        </div>
      </div>
      <button type="button" class="btn btn-outline-light d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
        <?= icon("upload") ?>
        <span><?= e(__("app.import")) ?></span>
      </button>
    </div>
  </div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('assignments.tab_list')) ?></span>
      <span class="badge"><?= count($assignments) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('assignments.tab_add')) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <a href="/assignments/workload" class="nav-link">
      <?= icon('chart', 'n-icon-sm') ?>
      <span><?= e(__('assignments.workload_summary')) ?></span>
    </a>
  </li>
  <?php if ($archivedCount > 0): ?>
  <li class="nav-item" role="presentation">
    <a href="/assignments/archived" class="nav-link">
      <?= icon('archive', 'n-icon-sm') ?>
      <span><?= e(__('common.archived_list')) ?></span>
      <span class="badge"><?= $archivedCount ?></span>
    </a>
  </li>
  <?php endif; ?>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">
  
  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($assignments)): ?>
      <!-- EMPTY STATE -->
      <div class="n-empty-powerful">
        <div class="n-empty-powerful-bg">
          <div class="n-empty-blob n-empty-blob-1"></div>
          <div class="n-empty-blob n-empty-blob-2"></div>
          <div class="n-empty-blob n-empty-blob-3"></div>
        </div>
        <div class="n-empty-powerful-content">
          <div class="n-empty-powerful-icon">
            <?= icon('clipboard-list', 'n-icon-massive') ?>
          </div>
          <h2 class="n-empty-powerful-title"><?= e(__('assignments.empty_title')) ?></h2>
          <p class="n-empty-powerful-description"><?= e(__('assignments.empty_description')) ?></p>
          <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
            <?= icon('plus', 'n-icon-lg') ?>
            <span><?= e(__('assignments.add')) ?></span>
          </button>
        </div>
      </div>
    <?php else: ?>
      <!-- ASSIGNMENTS GRID -->
      <div class="n-grades-grid">
        <?php foreach ($assignments as $index => $a): ?>
          <div class="n-grade-card" 
               style="--card-index: <?= $index ?>;" 
               data-assignment-id="<?= (int)$a['id'] ?>">
            
            <!-- Card Header -->
            <div class="n-grade-card-header">
              <div class="n-avatar" style="width: 40px; height: 40px;">
                <?= e(mb_substr($a['teacher_name'], 0, 1)) ?>
              </div>
              <div class="n-grade-status">
                <span class="badge text-bg-primary">
                  <?= e(currentLocale() === 'ar' ? $a['subject_name_ar'] : $a['subject_name_en']) ?>
                </span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="n-grade-card-body">
              <div class="n-grade-names">
                <h3 class="n-grade-name-en"><?= e($a['teacher_name']) ?></h3>
                <p class="n-grade-name-ar">
                  <?= e(currentLocale() === 'ar' ? $a['grade_name_ar'] : $a['grade_name_en']) ?> - <?= e($a['class_name']) ?>
                </p>
              </div>
              
              <!-- Inline periods editor -->
              <form method="post" action="/assignments/<?= (int)$a['id'] ?>" class="mt-3">
                <?= CsrfMiddleware::field() ?>
                <div class="d-flex align-items-center gap-2">
                  <label class="form-label mb-0 small text-muted"><?= e(__('assignments.weekly_periods')) ?>:</label>
                  <input type="number" name="weekly_periods" value="<?= (int)$a['weekly_periods'] ?>" 
                         min="1" max="30" class="form-control form-control-sm" style="width: 70px;">
                  <button type="submit" class="btn btn-sm btn-outline-primary" title="<?= e(__('common.save')) ?>">
                    <?= icon('check', 'n-icon-sm') ?>
                  </button>
                </div>
              </form>
            </div>

            <!-- Card Footer -->
            <div class="n-grade-card-footer">
              <form method="post" action="/assignments/<?= (int)$a['id'] ?>/archive" class="w-100" 
                    data-confirm="<?= e(__('assignments.confirm_archive')) ?>">
                <?= CsrfMiddleware::field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger n-btn-card w-100">
                  <?= icon('archive', 'n-icon-sm') ?>
                  <span><?= e(__('common.archive')) ?></span>
                </button>
              </form>
            </div>

            <!-- Decorative gradient -->
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
          <h2 class="n-form-card-title"><?= e(__('assignments.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('assignments.form_add_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/assignments" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="teacher_id_new">
            <span><?= e(__('assignments.teacher')) ?></span>
          </label>
          <select class="form-select n-input-powerful" id="teacher_id_new" name="teacher_id" required>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= (int)$teacher['id'] ?>"><?= e($teacher['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="subject_id_new">
            <span><?= e(__('assignments.subject')) ?></span>
          </label>
          <select class="form-select n-input-powerful" id="subject_id_new" name="subject_id" required>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int)$subject['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="class_id_new">
            <span><?= e(__('assignments.class')) ?></span>
          </label>
          <select class="form-select n-input-powerful" id="class_id_new" name="class_id" required>
            <?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?> - <?= e($class['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="weekly_periods_new">
            <span><?= e(__('assignments.weekly_periods')) ?></span>
          </label>
          <input type="number" class="form-control n-input-powerful" 
                 id="weekly_periods_new" 
                 name="weekly_periods" 
                 min="1" 
                 max="30"
                 value="5"
                 required>
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

</div>

<?php endif; ?>

<?php
ob_start();
$title = __("app.import");
$action = "/import/assignments";
$templateUrl = "/import/assignments/template";
require dirname(__DIR__) . "/partials/import_modal.php";
$pageModals = ob_get_clean();
require dirname(__DIR__) . '/layout/end.php';
?>