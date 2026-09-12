<?php
/** @var array $subjects */
$pageTitle = __('subjects.title');
$activeNav = 'subjects';
$pageScripts = ['/assets/js/subjects.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$activeSubjects = array_filter($subjects, fn($s) => (int)$s['is_active'] === 1);
$archivedSubjects = array_filter($subjects, fn($s) => (int)$s['is_active'] === 0);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('book', 'n-grades-icon') ?>
        </div>
        <?= e(__('subjects.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('subjects.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($activeSubjects) ?></span>
          <span class="n-stat-mini-label"><?= e(__('subjects.stat_active')) ?></span>
        </div>
        <?php if (count($archivedSubjects) > 0): ?>
        <div class="n-stat-mini archived">
          <span class="n-stat-mini-value"><?= count($archivedSubjects) ?></span>
          <span class="n-stat-mini-label"><?= e(__('subjects.stat_archived')) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
        <button type="button" class="btn btn-outline-primary ms-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
        <?= icon("upload", "n-icon-sm") ?>
        <?= e(__("app.import")) ?>
      </button>
</div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('subjects.tab_list')) ?></span>
      <span class="badge"><?= count($subjects) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('subjects.tab_add')) ?></span>
    </button>
  </li>
  <li class="nav-item d-none" role="presentation" id="edit-tab-wrapper">
    <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button" role="tab">
      <?= icon('edit', 'n-icon-sm') ?>
      <span><?= e(__('subjects.tab_edit')) ?></span>
    </button>
  </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">
  
  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($subjects)): ?>
      <!-- EMPTY STATE -->
      <div class="n-empty-powerful">
        <div class="n-empty-powerful-bg">
          <div class="n-empty-blob n-empty-blob-1"></div>
          <div class="n-empty-blob n-empty-blob-2"></div>
          <div class="n-empty-blob n-empty-blob-3"></div>
        </div>
        <div class="n-empty-powerful-content">
          <div class="n-empty-powerful-icon">
            <?= icon('book', 'n-icon-massive') ?>
          </div>
          <h2 class="n-empty-powerful-title"><?= e(__('subjects.empty_title')) ?></h2>
          <p class="n-empty-powerful-description"><?= e(__('subjects.empty_description')) ?></p>
          <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
            <?= icon('plus', 'n-icon-lg') ?>
            <span><?= e(__('subjects.add')) ?></span>
          </button>
        </div>
      </div>
    <?php else: ?>
      <!-- SUBJECTS GRID -->
      <div class="n-grades-grid">
        <?php foreach ($subjects as $index => $subject): ?>
          <div class="n-grade-card <?= (int)$subject['is_active'] === 0 ? 'archived' : '' ?>" 
               style="--card-index: <?= $index ?>;" 
               data-subject-id="<?= (int)$subject['id'] ?>">
            
            <!-- Card Header -->
            <div class="n-grade-card-header">
              <div class="n-grade-number">
                <span class="mono"><?= e($subject['code']) ?></span>
              </div>
              <div class="n-grade-status">
                <?php if ((int)$subject['is_active'] === 1): ?>
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
                <h3 class="n-grade-name-en"><?= e($subject['name_en']) ?></h3>
                <p class="n-grade-name-ar"><?= e($subject['name_ar']) ?></p>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="n-grade-card-footer">
              <button type="button" class="btn btn-sm btn-outline-primary n-btn-card"
                 data-subject-edit
                 data-subject-id="<?= (int)$subject['id'] ?>"
                 data-subject-code="<?= e($subject['code']) ?>"
                 data-subject-name-en="<?= e($subject['name_en']) ?>"
                 data-subject-name-ar="<?= e($subject['name_ar']) ?>">
                <?= icon('edit', 'n-icon-sm') ?>
                <span><?= e(__('common.edit')) ?></span>
              </button>
              
              <?php if ((int)$subject['is_active'] === 1): ?>
                <form method="post" action="/subjects/<?= (int)$subject['id'] ?>/archive" class="d-inline" 
                      data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger n-btn-card">
                    <?= icon('archive', 'n-icon-sm') ?>
                    <span class="d-none d-lg-inline"><?= e(__('common.archive')) ?></span>
                  </button>
                </form>
              <?php else: ?>
                <form method="post" action="/subjects/<?= (int)$subject['id'] ?>/restore" class="d-inline" 
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
          <h2 class="n-form-card-title"><?= e(__('subjects.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('subjects.form_add_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/subjects" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="code_new">
            <span><?= e(__('subjects.code')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="code_new" 
                 name="code" 
                 placeholder="MATH-01"
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_new">
            <span><?= e(__('subjects.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_en_new" 
                 name="name_en" 
                 placeholder="Mathematics"
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_new">
            <span><?= e(__('subjects.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_ar_new" 
                 name="name_ar" 
                 dir="rtl" 
                 placeholder="الرياضيات"
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

  <!-- EDIT TAB -->
  <div class="tab-pane fade" id="edit-panel" role="tabpanel">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('edit', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('subjects.tab_edit')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('subjects.form_edit_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/subjects/" id="editSubjectForm" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" id="edit_subject_id" name="subject_id">
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="code_edit">
            <span><?= e(__('subjects.code')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="code_edit" 
                 name="code" 
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_edit">
            <span><?= e(__('subjects.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_en_edit" 
                 name="name_en" 
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_edit">
            <span><?= e(__('subjects.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="name_ar_edit" 
                 name="name_ar" 
                 dir="rtl" 
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
$title = __("app.import");
$action = "/import/subjects";
$templateUrl = "/import/subjects/template";
require dirname(__DIR__) . "/partials/import_modal.php";
?>