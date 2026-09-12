<?php
/** @var array $grades */
$pageTitle = __('grades.title');
$activeNav = 'grades';
$pageScripts = ['/assets/js/grades.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$activeGrades = array_filter($grades, fn($g) => (int)$g['is_active'] === 1);
$archivedGrades = array_filter($grades, fn($g) => (int)$g['is_active'] === 0);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('layers', 'n-grades-icon') ?>
        </div>
        <?= e(__('grades.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('grades.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($activeGrades) ?></span>
          <span class="n-stat-mini-label"><?= e(__('grades.stat_active')) ?></span>
        </div>
        <?php if (count($archivedGrades) > 0): ?>
        <div class="n-stat-mini archived">
          <span class="n-stat-mini-value"><?= count($archivedGrades) ?></span>
          <span class="n-stat-mini-label"><?= e(__('grades.stat_archived')) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-outline-primary ms-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
        <?= icon("upload", "n-icon-sm") ?>
        <?= e(__("app.import")) ?>
      </button>
    </div>
  </div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('grades.tab_list')) ?></span>
      <span class="badge"><?= count($grades) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('grades.tab_add')) ?></span>
    </button>
  </li>
  <li class="nav-item d-none" role="presentation" id="edit-tab-wrapper">
    <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button" role="tab">
      <?= icon('edit', 'n-icon-sm') ?>
      <span><?= e(__('grades.tab_edit')) ?></span>
    </button>
  </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">
  
  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($grades)): ?>
      <!-- EMPTY STATE -->
      <div class="n-empty-powerful">
        <div class="n-empty-powerful-bg">
          <div class="n-empty-blob n-empty-blob-1"></div>
          <div class="n-empty-blob n-empty-blob-2"></div>
          <div class="n-empty-blob n-empty-blob-3"></div>
        </div>
        <div class="n-empty-powerful-content">
          <div class="n-empty-powerful-icon">
            <?= icon('layers', 'n-icon-massive') ?>
          </div>
          <h2 class="n-empty-powerful-title"><?= e(__('grades.empty_title')) ?></h2>
          <p class="n-empty-powerful-description"><?= e(__('grades.empty_description')) ?></p>
          <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
            <?= icon('plus', 'n-icon-lg') ?>
            <span><?= e(__('grades.add')) ?></span>
          </button>
        </div>
      </div>
    <?php else: ?>
      <!-- GRADES GRID -->
      <div class="n-grades-grid">
        <?php foreach ($grades as $index => $grade): ?>
          <div class="n-grade-card <?= (int)$grade['is_active'] === 0 ? 'archived' : '' ?>" 
               style="--card-index: <?= $index ?>;" 
               data-grade-id="<?= (int)$grade['id'] ?>">
            
            <!-- Card Header -->
            <div class="n-grade-card-header">
              <div class="n-grade-number">
                <span><?= (int)$grade['sort_order'] ?></span>
              </div>
              <div class="n-grade-status">
                <?php if ((int)$grade['is_active'] === 1): ?>
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
            </div>

            <!-- Card Body -->
            <div class="n-grade-card-body">
              <div class="n-grade-names">
                <h3 class="n-grade-name-en"><?= e($grade['name_en']) ?></h3>
                <p class="n-grade-name-ar"><?= e($grade['name_ar']) ?></p>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="n-grade-card-footer">
              <button type="button" class="btn btn-sm btn-outline-primary n-btn-card"
                 data-grade-edit
                 data-grade-id="<?= (int)$grade['id'] ?>"
                 data-grade-name-en="<?= e($grade['name_en']) ?>"
                 data-grade-name-ar="<?= e($grade['name_ar']) ?>"
                 data-grade-sort-order="<?= (int)$grade['sort_order'] ?>">
                <?= icon('edit', 'n-icon-sm') ?>
                <span><?= e(__('common.edit')) ?></span>
              </button>
              
              <?php if ((int)$grade['is_active'] === 1): ?>
                <form method="post" action="/grades/<?= (int)$grade['id'] ?>/archive" class="d-inline" 
                      data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger n-btn-card">
                    <?= icon('archive', 'n-icon-sm') ?>
                    <span class="d-none d-lg-inline"><?= e(__('common.archive')) ?></span>
                  </button>
                </form>
              <?php else: ?>
                <form method="post" action="/grades/<?= (int)$grade['id'] ?>/restore" class="d-inline" 
                      data-confirm="<?= e(__('common.confirm_restore')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-success n-btn-card">
                    <?= icon('unarchive', 'n-icon-sm') ?>
                    <span class="d-none d-lg-inline"><?= e(__('common.restore')) ?></span>
                  </button>
                </form>
              <?php endif; ?>
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
          <h2 class="n-form-card-title"><?= e(__('grades.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('grades.form_add_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/grades" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_new">
            <span><?= e(__('grades.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_en_new" 
                 name="name_en" 
                 placeholder="First Grade"
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_new">
            <span><?= e(__('grades.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_ar_new" 
                 name="name_ar" 
                 dir="rtl" 
                 placeholder="الصف الأول"
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="sort_order_new">
            <span><?= e(__('grades.sort_order')) ?></span>
          </label>
          <input type="number" class="form-control n-input-powerful" 
                 id="sort_order_new" 
                 name="sort_order" 
                 value="<?= count($grades) + 1 ?>" 
                 min="1" 
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

  <div class="tab-pane fade" id="edit-panel" role="tabpanel">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('edit', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('grades.tab_edit')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('grades.form_edit_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/grades/" id="editGradeForm" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" id="edit_grade_id" name="grade_id">
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_edit">
            <span><?= e(__('grades.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_en_edit" 
                 name="name_en" 
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_edit">
            <span><?= e(__('grades.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_ar_edit" 
                 name="name_ar" 
                 dir="rtl" 
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="sort_order_edit">
            <span><?= e(__('grades.sort_order')) ?></span>
          </label>
          <input type="number" class="form-control n-input-powerful" 
                 id="sort_order_edit" 
                 name="sort_order" 
                 min="1" 
                 required>
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

<?php require dirname(__DIR__) . '/layout/end.php'; ?>


<?php
$title = __("app.import") . " - " . __("grades.title");
$action = "/import/grades";
$templateUrl = "/import/grades/template";
require dirname(__DIR__) . "/partials/import_modal.php";
?>