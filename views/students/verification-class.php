<?php
/**
 * Roster verification -- one class against its printed page.
 *
 * @var array<string,mixed> $class
 * @var list<array<string,mixed>> $students
 * @var array<string,mixed>|null $verification
 * @var string $state
 * @var int $carried
 * @var int $missing_attribute
 * @var int $shorthand
 * @var bool $hasPage
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = strtr(__('verification.class_title'), ['{class}' => (string) $class['name']]);
$activeNav = 'roster-verification';
$classId = (int) $class['id'];
$canEdit = hasPermission('students.edit');

ob_start(); ?>
<p class="n-hero-sub">
  <?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?>
  <?php if (!empty($class['pathway_ar'])): ?> — <?= e($class['pathway_ar']) ?><?php endif; ?>
  — <?= (int) count($students) ?> <?= e(__('verification.students')) ?>
</p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/students/verification" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('verification.back')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<?php if ($state === 'changed'): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <div><strong><?= e(__('verification.state_changed')) ?></strong><div class="small mt-1"><?= e(__('verification.changed_help')) ?></div></div>
  </div>
<?php elseif ($state === 'verified'): ?>
  <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
    <?= icon('check-circle', 'n-icon-lg flex-shrink-0') ?>
    <div>
      <strong><?= e(__('verification.state_verified')) ?></strong>
      <div class="small mt-1">
        <?= e(strtr(__('verification.verified_meta'), [
            '{name}' => (string) ($verification['verified_by_name'] ?? '—'),
            '{date}' => !empty($verification['verified_at']) ? date('Y-m-d H:i', strtotime((string) $verification['verified_at'])) : '—',
            '{count}' => (string) ($verification['student_count'] ?? 0),
        ])) ?>
      </div>
      <?php if (!empty($verification['note'])): ?><div class="small mt-1"><?= e(__('verification.note_label')) ?>: <?= e((string) $verification['note']) ?></div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($carried > 0 || $shorthand > 0 || $missing_attribute > 0): ?>
  <div class="alert alert-info d-flex gap-2 align-items-start" role="status">
    <?= icon('info-circle', 'n-icon-lg flex-shrink-0') ?>
    <div class="small">
      <?php if ($carried > 0): ?><div><?= e(strtr(__('verification.carried_notice'), ['{count}' => (string) $carried])) ?></div><?php endif; ?>
      <?php if ($shorthand > 0): ?><div><?= e(strtr(__('verification.shorthand_notice'), ['{count}' => (string) $shorthand])) ?></div><?php endif; ?>
      <?php if ($missing_attribute > 0): ?><div><?= e(strtr(__('verification.missing_notice'), ['{count}' => (string) $missing_attribute])) ?></div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-8">
    <form method="post" action="/students/verification/<?= $classId ?>">
      <?= CsrfMiddleware::field() ?>
      <div class="n-data-panel">
        <div class="table-responsive">
          <table class="table table-hover align-middle n-table-stack mb-0">
            <thead>
              <tr>
                <th style="width:56px;">#</th>
                <th><?= e(__('verification.name_column')) ?></th>
                <th style="width:210px;"><?= e(__('verification.attribute_column')) ?></th>
                <th style="width:130px;"><?= e(__('verification.source_column')) ?></th>
                <th style="width:110px;"><?= e(__('students.religion')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $index => $student): ?>
                <?php $studentId = (int) $student['id']; ?>
                <tr>
                  <td data-label="#" class="text-muted"><?= $index + 1 ?></td>
                  <td data-label="<?= e(__('verification.name_column')) ?>">
                    <?php if ($canEdit): ?>
                      <input type="text" class="form-control" name="name[<?= $studentId ?>]"
                             value="<?= e((string) $student['full_name']) ?>" maxlength="150" required>
                    <?php else: ?>
                      <?= e((string) $student['full_name']) ?>
                    <?php endif; ?>
                  </td>
                  <td data-label="<?= e(__('verification.attribute_column')) ?>">
                    <?php if ($canEdit): ?>
                      <input type="text" class="form-control" name="attribute[<?= $studentId ?>]"
                             value="<?= e((string) ($student['class_attribute_raw'] ?? '')) ?>" maxlength="120">
                    <?php else: ?>
                      <?= e((string) ($student['class_attribute_raw'] ?? '—')) ?>
                    <?php endif; ?>
                  </td>
                  <td data-label="<?= e(__('verification.source_column')) ?>">
                    <?php if (($student['class_attribute_source'] ?? '') === 'printed'): ?>
                      <span class="badge text-bg-success"><?= e(__('verification.source_printed')) ?></span>
                    <?php elseif (($student['class_attribute_source'] ?? '') === 'carried_forward'): ?>
                      <span class="badge text-bg-light" title="<?= e(__('verification.carried_help')) ?>"><?= e(__('verification.source_carried')) ?></span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary"><?= e(__('verification.source_manual')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td data-label="<?= e(__('students.religion')) ?>">
                    <?php if ($student['religion'] === 'muslim'): ?><?= e(__('students.religion_muslim')) ?>
                    <?php elseif ($student['religion'] === 'christian'): ?><?= e(__('students.religion_christian')) ?>
                    <?php elseif ($student['religion'] === 'other'): ?><?= e(__('students.religion_other')) ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($canEdit): ?>
        <div class="d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?><?= e(__('verification.save_corrections')) ?></button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h6 d-flex align-items-center gap-2"><?= icon('file-text') ?><?= e(__('verification.reference_title')) ?></h2>
        <p class="text-muted small mb-3"><?= e(__('verification.page_order_hint')) ?></p>
        <?php if ($hasPage): ?>
          <a href="/students/verification/<?= $classId ?>/page" target="_blank" rel="noopener">
            <img src="/students/verification/<?= $classId ?>/page" alt="<?= e(strtr(__('verification.page_alt'), ['{class}' => (string) $class['name']])) ?>"
                 class="img-fluid border rounded" loading="lazy">
          </a>
          <a href="/students/verification/<?= $classId ?>/page" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm w-100 mt-2">
            <?= icon('external-link') ?><?= e(__('verification.open_page')) ?>
          </a>
        <?php else: ?>
          <div class="alert alert-warning mb-0 small"><?= e(__('verification.reference_missing')) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h2 class="h6 d-flex align-items-center gap-2"><?= icon('check-circle') ?><?= e(__('verification.signoff_title')) ?></h2>
        <p class="text-muted small"><?= e(__('verification.signoff_help')) ?></p>

        <?php if ($canEdit): ?>
          <form method="post" action="/students/verification/<?= $classId ?>/confirm" class="mb-2">
            <?= CsrfMiddleware::field() ?>
            <label class="form-label small" for="verificationNote"><?= e(__('verification.note_label')) ?></label>
            <textarea id="verificationNote" name="note" class="form-control mb-2" rows="2" maxlength="500"
                      placeholder="<?= e(__('verification.note_placeholder')) ?>"></textarea>
            <button type="submit" class="btn btn-success w-100" <?= $missing_attribute > 0 ? 'disabled' : '' ?>>
              <?= icon('check') ?><?= e(__('verification.verify')) ?>
            </button>
          </form>
          <?php if ($missing_attribute > 0): ?>
            <div class="small text-danger mb-2"><?= e(strtr(__('verification.missing_notice'), ['{count}' => (string) $missing_attribute])) ?></div>
          <?php endif; ?>

          <?php if ($state !== 'pending'): ?>
            <form method="post" action="/students/verification/<?= $classId ?>/reopen" class="mt-2">
              <?= CsrfMiddleware::field() ?>
              <button type="submit" class="btn btn-outline-secondary w-100"><?= icon('refresh') ?><?= e(__('verification.reopen')) ?></button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="small text-muted"><?= e(__('verification.no_permission')) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
