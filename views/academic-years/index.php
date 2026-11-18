<?php
/** @var array $years */
$pageTitle = __('academic_years.title');
$activeNav = 'academic-years';
$canManage = hasPermission('academic_years.manage');
$canClose = hasPermission('academic_years.close');
$years = filterRowsBySearch($years, (string) ($_GET['q'] ?? ''), ['label', 'start_date', 'end_date']);
$activeYears = array_filter($years, static fn (array $year): bool => (int) $year['is_active'] === 1);
$closedYears = array_filter($years, static fn (array $year): bool => (int) $year['is_closed'] === 1);
$openYears = array_filter($years, static fn (array $year): bool => (int) $year['is_closed'] === 0);
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('academic_years.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-grades-stats-mini"><div class="n-stat-mini"><span class="n-stat-mini-value"><?= count($years) ?></span><span class="n-stat-mini-label"><?= e(__('academic_years.total_years')) ?></span></div><div class="n-stat-mini success"><span class="n-stat-mini-value"><?= count($activeYears) ?></span><span class="n-stat-mini-label"><?= e(__('academic_years.stat_active')) ?></span></div><div class="n-stat-mini archived"><span class="n-stat-mini-value"><?= count($closedYears) ?></span><span class="n-stat-mini-label"><?= e(__('academic_years.stat_closed')) ?></span></div></div>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<ul class="nav nav-tabs mb-4" role="tablist"><li class="nav-item"><button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button"><?= icon('list') ?><?= e(__('academic_years.tab_list')) ?><span class="badge"><?= count($years) ?></span></button></li><?php if ($canManage): ?><li class="nav-item"><button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button"><?= icon('plus') ?><?= e(__('academic_years.tab_add')) ?></button></li><?php endif; ?></ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (!empty($years)) { $listSearchTarget = '.n-year-list'; $listSearchAction = '/academic-years'; require dirname(__DIR__) . '/partials/list-search.php'; } ?>
    <?php if (empty($years)): ?><div class="n-empty-state"><div class="n-empty-icon"><?= icon('calendar') ?></div><h2 class="n-empty-title"><?= e(__('academic_years.empty_title')) ?></h2><p class="n-empty-description"><?= e(__('academic_years.empty_description')) ?></p><?php if ($canManage): ?><button class="btn btn-primary" type="button" data-tab-switch="add-tab"><?= icon('plus') ?><?= e(__('academic_years.add')) ?></button><?php endif; ?></div>
    <?php else: ?><div class="n-year-list">
      <?php foreach ($years as $year): ?>
        <article data-filter-item class="n-year-row <?= (int) $year['is_active'] === 1 ? 'active' : '' ?> <?= (int) $year['is_closed'] === 1 ? 'closed' : '' ?>">
          <div class="n-year-marker"><?= icon((int) $year['is_closed'] === 1 ? 'lock' : 'calendar') ?></div>
          <div class="n-year-main"><div class="d-flex align-items-center gap-2 flex-wrap"><h2 dir="ltr"><?= e($year['label']) ?></h2><?php if ((int) $year['is_active'] === 1): ?><span class="badge text-bg-primary"><?= icon('check-circle') ?><?= e(__('academic_years.active_badge')) ?></span><?php endif; ?><span class="badge <?= (int) $year['is_closed'] === 1 ? 'text-bg-secondary' : 'text-bg-success' ?>"><?= e((int) $year['is_closed'] === 1 ? __('academic_years.closed_badge') : __('academic_years.open_badge')) ?></span></div><div class="n-year-dates" dir="ltr"><?= icon('calendar') ?><time><?= e($year['start_date']) ?></time><span>→</span><time><?= e($year['end_date']) ?></time></div></div>
          <div class="n-year-actions">
            <?php if ($canManage): ?><a href="/academic-years/<?= (int) $year['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><?= icon('edit') ?><?= e(__('common.edit')) ?></a><?php endif; ?>
            <?php if ($canManage && (int) $year['is_active'] !== 1 && (int) $year['is_closed'] !== 1): ?><form method="post" action="/academic-years/<?= (int) $year['id'] ?>/activate" data-confirm="<?= e(__('academic_years.confirm_activate')) ?>"><?= CsrfMiddleware::field() ?><button class="btn btn-sm btn-outline-primary" type="submit"><?= icon('check-circle') ?><?= e(__('academic_years.activate')) ?></button></form><?php endif; ?>
            <?php if ($canClose && (int) $year['is_closed'] !== 1): ?><form method="post" action="/academic-years/<?= (int) $year['id'] ?>/close" data-confirm="<?= e(__('academic_years.confirm_close')) ?>"><?= CsrfMiddleware::field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= icon('lock') ?><?= e(__('academic_years.close')) ?></button></form><?php elseif ($canClose): ?><form method="post" action="/academic-years/<?= (int) $year['id'] ?>/reopen" data-confirm="<?= e(__('academic_years.confirm_reopen')) ?>"><?= CsrfMiddleware::field() ?><button class="btn btn-sm btn-outline-warning" type="submit"><?= icon('unlock') ?><?= e(__('academic_years.reopen')) ?></button></form><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </div>

  <?php if ($canManage): ?><div class="tab-pane fade" id="add-panel" role="tabpanel">
    <div class="n-form-card-powerful"><div class="n-form-card-header"><span class="n-form-card-icon"><?= icon('calendar') ?></span><div><h2 class="n-form-card-title"><?= e(__('academic_years.tab_add')) ?></h2><p class="n-form-card-subtitle"><?= e(__('academic_years.form_add_subtitle')) ?></p></div></div>
      <form method="post" action="/academic-years" class="n-form-powerful" data-unsaved-guard data-loading-submit><?= CsrfMiddleware::field() ?><div class="row g-3"><div class="col-12"><label class="form-label" for="label_new"><?= e(__('academic_years.label')) ?></label><input class="form-control" id="label_new" name="label" dir="ltr" placeholder="2026/2027" required></div><div class="col-md-6"><label class="form-label" for="start_date_new"><?= e(__('academic_years.start_date')) ?></label><input type="date" class="form-control" id="start_date_new" name="start_date" required></div><div class="col-md-6"><label class="form-label" for="end_date_new"><?= e(__('academic_years.end_date')) ?></label><input type="date" class="form-control" id="end_date_new" name="end_date" required></div><?php if (!empty($years)): ?><div class="col-12"><label class="form-label" for="rollover_from_new"><?= e(__('academic_years.rollover_option')) ?></label><select class="form-select" id="rollover_from_new" name="rollover_from"><option value=""><?= e(__('academic_years.rollover_none')) ?></option><?php foreach ($years as $year): ?><option value="<?= (int) $year['id'] ?>"><?= e($year['label']) ?></option><?php endforeach; ?></select></div><?php endif; ?></div><div class="n-form-actions"><button class="btn btn-secondary" type="button" data-tab-switch="list-tab"><?= e(__('common.cancel')) ?></button><button class="btn btn-primary" type="submit"><?= icon('check') ?><?= e(__('common.save')) ?></button></div></form>
    </div>
  </div><?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
