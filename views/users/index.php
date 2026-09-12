<?php
/** @var array $users @var array $roles */
$pageTitle = __('users.title');
$activeNav = 'users';
$pageScripts = ['/assets/js/users.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$activeUsers = array_filter($users, fn ($u) => (int) $u['is_active'] === 1);
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('users-cog', 'n-grades-icon') ?>
        </div>
        <?= e(__('users.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('users.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($users) ?></span>
          <span class="n-stat-mini-label"><?= e(__('users.stat_total')) ?></span>
        </div>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($activeUsers) ?></span>
          <span class="n-stat-mini-label"><?= e(__('users.stat_active')) ?></span>
        </div>
      </div>
      <button type="button" class="btn btn-outline-light d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
        <?= icon("upload") ?>
        <span><?= e(__("app.import")) ?></span>
      </button>
    </div>
  </div>
</div>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs n-tabs-powerful mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
      <?= icon('list', 'n-icon-sm') ?>
      <span><?= e(__('users.tab_list')) ?></span>
      <span class="badge"><?= count($users) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-panel" type="button" role="tab">
      <?= icon('plus', 'n-icon-sm') ?>
      <span><?= e(__('users.tab_add')) ?></span>
    </button>
  </li>
  <li class="nav-item d-none" role="presentation" id="edit-tab-wrapper">
    <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-panel" type="button" role="tab">
      <?= icon('edit', 'n-icon-sm') ?>
      <span><?= e(__('users.tab_edit')) ?></span>
    </button>
  </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">

  <!-- LIST TAB -->
  <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
    <?php if (empty($users)): ?>
      <div class="card">
        <div class="card-body">
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('users-cog') ?></div>
            <p class="mb-0"><?= e(__('common.no_results')) ?></p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 n-table-stack">
              <thead>
                <tr>
                  <th><?= e(__('users.username')) ?></th>
                  <th><?= e(__('users.full_name')) ?></th>
                  <th><?= e(__('users.email')) ?></th>
                  <th><?= e(__('users.role')) ?></th>
                  <th><?= e(__('users.last_login')) ?></th>
                  <th><?= e(__('academic_years.status')) ?></th>
                  <th class="text-end n-stack-hide"><?= e(__('common.actions')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td data-label="<?= e(__('users.username')) ?>" class="mono"><?= e($user['username']) ?></td>
                    <td data-label="<?= e(__('users.full_name')) ?>">
                      <div class="d-flex align-items-center gap-2">
                        <div class="n-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                          <?= e(mb_substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <span><?= e($user['full_name']) ?></span>
                      </div>
                    </td>
                    <td data-label="<?= e(__('users.email')) ?>" class="text-muted small">
                      <?= e($user['email'] ?? '—') ?>
                    </td>
                    <td data-label="<?= e(__('users.role')) ?>">
                      <?= e(currentLocale() === 'ar' ? $user['role_name_ar'] : $user['role_name_en']) ?>
                    </td>
                    <td data-label="<?= e(__('users.last_login')) ?>" class="text-muted small">
                      <?= e($user['last_login_at'] ?? __('users.never_logged_in')) ?>
                    </td>
                    <td data-label="<?= e(__('academic_years.status')) ?>">
                      <?php if ((int) $user['is_active'] === 1): ?>
                        <span class="badge text-bg-success"><?= e(__('common.status_active')) ?></span>
                      <?php else: ?>
                        <span class="badge text-bg-secondary"><?= e(__('common.status_archived')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap" data-label="<?= e(__('common.actions')) ?>">
                      <button type="button" class="btn btn-sm btn-outline-secondary"
                         data-user-edit
                         data-user-id="<?= (int) $user['id'] ?>"
                         data-user-username="<?= e($user['username']) ?>"
                         data-user-full-name="<?= e($user['full_name']) ?>"
                         data-user-email="<?= e($user['email'] ?? '') ?>"
                         data-user-role="<?= e($user['role_code']) ?>">
                        <?= icon('edit') ?>
                        <span class="d-none d-lg-inline ms-1"><?= e(__('common.edit')) ?></span>
                      </button>
                      <?php if ((int) $user['is_active'] === 1): ?>
                        <form method="post" action="/users/<?= (int) $user['id'] ?>/archive" class="d-inline"
                              data-confirm="<?= e(__('common.confirm_archive')) ?>">
                          <?= CsrfMiddleware::field() ?>
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <?= icon('archive') ?>
                            <span class="d-none d-lg-inline ms-1"><?= e(__('common.archive')) ?></span>
                          </button>
                        </form>
                      <?php else: ?>
                        <form method="post" action="/users/<?= (int) $user['id'] ?>/restore" class="d-inline"
                              data-confirm="<?= e(__('common.confirm_restore')) ?>">
                          <?= CsrfMiddleware::field() ?>
                          <button type="submit" class="btn btn-sm btn-outline-success">
                            <?= icon('unarchive') ?>
                            <span class="d-none d-lg-inline ms-1"><?= e(__('common.restore')) ?></span>
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
    <?php endif; ?>
  </div>

  <!-- ADD TAB -->
  <div class="tab-pane fade" id="add-panel" role="tabpanel">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('plus', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('users.tab_add')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('users.form_add_subtitle')) ?></p>
        </div>
      </div>

      <form method="post" action="/users" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="username_new"><span><?= e(__('users.username')) ?></span></label>
          <input type="text" class="form-control n-input-powerful" id="username_new" name="username"
                 pattern="[a-zA-Z0-9_.\-]{3,50}" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="full_name_new"><span><?= e(__('users.full_name')) ?></span></label>
          <input type="text" class="form-control n-input-powerful" id="full_name_new" name="full_name" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="email_new"><span><?= e(__('users.email')) ?> (<?= e(__('common.optional')) ?>)</span></label>
          <input type="email" class="form-control n-input-powerful" id="email_new" name="email">
          <div class="form-text"><?= e(__('users.email_help')) ?></div>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="role_code_new"><span><?= e(__('users.role')) ?></span></label>
          <select class="form-select n-input-powerful" id="role_code_new" name="role_code" required>
            <?php foreach ($roles as $role): ?>
              <option value="<?= e($role['code']) ?>">
                <?= e(currentLocale() === 'ar' ? $role['name_ar'] : $role['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="password_new"><span><?= e(__('users.password')) ?></span></label>
          <input type="password" class="form-control n-input-powerful" id="password_new" name="password"
                 autocomplete="new-password" required minlength="8">
        </div>

        <div class="n-form-actions">
          <button type="button" class="btn btn-secondary n-btn-powerful-secondary" data-tab-switch="list-tab">
            <?= icon('close') ?>
            <span><?= e(__('common.cancel')) ?></span>
          </button>
          <button type="submit" class="btn btn-primary n-btn-powerful">
            <?= icon('check') ?>
            <span><?= e(__('common.save')) ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT TAB -->
  <div class="tab-pane fade" id="edit-panel" role="tabpanel">
    <div class="n-form-card-powerful">
      <div class="n-form-card-header">
        <div class="n-form-card-icon">
          <?= icon('edit', 'n-icon-lg') ?>
        </div>
        <div>
          <h2 class="n-form-card-title"><?= e(__('users.tab_edit')) ?></h2>
          <p class="n-form-card-subtitle"><?= e(__('users.form_edit_subtitle')) ?></p>
        </div>
      </div>

      <form method="post" action="/users/" id="editUserForm" class="n-form-powerful">
        <?= CsrfMiddleware::field() ?>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="username_edit"><span><?= e(__('users.username')) ?></span></label>
          <input type="text" class="form-control n-input-powerful" id="username_edit" name="username"
                 pattern="[a-zA-Z0-9_.\-]{3,50}" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="full_name_edit"><span><?= e(__('users.full_name')) ?></span></label>
          <input type="text" class="form-control n-input-powerful" id="full_name_edit" name="full_name" required>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="email_edit"><span><?= e(__('users.email')) ?> (<?= e(__('common.optional')) ?>)</span></label>
          <input type="email" class="form-control n-input-powerful" id="email_edit" name="email">
          <div class="form-text"><?= e(__('users.email_help')) ?></div>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="role_code_edit"><span><?= e(__('users.role')) ?></span></label>
          <select class="form-select n-input-powerful" id="role_code_edit" name="role_code" required>
            <?php foreach ($roles as $role): ?>
              <option value="<?= e($role['code']) ?>">
                <?= e(currentLocale() === 'ar' ? $role['name_ar'] : $role['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="n-form-group-powerful">
          <label class="n-label-powerful" for="password_edit"><span><?= e(__('users.password')) ?></span></label>
          <input type="password" class="form-control n-input-powerful" id="password_edit" name="password"
                 autocomplete="new-password" placeholder="<?= e(__('users.password_unchanged_hint')) ?>">
        </div>

        <div class="n-form-actions">
          <button type="button" class="btn btn-secondary n-btn-powerful-secondary" data-tab-switch="list-tab">
            <?= icon('close') ?>
            <span><?= e(__('common.cancel')) ?></span>
          </button>
          <button type="submit" class="btn btn-primary n-btn-powerful">
            <?= icon('check') ?>
            <span><?= e(__('common.update')) ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>


<?php
$title = __("app.import");
$action = "/import/users";
$templateUrl = "/import/users/template";
require dirname(__DIR__) . "/partials/import_modal.php";
?>