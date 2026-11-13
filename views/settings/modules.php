<?php
/** @var array $modules */
$pageTitle = __('settings.tab.modules');
$activeNav = 'settings';
$activeSettingsTab = 'modules';

require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<?php require __DIR__ . '/_nav.php'; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.modules')) ?></h2>
    <p><?= e(__('modules.catalog_subtitle')) ?></p>
  </div>
  <div class="n-settings-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(__('modules.name')) ?></th>
            <th><?= e(__('modules.category')) ?></th>
            <th><?= e(__('modules.type')) ?></th>
            <th><?= e(__('modules.status')) ?></th>
            <th class="text-end"><?= e(__('common.action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($modules as $m): ?>
            <tr>
              <td>
                <div class="fw-semibold">
                  <?= e(currentLocale() === 'ar' ? $m['name_ar'] : $m['name_en']) ?>
                </div>
                <div class="small text-muted">
                  <?= e(currentLocale() === 'ar' ? ($m['description_ar'] ?? $m['module_key']) : ($m['description_en'] ?? $m['module_key'])) ?>
                </div>
              </td>
              <td>
                <span class="badge text-bg-light border text-uppercase font-monospace small">
                  <?= e($m['category']) ?>
                </span>
              </td>
              <td>
                <?php if ($m['is_core']): ?>
                  <span class="badge text-bg-primary"><?= e(__('modules.core')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary"><?= e(__('modules.optional')) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($m['is_enabled']): ?>
                  <span class="badge text-bg-success d-inline-flex align-items-center gap-1">
                    <?= icon('check', 'n-icon-sm') ?>
                    <span><?= e(__('modules.enabled')) ?></span>
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-light text-muted border">
                    <?= e(__('modules.disabled')) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($m['is_core']): ?>
                  <span class="small text-muted fst-italic"><?= e(__('modules.required')) ?></span>
                <?php else: ?>
                  <form method="post" action="/settings/modules/<?= e($m['module_key']) ?>/toggle" class="d-inline">
                    <?= CsrfMiddleware::field() ?>
                    <input type="hidden" name="enable" value="<?= $m['is_enabled'] ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= $m['is_enabled'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                      <?= $m['is_enabled'] ? e(__('modules.disable')) : e(__('modules.enable')) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>
