<?php
/**
 * Nizam -- authenticated app shell, opening half. Paired with
 * views/layout/end.php; every authenticated page does:
 *   <?php $pageTitle = '...'; require .../layout/start.php; ?>
 *   ...page content...
 *   <?php require .../layout/end.php; ?>
 * No template-inheritance system -- §C already ruled that out ("no template
 * engine, nothing to add as a dependency for something require already
 * does"); this is the same plain-include pattern extended to a shared shell.
 *
 * @var string $pageTitle
 */
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;

$school = (new SchoolRepository())->full();
$schoolName = $school === null
    ? __('app.name')
    : (currentLocale() === 'ar' ? $school['name_ar'] : $school['name']);
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= e($schoolName) ?></title>
  <?php if (currentDirection() === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <?php else: ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="nizam-shell">
  <nav class="nizam-sidebar p-3 d-none d-md-block">
    <div class="fw-bold mb-4 fs-5"><?= e(__('app.name')) ?></div>
    <ul class="nav nav-pills flex-column">
      <?php foreach (navItems() as $item): ?>
        <?php if ($item['permission'] === null || hasPermission($item['permission'])): ?>
          <li class="nav-item">
            <a class="nav-link<?= ($activeNav ?? '') === $item['key'] ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
              <?= e(__($item['label'])) ?>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="nizam-main">
    <header class="nizam-topbar d-flex align-items-center justify-content-between px-3 py-2">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#nizamMobileNav">
          ☰
        </button>
        <?php if (!empty($school['logo_path'])): ?>
          <img src="/logo" alt="" style="height:28px; max-width:90px; object-fit:contain;">
        <?php endif; ?>
        <span class="fw-semibold"><?= e($schoolName) ?></span>
        <?php if (AcademicYearContext::activeYearId() !== null): ?>
          <span class="badge text-bg-light border"><?= e(AcademicYearContext::label() ?? '') ?></span>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(__('nav.language')) ?>">
          <a class="btn btn-outline-secondary<?= currentLocale() === 'ar' ? ' active' : '' ?>" href="/lang?to=ar">ع</a>
          <a class="btn btn-outline-secondary<?= currentLocale() === 'en' ? ' active' : '' ?>" href="/lang?to=en">EN</a>
        </div>
        <span class="text-muted small"><?= e($_SESSION['full_name'] ?? '') ?></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logoutConfirm">
          <?= e(__('auth.logout.submit')) ?>
        </button>
      </div>
    </header>

    <!-- Reusable confirmation-dialog pattern (§L "Confirmation dialogs"):
         a plain Bootstrap modal, its confirm button doing nothing but
         submit the real form -- the CSRF-protected POST underneath is
         unchanged, this only adds an "are you sure" step in front of it. -->
    <div class="modal fade" id="logoutConfirm" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body"><?= e(__('auth.logout.confirm')) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
            <form method="post" action="/logout" class="m-0">
              <?= CsrfMiddleware::field() ?>
              <button type="submit" class="btn btn-primary"><?= e(__('auth.logout.submit')) ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Shared confirm-before-submit modal (§L), driven by public/assets/js/confirm.js --
         any <form data-confirm="..."> anywhere reuses this one instance rather than
         each Phase 6 archive/activate/close/promote action rolling its own modal. -->
    <div class="modal fade" id="nizamConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body" id="nizamConfirmMessage"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
            <button type="button" class="btn btn-primary" id="nizamConfirmButton"><?= e(__('common.confirm')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="nizamMobileNav">
      <div class="offcanvas-header">
        <span class="fw-bold"><?= e(__('app.name')) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="nav nav-pills flex-column">
          <?php foreach (navItems() as $item): ?>
            <?php if ($item['permission'] === null || hasPermission($item['permission'])): ?>
              <li class="nav-item">
                <a class="nav-link<?= ($activeNav ?? '') === $item['key'] ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
                  <?= e(__($item['label'])) ?>
                </a>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <main class="nizam-content">
      <?php foreach (Flash::consume() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show"
             role="alert" <?= $flash['type'] === 'success' ? 'data-flash-autodismiss' : '' ?>>
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
      <?php endforeach; ?>

      <h1 class="h4 mb-3"><?= e($pageTitle) ?></h1>
