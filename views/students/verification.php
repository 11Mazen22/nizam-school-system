<?php
/**
 * Roster verification -- class list.
 *
 * @var list<array<string,mixed>> $classes
 * @var string|null $activeYear
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('verification.title');
$activeNav = 'roster-verification';

$verified = count(array_filter($classes, static fn (array $class): bool => $class['state'] === 'verified'));
$changed = count(array_filter($classes, static fn (array $class): bool => $class['state'] === 'changed'));
$pending = count(array_filter($classes, static fn (array $class): bool => $class['state'] === 'pending'));
$students = array_sum(array_column($classes, 'student_count'));
$carried = array_sum(array_column($classes, 'carried_count'));

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('verification.subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/students" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('students.title')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="n-stat-mini"><span class="n-stat-mini-value"><?= $students ?></span><span class="n-stat-mini-label"><?= e(__('verification.summary_students')) ?></span></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="n-stat-mini"><span class="n-stat-mini-value text-success"><?= $verified ?></span><span class="n-stat-mini-label"><?= e(__('verification.summary_verified')) ?></span></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="n-stat-mini"><span class="n-stat-mini-value text-warning"><?= $changed ?></span><span class="n-stat-mini-label"><?= e(__('verification.summary_changed')) ?></span></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="n-stat-mini"><span class="n-stat-mini-value text-secondary"><?= $pending ?></span><span class="n-stat-mini-label"><?= e(__('verification.summary_pending')) ?></span></div>
    </div></div>
  </div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" role="status">
  <?= icon('info-circle', 'n-icon-lg flex-shrink-0') ?>
  <div>
    <strong><?= e(__('verification.rule_title')) ?></strong>
    <div class="small mt-1"><?= e(strtr(__('verification.rule_text'), ['{count}' => (string) $carried])) ?></div>
  </div>
</div>

<?php if (empty($classes)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('clipboard-check', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('verification.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e(__('verification.empty_text')) ?></p>
  </div>
<?php else: ?>
  <?php $classes = filterRowsBySearch($classes, (string) ($_GET['q'] ?? ''), ['name', 'pathway_ar', 'pathway_en']); ?>
  <?php $listSearchTarget = '#verification-table'; $listSearchAction = '/students/verification'; require dirname(__DIR__) . '/partials/list-search.php'; ?>
  <div class="n-data-panel">
    <div class="table-responsive">
      <table id="verification-table" class="table table-hover align-middle n-table-stack">
        <thead>
          <tr>
            <th><?= e(__('verification.class')) ?></th>
            <th><?= e(__('verification.grade')) ?></th>
            <th class="text-center"><?= e(__('verification.students')) ?></th>
            <th class="text-center"><?= e(__('verification.carried_short')) ?></th>
            <th><?= e(__('verification.status')) ?></th>
            <th><?= e(__('verification.last_check')) ?></th>
            <th class="text-end"><?= e(__('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classes as $class): ?>
            <?php
            $state = (string) $class['state'];
            $badge = match ($state) {
                'verified' => 'success',
                'changed' => 'warning',
                default => 'secondary',
            };
            $stateLabel = match ($state) {
                'verified' => __('verification.state_verified'),
                'changed' => __('verification.state_changed'),
                default => __('verification.state_pending'),
            };
            ?>
            <tr>
              <td data-label="<?= e(__('verification.class')) ?>">
                <a href="/students/verification/<?= (int) $class['id'] ?>" class="fw-semibold"><?= e($class['name']) ?></a>
                <?php if (!empty($class['pathway_ar'])): ?><br><small class="text-muted"><?= e($class['pathway_ar']) ?></small><?php endif; ?>
              </td>
              <td data-label="<?= e(__('verification.grade')) ?>"><?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?></td>
              <td data-label="<?= e(__('verification.students')) ?>" class="text-center"><?= (int) $class['student_count'] ?></td>
              <td data-label="<?= e(__('verification.carried_short')) ?>" class="text-center">
                <?php if ((int) $class['carried_count'] > 0): ?>
                  <span class="badge text-bg-light" title="<?= e(__('verification.carried_help')) ?>"><?= (int) $class['carried_count'] ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('verification.status')) ?>"><span class="badge text-bg-<?= $badge ?>"><?= e($stateLabel) ?></span></td>
              <td data-label="<?= e(__('verification.last_check')) ?>">
                <?php if (!empty($class['verified_at'])): ?>
                  <span class="small"><?= e((string) $class['verified_by_name']) ?><br><span class="text-muted" dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $class['verified_at']))) ?></span></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(__('common.actions')) ?>">
                <div class="n-table-actions justify-content-end">
                  <a href="/students/verification/<?= (int) $class['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= icon('eye') ?><?= e(__('verification.open')) ?></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
