<?php
/**
 * @var array    $exams   all exams for the active year
 * @var int|null $yearId
 */
$pageTitle = __('exams.title');
$activeNav = 'exams';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('star', 'n-grades-icon') ?></div>
        <?= e(__('exams.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('exams.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-stat-mini">
        <span class="n-stat-mini-value"><?= count($exams) ?></span>
        <span class="n-stat-mini-label"><?= e(__('exams.total')) ?></span>
      </div>
      <a href="/exams/ranking" class="btn btn-outline-light ms-2"><?= icon('trending-up') ?> <?= e(__('exams.ranking')) ?></a>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Exams list -->
  <div class="col-lg-8">
    <?php if (empty($exams)): ?>
      <div class="n-empty-state">
        <div class="n-empty-icon"><?= icon('star', 'n-icon-xl') ?></div>
        <h3 class="n-empty-title"><?= e(__('exams.empty_title')) ?></h3>
        <p class="n-empty-description"><?= e(__('exams.empty_description')) ?></p>
      </div>
    <?php else: ?>
      <?php
        $byTerm = [];
        foreach ($exams as $ex) { $byTerm[(int)$ex['term']][] = $ex; }
        ksort($byTerm);
      ?>
      <?php foreach ($byTerm as $term => $termExams): ?>
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-white py-2 fw-semibold">
            <?= e(__('exams.term')) ?> <?= $term ?>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('exams.name')) ?></th>
                  <th><?= e(__('exams.max_score')) ?></th>
                  <th><?= e(__('exams.weight')) ?></th>
                  <th><?= e(__('common.actions')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($termExams as $ex): ?>
                  <tr>
                    <td class="fw-semibold"><?= e(currentLocale() === 'ar' ? $ex['name_ar'] : $ex['name_en']) ?></td>
                    <td><?= e($ex['max_score']) ?></td>
                    <td><?= e($ex['weight']) ?>%</td>
                    <td class="text-nowrap d-flex gap-1">
                      <a href="/exams/<?= (int)$ex['id'] ?>/scores" class="btn btn-sm btn-outline-primary">
                        <?= icon('edit') ?> <?= e(__('exams.enter_scores')) ?>
                      </a>
                      <?php if (hasPermission('exams.manage')): ?>
                        <button class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#editExam<?= (int)$ex['id'] ?>">
                          <?= icon('settings') ?>
                        </button>
                        <form method="post" action="/exams/<?= (int)$ex['id'] ?>/delete"
                              data-confirm="<?= e(__('exams.confirm_delete')) ?>">
                          <?= CsrfMiddleware::field() ?>
                          <button class="btn btn-sm btn-outline-danger"><?= icon('trash') ?></button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <!-- Edit modal -->
                  <div class="modal fade" id="editExam<?= (int)$ex['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                      <form method="post" action="/exams/<?= (int)$ex['id'] ?>/update" class="modal-content">
                        <?= CsrfMiddleware::field() ?>
                        <div class="modal-header"><h5 class="modal-title"><?= e(__('exams.edit')) ?></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body row g-3">
                          <div class="col-12"><label class="form-label"><?= e(__('exams.name_en')) ?></label>
                            <input name="name_en" class="form-control" value="<?= e($ex['name_en']) ?>" required></div>
                          <div class="col-12"><label class="form-label"><?= e(__('exams.name_ar')) ?></label>
                            <input name="name_ar" class="form-control" value="<?= e($ex['name_ar']) ?>" required></div>
                          <div class="col-4"><label class="form-label"><?= e(__('exams.term')) ?></label>
                            <input type="number" name="term" class="form-control" value="<?= (int)$ex['term'] ?>" min="1" max="6"></div>
                          <div class="col-4"><label class="form-label"><?= e(__('exams.max_score')) ?></label>
                            <input type="number" name="max_score" class="form-control" value="<?= e($ex['max_score']) ?>" step="0.01" min="1"></div>
                          <div class="col-4"><label class="form-label"><?= e(__('exams.weight')) ?> %</label>
                            <input type="number" name="weight" class="form-control" value="<?= e($ex['weight']) ?>" step="0.01" min="0" max="100"></div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                          <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                        </div>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Add exam form -->
  <?php if (hasPermission('exams.manage')): ?>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 fw-semibold"><?= icon('plus') ?> <?= e(__('exams.add')) ?></div>
      <div class="card-body">
        <form method="post" action="/exams" class="row g-3">
          <?= CsrfMiddleware::field() ?>
          <div class="col-12">
            <label class="form-label"><?= e(__('exams.name_en')) ?></label>
            <input name="name_en" class="form-control" placeholder="Term 1 Final" required>
          </div>
          <div class="col-12">
            <label class="form-label"><?= e(__('exams.name_ar')) ?></label>
            <input name="name_ar" class="form-control" placeholder="امتحان الفصل الأول" required>
          </div>
          <div class="col-4">
            <label class="form-label"><?= e(__('exams.term')) ?></label>
            <input type="number" name="term" class="form-control" value="1" min="1" max="6">
          </div>
          <div class="col-4">
            <label class="form-label"><?= e(__('exams.max_score')) ?></label>
            <input type="number" name="max_score" class="form-control" value="100" step="0.01" min="1">
          </div>
          <div class="col-4">
            <label class="form-label"><?= e(__('exams.weight')) ?> %</label>
            <input type="number" name="weight" class="form-control" value="100" step="0.01" min="0" max="100">
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100"><?= icon('plus') ?> <?= e(__('exams.add')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
