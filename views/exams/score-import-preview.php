<?php
/**
 * Exam score import -- step 2: per-row validation verdict and commit.
 *
 * @var array $exam
 * @var int $classId
 * @var string $className
 * @var string $token
 * @var list<array<string,mixed>> $rows
 * @var array{ok:int,skipped:int,error:int} $counts
 * @var string $fileName
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('exams.import_preview_title');
$activeNav = 'exams';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('exams.import_file')) ?>: <?= e($fileName) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/exams/<?= (int) $exam['id'] ?>/scores/import?class_id=<?= $classId ?>" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?> <?= e(__('common.back')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="d-flex flex-wrap gap-2 mb-4">
  <span class="badge text-bg-success"><?= e(__('exams.import_ok')) ?>: <?= (int) $counts['ok'] ?></span>
  <span class="badge text-bg-secondary"><?= e(__('exams.import_skipped')) ?>: <?= (int) $counts['skipped'] ?></span>
  <span class="badge text-bg-danger"><?= e(__('exams.import_error')) ?>: <?= (int) $counts['error'] ?></span>
</div>

<?php if ($counts['error'] > 0): ?>
  <div class="alert alert-danger d-flex gap-2 mb-4">
    <?= icon('alert-triangle', 'flex-shrink-0') ?>
    <span><?= e(__('exams.import_has_errors')) ?></span>
  </div>
<?php else: ?>
  <div class="n-settings-card mb-4">
    <div class="n-settings-card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
      <span><?= e(__('exams.import_commit_hint')) ?></span>
      <form method="post" action="/exams/<?= (int) $exam['id'] ?>/scores/import/commit" data-loading-submit>
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-primary d-flex align-items-center gap-2">
          <?= icon('check') ?><span><?= e(__('exams.import_commit')) ?></span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th><?= e(__('exams.import_row')) ?></th>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('exams.subject')) ?></th>
          <th><?= e(__('exams.score')) ?></th>
          <th><?= e(__('common.notes')) ?></th>
          <th><?= e(__('exams.import_verdict')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
          $status = (string) $row['status'];
          $badge = match ($status) {
              'ok' => 'text-bg-success',
              'skipped' => 'text-bg-secondary',
              default => 'text-bg-danger',
          };
          ?>
          <tr>
            <td class="text-muted small"><?= (int) $row['line'] ?></td>
            <td class="small"><?= e($className) ?></td>
            <td class="fw-semibold"><?= e((string) $row['student_name']) ?></td>
            <td><?= e((string) $row['subject_name']) ?></td>
            <td class="mono"><?= $row['score'] !== null ? e((string) $row['score']) : '—' ?></td>
            <td class="small"><?= e((string) ($row['notes'] ?? '')) ?></td>
            <td>
              <span class="badge <?= $badge ?>"><?= e(__('exams.import_status_' . $status)) ?></span>
              <?php foreach ($row['messages'] as $message): ?>
                <div class="small text-danger"><?= e(__('exams.import_msg_' . (string) $message)) ?></div>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
