<?php
/**
 * Emergency admin recovery form — only shown when users table is empty.
 * Reuses the same form structure as setup/admin.php but with different
 * messaging to make it clear this is recovery, not initial setup.
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'ar') ?>" dir="<?= $_SESSION['lang'] === 'en' ? 'ltr' : 'rtl' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('setup.recover_admin_title') ?> — <?= __('app.name') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/setup.css">
</head>
<body class="setup-page">
    <div class="setup-container">
        <div class="setup-header">
            <h1 class="setup-logo">
                <span class="setup-logo-icon">🔐</span>
                <?= __('app.name') ?>
            </h1>
            <p class="setup-subtitle"><?= __('setup.recover_admin_subtitle') ?></p>
        </div>

        <div class="setup-progress">
            <div class="setup-step active">
                <div class="setup-step-number">⚠️</div>
                <div class="setup-step-label"><?= __('setup.recover_admin_step') ?></div>
            </div>
        </div>

        <div class="setup-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="setup-info-box">
                <p><strong><?= __('setup.recover_admin_warning_title') ?></strong></p>
                <p><?= __('setup.recover_admin_warning_text') ?></p>
            </div>

            <form method="POST" action="/setup/recover-admin" class="setup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <div class="form-group">
                    <label for="full_name"><?= __('setup.admin_full_name') ?></label>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-control" 
                           required 
                           autofocus
                           placeholder="<?= __('setup.admin_full_name_placeholder') ?>">
                </div>

                <div class="form-group">
                    <label for="username"><?= __('setup.admin_username') ?></label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           required
                           placeholder="<?= __('setup.admin_username_placeholder') ?>">
                </div>

                <div class="form-group">
                    <label for="password"><?= __('setup.admin_password') ?></label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required
                           minlength="8"
                           placeholder="<?= __('setup.admin_password_placeholder') ?>">
                    <small class="form-text text-muted"><?= __('setup.admin_password_hint') ?></small>
                </div>

                <div class="form-group">
                    <label for="password_confirm"><?= __('setup.admin_password_confirm') ?></label>
                    <input type="password" 
                           id="password_confirm" 
                           name="password_confirm" 
                           class="form-control" 
                           required
                           minlength="8"
                           placeholder="<?= __('setup.admin_password_confirm_placeholder') ?>">
                </div>

                <div class="setup-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= __('setup.recover_admin_submit') ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="setup-footer">
            <p><?= __('setup.footer_text') ?></p>
        </div>
    </div>
</body>
</html>
