<?php
/**
 * @var string|null $error
 * @var string|null $notice
 * Bare, functional login screen -- proves the auth/session/CSRF/i18n
 * foundation works end to end. Deliberately minimal styling: the real
 * component library (§L) and visual design are Phase 5 scope, not this one.
 */
use App\Middleware\CsrfMiddleware;
$notice ??= null;
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
<meta charset="UTF-8">
<title><?= e(__('auth.login.title')) ?> — <?= e(__('app.name')) ?></title>
<style>
  body { font-family: Tahoma, Arial, sans-serif; background:#f2f4f3; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
  form { background:#fff; border:1px solid #d6dbd9; border-radius:8px; padding:28px 32px; width:280px; }
  h1 { font-size:1.1rem; margin:0 0 18px; }
  label { display:block; font-size:0.85rem; margin:12px 0 4px; }
  input[type=text], input[type=password] { width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px; }
  button { margin-top:18px; width:100%; padding:9px; background:#2f4b7c; color:#fff; border:0; border-radius:4px; cursor:pointer; }
  .error { background:#f6e6e5; color:#a23b3b; padding:8px 10px; border-radius:4px; font-size:0.85rem; margin-bottom:10px; }
  .notice { background:#e4efe8; color:#3f7d58; padding:8px 10px; border-radius:4px; font-size:0.85rem; margin-bottom:10px; }
</style>
</head>
<body>
  <form method="post" action="/login">
    <h1><?= e(__('auth.login.title')) ?></h1>
    <?php if ($notice !== null): ?>
      <div class="notice"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>
    <?= CsrfMiddleware::field() ?>
    <label for="username"><?= e(__('auth.login.username')) ?></label>
    <input type="text" id="username" name="username" autofocus required>
    <label for="password"><?= e(__('auth.login.password')) ?></label>
    <input type="password" id="password" name="password" required>
    <button type="submit"><?= e(__('auth.login.submit')) ?></button>
  </form>
</body>
</html>
