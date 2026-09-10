<?php
/** @var array $users @var array $roles */
$pageTitle = __('users.title');
$activeNav = 'users';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal-new">
    <?= e(__('users.add')) ?>
  </button>
</div>

<?php if (empty($users)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('users.username')) ?></th>
          <th><?= e(__('users.full_name')) ?></th>
          <th><?= e(__('users.role')) ?></th>
          <th><?= e(__('users.last_login')) ?></th>
          <th><?= e(__('academic_years.status')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td class="mono"><?= e($user['username']) ?></td>
            <td><?= e($user['full_name']) ?></td>
            <td><?= e(currentLocale() === 'ar' ? $user['role_name_ar'] : $user['role_name_en']) ?></td>
            <td class="text-muted small"><?= e($user['last_login_at'] ?? __('users.never_logged_in')) ?></td>
            <td>
              <?php if ((int) $user['is_active'] === 1): ?>
                <span class="badge text-bg-success"><?= e(__('common.status_active')) ?></span>
              <?php else: ?>
                <span class="badge text-bg-secondary"><?= e(__('common.status_archived')) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#userModal-<?= (int) $user['id'] ?>">
                <?= e(__('common.edit')) ?>
              </button>
              <?php if ((int) $user['is_active'] === 1): ?>
                <form method="post" action="/users/<?= (int) $user['id'] ?>/archive" class="d-inline" data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.archive')) ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/users/<?= (int) $user['id'] ?>/restore" class="d-inline" data-confirm="<?= e(__('common.confirm_restore')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-success"><?= e(__('common.restore')) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <div class="modal fade" id="userModal-<?= (int) $user['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post" action="/users/<?= (int) $user['id'] ?>">
                  <?= CsrfMiddleware::field() ?>
                  <div class="modal-header"><h2 class="h6 m-0"><?= e(__('common.edit')) ?></h2></div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('users.username')) ?></label>
                      <input type="text" class="form-control" name="username" value="<?= e($user['username']) ?>" pattern="[a-zA-Z0-9_.\-]{3,50}" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('users.full_name')) ?></label>
                      <input type="text" class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('users.role')) ?></label>
                      <select class="form-select" name="role_code" required>
                        <?php foreach ($roles as $role): ?>
                          <option value="<?= e($role['code']) ?>" <?= $role['code'] === $user['role_code'] ? 'selected' : '' ?>>
                            <?= e(currentLocale() === 'ar' ? $role['name_ar'] : $role['name_en']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label"><?= e(__('users.password')) ?></label>
                      <input type="password" class="form-control" name="password" autocomplete="new-password" placeholder="<?= e(__('users.password_unchanged_hint')) ?>">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="modal fade" id="userModal-new" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/users">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header"><h2 class="h6 m-0"><?= e(__('users.add')) ?></h2></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><?= e(__('users.username')) ?></label>
            <input type="text" class="form-control" name="username" pattern="[a-zA-Z0-9_.\-]{3,50}" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(__('users.full_name')) ?></label>
            <input type="text" class="form-control" name="full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(__('users.role')) ?></label>
            <select class="form-select" name="role_code" required>
              <?php foreach ($roles as $role): ?>
                <option value="<?= e($role['code']) ?>"><?= e(currentLocale() === 'ar' ? $role['name_ar'] : $role['name_en']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(__('users.password')) ?></label>
            <input type="password" class="form-control" name="password" autocomplete="new-password" required minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
