<?php
/** @var array $years */
$pageTitle = __('academic_years.title');
$activeNav = 'academic-years';
$pageScripts = ['/assets/js/academic-years.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$activeYears = array_filter($years, fn($y) => (int)$y['is_active'] === 1);
$closedYears = array_filter($years, fn($y) => (int)$y['is_closed'] === 1);
$openYears = array_filter($years, fn($y) => (int)$y['is_closed'] === 0);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('calendar', 'n-grades-icon') ?>
        </div>
        <?= e(__('academic_years.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('academic_years.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($years) ?></span>
          <span class="n-stat-mini-label"><?= e(__('academic_years.total_years')) ?></span>
        </div>
        <?php if (count($activeYears) > 0): ?>
        <div class="n-stat-mini success">
          <span class="n-stat-mini-value"><?= count($activeYears) ?></span>
          <span class="n-stat-mini-label"><?= e(__('academic_years.stat_active')) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('academic_years.tab_list')) ?></span>
      <span class="badge"><?= count($years) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('academic_years.tab_add')) ?></span>
    </button>
  </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">
  
  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($years)): ?>
      <!-- EMPTY STATE -->
      <div class="n-empty-powerful">
        <div class="n-empty-powerful-bg">
          <div class="n-empty-blob n-empty-blob-1"></div>
          <div class="n-empty-blob n-empty-blob-2"></div>
          <div class="n-empty-blob n-empty-blob-3"></div>
        </div>
        <div class="n-empty-powerful-content">
          <div class="n-empty-powerful-icon">
            <?= icon('calendar', 'n-icon-massive') ?>
          </div>
          <h2 class="n-empty-powerful-title"><?= e(__('academic_years.empty_title')) ?></h2>
          <p class="n-empty-powerful-description"><?= e(__('academic_years.empty_description')) ?></p>
          <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
            <?= icon('plus', 'n-icon-lg') ?>
            <span><?= e(__('academic_years.add')) ?></span>
          </button>
        </div>
      </div>
    <?php else: ?>
      <!-- ACADEMIC YEARS GRID -->
      <div class="n-grades-grid">
        <?php foreach ($years as $index => $year): ?>
          <div class="n-grade-card <?= (int)$year['is_closed'] === 1 ? 'archived' : '' ?>" 
               style="--card-index: <?= $index ?>;" 
               data-year-id="<?= (int)$year['id'] ?>">
            
            <!-- Card Header -->
            <div class="n-grade-card-header">
              <div class="n-grade-number">
                <?= icon('calendar', 'n-icon-lg') ?>
              </div>
              <div class="n-grade-status">
                <?php if ((int)$year['is_active'] === 1): ?>
                  <span class="badge text-bg-success d-inline-flex align-items-center gap-1">
                    <?= icon('check-circle', 'n-icon-sm') ?>
                    <?= e(__('academic_years.active_badge')) ?>
                  </span>
                <?php endif; ?>
                <?php if ((int)$year['is_closed'] === 1): ?>
                  <span class="badge text-bg-secondary d-inline-flex align-items-center gap-1">
                    <?= icon('lock', 'n-icon-sm') ?>
                    <?= e(__('academic_years.closed_badge')) ?>
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-light border d-inline-flex align-items-center gap-1">
                    <?= icon('unlock', 'n-icon-sm') ?>
                    <?= e(__('academic_years.open_badge')) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Card Body -->
            <div class="n-grade-card-body">
              <div class="n-grade-names">
                <h3 class="n-grade-name-en"><?= e($year['label']) ?></h3>
                <p class="n-grade-name-ar" dir="ltr">
                  <?= e($year['start_date']) ?> → <?= e($year['end_date']) ?>
                </p>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="n-grade-card-footer">
              <?php if ((int)$year['is_active'] !== 1): ?>
                <form method="post" action="/academic-years/<?= (int)$year['id'] ?>/activate" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_activate')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-primary n-btn-card">
                    <?= icon('check-circle', 'n-icon-sm') ?>
                    <span><?= e(__('academic_years.activate')) ?></span>
                  </button>
                </form>
              <?php endif; ?>
              
              <?php if ((int)$year['is_closed'] !== 1): ?>
                <form method="post" action="/academic-years/<?= (int)$year['id'] ?>/close" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_close')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-secondary n-btn-card">
                    <?= icon('lock', 'n-icon-sm') ?>
                    <span class="d-none d-lg-inline"><?= e(__('academic_years.close')) ?></span>
                  </button>
                </form>
              <?php else: ?>
                <form method="post" action="/academic-years/<?= (int)$year['id'] ?>/reopen" class="d-inline"
                      data-confirm="<?= e(__('academic_years.confirm_reopen')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-warning n-btn-card">
                    <?= icon('unarchive', 'n-icon-sm') ?>
                    <span class="d-none d-lg-inline"><?= e(__('academic_years.reopen')) ?></span>
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
          <h2 class="n-form-card-title"><?= e(__('academic_years.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('academic_years.form_add_subtitle')) ?></p>
        </div>
      </div>
      
      <form method="post" action="/academic-years" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="label_new">
            <span><?= e(__('academic_years.label')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" 
                 id="label_new" 
                 name="label" 
                 placeholder="2024-2025"
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="start_date_new">
            <span><?= e(__('academic_years.start_date')) ?></span>
          </label>
          <input type="date" class="form-control n-input-powerful" 
                 id="start_date_new" 
                 name="start_date" 
                 required>
        </div>
        
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="end_date_new">
            <span><?= e(__('academic_years.end_date')) ?></span>
          </label>
          <input type="date" class="form-control n-input-powerful" 
                 id="end_date_new" 
                 name="end_date" 
                 required>
        </div>

        <?php if (!empty($years)): ?>
        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="rollover_from_new">
            <span><?= e(__('academic_years.rollover_option')) ?></span>
          </label>
          <select class="form-select n-input-powerful" id="rollover_from_new" name="rollover_from">
            <option value=""><?= e(__('academic_years.rollover_none')) ?></option>
            <?php foreach ($years as $y): ?>
              <option value="<?= (int)$y['id'] ?>"><?= e($y['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
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

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
