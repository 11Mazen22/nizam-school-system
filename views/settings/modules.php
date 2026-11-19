<?php
/** @var array $modules */
$moduleManager = new \App\Services\ModuleManager();
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
                <?php if (dbBool($m['is_core'])): ?>
                  <span class="badge text-bg-primary"><?= e(__('modules.core')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary"><?= e(__('modules.optional')) ?></span>
                <?php endif; ?>
                <?php
                  $deps = $moduleManager->dependenciesFor((string) $m['module_key']);
                  $dependents = $moduleManager->activeDependents((string) $m['module_key']);
                  if ($deps !== [] || $dependents !== []):
                ?>
                  <div class="small text-muted mt-1" tabindex="0"
                       title="<?= e(__('modules.dependency_help')) ?>"
                       aria-label="<?= e(__('modules.dependency_help')) ?>">
                    <?= icon('info-circle', 'n-icon-sm') ?>
                    <?php if ($deps !== []): ?>
                      <?= e(__('modules.requires')) ?>: <?= e(implode(', ', array_map('moduleLabel', $deps))) ?><br>
                    <?php endif; ?>
                    <?php if ($dependents !== []): ?>
                      <span class="text-warning"><?= e(__('modules.required_by')) ?>: <?= e(implode(', ', array_map('moduleLabel', $dependents))) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (dbBool($m['is_enabled'])): ?>
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
                <?php if (dbBool($m['is_core'])): ?>
                  <span class="small text-muted fst-italic"><?= e(__('modules.required')) ?></span>
                <?php else: ?>
                  <form method="post" action="/settings/modules/<?= e($m['module_key']) ?>/toggle"
                        class="d-inline" data-loading-submit
                        <?= dbBool($m['is_enabled']) ? 'data-confirm="' . e(__('modules.confirm_disable', ['module' => moduleLabel((string) $m['module_key'])])) . '"' : '' ?>>
                    <?= CsrfMiddleware::field() ?>
                    <input type="hidden" name="enable" value="<?= dbBool($m['is_enabled']) ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm d-inline-flex align-items-center gap-1 <?= dbBool($m['is_enabled']) ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                            <?php if ($dependents !== [] && dbBool($m['is_enabled'])): ?>disabled title="<?= e(__('modules.error.active_dependents', ['modules' => implode(', ', array_map('moduleLabel', $dependents))])) ?>"<?php endif; ?>>
                      <?= dbBool($m['is_enabled']) ? e(__('modules.disable')) : e(__('modules.enable')) ?>
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
