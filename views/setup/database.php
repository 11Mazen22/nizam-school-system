<?php
/**
 * @var string|null $error @var string $host @var string $port
 * @var string $database @var string $username
 * @var array{phpVersion: string, missingRequired: string[], missingOptional: string[], ok: bool} $environment
 */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.database.title');
$stepNumber = 1;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <div class="mb-4 pb-3 border-bottom">
        <h2 class="h6"><?= e(__('setup.environment.title')) ?></h2>
        <p class="small text-muted mb-1"><?= e(__('setup.environment.php_version')) ?>: <?= e($environment['phpVersion']) ?></p>
        <?php if ($environment['ok']): ?>
          <p class="small text-success mb-0"><?= e(__('setup.environment.required_ok')) ?></p>
        <?php else: ?>
          <p class="small text-danger mb-0">
            <?= e(__('setup.environment.required_missing', ['list' => implode(', ', $environment['missingRequired'])])) ?>
          </p>
        <?php endif; ?>
        <?php if (!empty($environment['missingOptional'])): ?>
          <p class="small text-warning mb-0">
            <?= e(__('setup.environment.optional_missing', ['list' => implode(', ', $environment['missingOptional'])])) ?>
          </p>
        <?php endif; ?>
      </div>
      <form method="post" action="/setup/database">
        <?= CsrfMiddleware::field() ?>
        <fieldset<?= $environment['ok'] ? '' : ' disabled' ?>>
        <div class="mb-3">
          <label class="form-label" for="host"><?= e(__('setup.database.host')) ?></label>
          <input type="text" class="form-control" id="host" name="host" value="<?= e($host) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="port"><?= e(__('setup.database.port')) ?></label>
          <input type="text" inputmode="numeric" class="form-control" id="port" name="port" value="<?= e($port) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="database"><?= e(__('setup.database.name')) ?></label>
          <input type="text" class="form-control" id="database" name="database" value="<?= e($database) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="username"><?= e(__('setup.database.username')) ?></label>
          <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password"><?= e(__('setup.database.password')) ?></label>
          <input type="password" class="form-control" id="password" name="password">
        </div>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('database') ?>
          <span><?= e(__('setup.database.test_and_save')) ?></span>
        </button>
        </fieldset>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>
