<?php
/**
 * @var array    $exams   all exams for the active year
 * @var int|null $yearId
 */
$pageTitle = __('exams.title');
$activeNav = 'exams';
$pageScripts = ['/assets/js/exams.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$byTerm = [];
foreach ($exams as $ex) {
    $byTerm[(int) $ex['term']][] = $ex;
}
ksort($byTerm);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('star', 'n-grades-icon') ?>
        </div>
        <?= e(__('exams.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('exams.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= count($exams) ?></span>
          <span class="n-stat-mini-label"><?= e(__('exams.total')) ?></span>
        </div>
        <?php if (count($byTerm) > 0): ?>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= count($byTerm) ?></span>
          <span class="n-stat-mini-label"><?= e(__('exams.stat_terms')) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <a href="/exams/ranking" class="btn btn-outline-light d-flex align-items-center gap-2">
        <?= icon('trending-up') ?>
        <span><?= e(__('exams.ranking')) ?></span>
      </a>
    </div>
  </div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('exams.tab_list')) ?></span>
      <span class="badge"><?= count($exams) ?></span>
    </button>
  </li>
  <?php if (hasPermission('exams.manage')): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('exams.tab_add')) ?></span>
    </button>
  </li>
  <li class="nav-item d-none" role="presentation" id="edit-tab-wrapper">
    <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button" role="tab">
      <?= icon('edit', 'n-icon-sm') ?>
      <span><?= e(__('exams.tab_edit')) ?></span>
    </button>
  </li>
  <?php endif; ?>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">

  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($exams)): ?>
      <!-- EMPTY STATE -->
      <div class="n-empty-powerful">
        <div class="n-empty-powerful-bg">
          <div class="n-empty-blob n-empty-blob-1"></div>
          <div class="n-empty-blob n-empty-blob-2"></div>
          <div class="n-empty-blob n-empty-blob-3"></div>
        </div>
        <div class="n-empty-powerful-content">
          <div class="n-empty-powerful-icon">
            <?= icon('star', 'n-icon-massive') ?>
          </div>
          <h2 class="n-empty-powerful-title"><?= e(__('exams.empty_title')) ?></h2>
          <p class="n-empty-powerful-description"><?= e(__('exams.empty_description')) ?></p>
          <?php if (hasPermission('exams.manage')): ?>
            <button type="button" class="btn btn-primary btn-lg n-btn-powerful" data-tab-switch="add-tab">
              <?= icon('plus', 'n-icon-lg') ?>
              <span><?= e(__('exams.add')) ?></span>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="n-reveal-group d-flex flex-column gap-3">
        <?php foreach ($byTerm as $term => $termExams): ?>
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
              <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-light border d-inline-flex align-items-center justify-content-center"
                      style="width:28px; height:28px; font-size:0.9rem; color: var(--n-primary-strong);">
                  <?= $term ?>
                </span>
                <span class="fw-bold"><?= e(__('exams.term_n', ['term' => $term])) ?></span>
                <span class="text-muted small">&middot; <?= count($termExams) ?> <?= e(__('exams.exams_count')) ?></span>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= e(__('exams.name')) ?></th>
                    <th><?= e(__('exams.max_score')) ?></th>
                    <th><?= e(__('exams.weight')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($termExams as $ex): ?>
                    <tr>
                      <td class="fw-semibold"><?= e(currentLocale() === 'ar' ? $ex['name_ar'] : $ex['name_en']) ?></td>
                      <td><span class="font-variant-numeric-tabular"><?= e($ex['max_score']) ?></span></td>
                      <td><span class="font-variant-numeric-tabular"><?= e($ex['weight']) ?>%</span></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <a href="/exams/<?= (int) $ex['id'] ?>/scores" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                            <?= icon('edit', 'n-icon-sm') ?>
                            <span class="d-none d-lg-inline"><?= e(__('exams.enter_scores')) ?></span>
                          </a>
                          <?php if (hasPermission('exams.manage')): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-exam-edit
                                    data-exam-id="<?= (int) $ex['id'] ?>"
                                    data-exam-name-en="<?= e($ex['name_en']) ?>"
                                    data-exam-name-ar="<?= e($ex['name_ar']) ?>"
                                    data-exam-term="<?= (int) $ex['term'] ?>"
                                    data-exam-max-score="<?= e($ex['max_score']) ?>"
                                    data-exam-weight="<?= e($ex['weight']) ?>"
                                    aria-label="<?= e(__('common.edit')) ?>">
                              <?= icon('settings', 'n-icon-sm') ?>
                            </button>
                            <form method="post" action="/exams/<?= (int) $ex['id'] ?>/delete" class="d-inline"
                                  data-confirm="<?= e(__('exams.confirm_delete')) ?>">
                              <?= CsrfMiddleware::field() ?>
                              <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.archive')) ?>">
                                <?= icon('trash', 'n-icon-sm') ?>
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (hasPermission('exams.manage')): ?>
  <!-- ADD NEW TAB -->
  <div class="tab-pane fade" id="add-panel" role="tabpanel">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('plus', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('exams.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('exams.form_add_subtitle')) ?></p>
        </div>
      </div>

      <form method="post" action="/exams" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_new">
            <span><?= e(__('exams.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful"
                 id="name_en_new" name="name_en" placeholder="Term 1 Final" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_new">
            <span><?= e(__('exams.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful"
                 id="name_ar_new" name="name_ar" dir="rtl" placeholder="امتحان الفصل الأول" required>
        </div>

        <div class="row g-3">
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="term_new"><span><?= e(__('exams.term')) ?></span></label>
              <input type="number" class="form-control n-input-powerful" id="term_new" name="term" value="1" min="1" max="6" required>
            </div>
          </div>
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="max_score_new"><span><?= e(__('exams.max_score')) ?></span></label>
              <input type="number" class="form-control n-input-powerful" id="max_score_new" name="max_score" value="100" step="0.01" min="1" required>
            </div>
          </div>
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="weight_new"><span><?= e(__('exams.weight')) ?> %</span></label>
              <input type="number" class="form-control n-input-powerful" id="weight_new" name="weight" value="100" step="0.01" min="0" max="100" required>
            </div>
          </div>
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
          <h2 class="n-form-card-title"><?= e(__('exams.tab_edit')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('exams.form_edit_subtitle')) ?></p>
        </div>
      </div>

      <form method="post" action="/exams/" id="editExamForm" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" id="edit_exam_id" name="exam_id">

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_en_edit">
            <span><?= e(__('exams.name_en')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" id="name_en_edit" name="name_en" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="name_ar_edit">
            <span><?= e(__('exams.name_ar')) ?></span>
          </label>
          <input type="text" class="form-control n-input-powerful" id="name_ar_edit" name="name_ar" dir="rtl" required>
        </div>

        <div class="row g-3">
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="term_edit"><span><?= e(__('exams.term')) ?></span></label>
              <input type="number" class="form-control n-input-powerful" id="term_edit" name="term" min="1" max="6" required>
            </div>
          </div>
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="max_score_edit"><span><?= e(__('exams.max_score')) ?></span></label>
              <input type="number" class="form-control n-input-powerful" id="max_score_edit" name="max_score" step="0.01" min="1" required>
            </div>
          </div>
          <div class="col-4">
            <div class="n-form-group-powerful mb-0">
              <label class="n-label-powerful" for="weight_edit"><span><?= e(__('exams.weight')) ?> %</span></label>
              <input type="number" class="form-control n-input-powerful" id="weight_edit" name="weight" step="0.01" min="0" max="100" required>
            </div>
          </div>
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
  <?php endif; ?>

</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
