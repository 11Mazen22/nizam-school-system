<?php
/**
 * Student roster CSV import -- preview step. Nothing is written yet.
 *
 * @var string $token
 * @var list<array<string, mixed>> $rows
 * @var array{ok:int, duplicate:int, error:int} $counts
 * @var string $fileName
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('roster.preview_title');
$activeNav = 'students';
$pageSubtitle = $fileName;

ob_start(); ?>
<a href="/students/import/roster" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
  <?= icon('chevron-left', 'n-flip-rtl') ?>
  <span><?= e(__('roster.choose_another')) ?></span>
</a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';

$reasons = [
    'name_required' => 'roster.reason_name',
    'class_unknown' => 'roster.reason_class',
    'gender_invalid' => 'roster.reason_gender',
    'dob_invalid' => 'roster.reason_dob',
    'religion_invalid' => 'roster.reason_religion',
    'no_active_year' => 'roster.reason_year',
    'duplicate_in_file' => 'roster.reason_duplicate_file',
    'already_enrolled' => 'roster.reason_duplicate_db',
];
?>

<div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
  <div class="col">
    <div class="card h-100 n-roster-count n-roster-count-ok">
      <div class="card-body">
        <div class="n-roster-count-value" dir="ltr"><?= (int) $counts['ok'] ?></div>
        <div class="n-roster-count-label"><?= e(__('roster.count_ok')) ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card h-100 n-roster-count n-roster-count-duplicate">
      <div class="card-body">
        <div class="n-roster-count-value" dir="ltr"><?= (int) $counts['duplicate'] ?></div>
        <div class="n-roster-count-label"><?= e(__('roster.count_duplicate')) ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card h-100 n-roster-count n-roster-count-error">
      <div class="card-body">
        <div class="n-roster-count-value" dir="ltr"><?= (int) $counts['error'] ?></div>
        <div class="n-roster-count-label"><?= e(__('roster.count_error')) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="n-data-panel">
  <div class="table-responsive">
    <table class="table table-hover align-middle n-table-stack mb-0">
      <thead>
        <tr>
          <th><?= e(__('roster.col_line')) ?></th>
          <th><?= e(__('roster.col_name')) ?></th>
          <th><?= e(__('roster.col_class')) ?></th>
          <th><?= e(__('roster.col_status')) ?></th>
          <th><?= e(__('roster.col_notes')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td data-label="<?= e(__('roster.col_line')) ?>" class="mono" dir="ltr"><?= (int) $row['line'] ?></td>
            <td data-label="<?= e(__('roster.col_name')) ?>"><strong><?= e((string) $row['name']) ?></strong></td>
            <td data-label="<?= e(__('roster.col_class')) ?>" dir="ltr"><?= e((string) $row['class']) ?></td>
            <td data-label="<?= e(__('roster.col_status')) ?>">
              <?php if ($row['status'] === 'ok'): ?>
                <span class="badge text-bg-success"><?= e(__('roster.status_ok')) ?></span>
              <?php elseif ($row['status'] === 'duplicate'): ?>
                <span class="badge text-bg-warning"><?= e(__('roster.status_duplicate')) ?></span>
              <?php else: ?>
                <span class="badge text-bg-danger"><?= e(__('roster.status_error')) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="<?= e(__('roster.col_notes')) ?>">
              <?php foreach ($row['messages'] as $message): ?>
                <div class="small text-muted"><?= e(__($reasons[$message] ?? 'roster.status_error')) ?></div>
              <?php endforeach; ?>
              <?php if ($row['messages'] === []): ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" action="/students/import/roster/commit" class="d-flex flex-wrap align-items-center gap-2 mt-4">
  <?= CsrfMiddleware::field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" <?= $counts['ok'] === 0 ? 'disabled' : '' ?>>
    <?= icon('check') ?>
    <span><?= strtr(__('roster.commit_button'), ['{count}' => (string) $counts['ok']]) ?></span>
  </button>
  <a href="/students/import/roster" class="btn btn-secondary"><?= e(__('common.cancel')) ?></a>
  <span class="text-muted small"><?= e(__('roster.commit_help')) ?></span>
</form>
