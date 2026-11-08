<?php
/**
 * Authenticated Nizam application shell.
 *
 * @var string $pageTitle
 * @var bool|null $suppressPageTitle
 */
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;

$school = (new SchoolRepository())->full();
$schoolName = $school === null
    ? __('app.name')
    : (currentLocale() === 'ar' ? $school['name_ar'] : $school['name']);
$logoUrl = !empty($school['logo_path']) ? '/logo' : '/assets/img/hadaba-logo.png';

$navItemsList = navItems();
$navGroups = [
    'main' => 'nav.group.main',
    'academic' => 'nav.group.academic',
    'people' => 'nav.group.people',
    'operations' => 'nav.group.operations',
    'welfare' => 'nav.group.welfare',
    'business' => 'nav.group.business',
    'portals' => 'nav.group.portals',
    'reports' => 'nav.group.reports',
    'system' => 'nav.group.system',
];
$visibleNavGroups = [];
foreach ($navItemsList as $item) {
    if ($item['permission'] === null || hasPermission($item['permission'])) {
        $visibleNavGroups[$item['group']][] = $item;
    }
}

$navIconByKey = array_column($navItemsList, 'icon', 'key');
$pageIcon = $navIconByKey[$activeNav ?? ''] ?? 'dashboard';
$activeNavItem = null;
foreach ($navItemsList as $item) {
    if (($activeNav ?? '') === $item['key']) {
        $activeNavItem = $item;
        break;
    }
}

$quickActions = [];
if (hasPermission('students.create')) {
    $quickActions[] = ['href' => '/students/create', 'label' => __('students.add'), 'icon' => 'users'];
}
if (hasPermission('teachers.create')) {
    $quickActions[] = ['href' => '/teachers/create', 'label' => __('teachers.add'), 'icon' => 'graduation-cap'];
}
if (hasPermission('attendance.manage')) {
    $quickActions[] = ['href' => '/attendance', 'label' => __('attendance.mark_title'), 'icon' => 'check-circle'];
}
if (hasPermission('exams.manage')) {
    $quickActions[] = ['href' => '/exams', 'label' => __('exams.add'), 'icon' => 'star'];
}
if (hasPermission('reports.view')) {
    $quickActions[] = ['href' => '/reports', 'label' => __('reports.title'), 'icon' => 'chart'];
}

$renderNavGroups = static function (array $groups, array $labels, string $active): void {
    foreach ($labels as $groupKey => $groupLabel) {
        if (empty($groups[$groupKey])) {
            continue;
        }
        ?>
        <div class="n-nav-group">
          <div class="n-nav-label"><?= e(__($groupLabel)) ?></div>
          <ul class="nav flex-column">
            <?php foreach ($groups[$groupKey] as $item): ?>
              <li class="nav-item">
                <a class="nav-link<?= $active === $item['key'] ? ' active' : '' ?>"
                   href="<?= e($item['href']) ?>"
                   title="<?= e(__($item['label'])) ?>"
                   <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                  <span class="n-nav-icon"><?= icon($item['icon']) ?></span>
                  <span class="n-nav-text"><?= e(__($item['label'])) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php
    }
};
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#315efb">
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="/assets/img/favicon-180.png">
  <title><?= e($pageTitle) ?> — <?= e($schoolName) ?></title>
  <?php if (currentDirection() === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <?php else: ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/app.css')) ?>">
</head>
<body class="n-app-body">
<a class="n-skip-link" href="#main-content"><?= e($pageTitle) ?></a>

<div class="nizam-shell" id="nizamShell">
  <aside class="nizam-sidebar d-none d-lg-flex" id="nizamSidebar" aria-label="<?= e(__('nav.menu')) ?>">
    <a class="nizam-brand" href="/dashboard" aria-label="<?= e($schoolName) ?>">
      <span class="school-brand-mark school-brand-mark-logo"><img src="<?= e($logoUrl) ?>" alt=""></span>
      <span class="nizam-brand-copy">
        <span class="nizam-brand-name"><?= e($schoolName) ?></span>
        <span class="nizam-brand-product">Nizam</span>
      </span>
    </a>

    <nav class="n-sidebar-nav">
      <?php $renderNavGroups($visibleNavGroups, $navGroups, $activeNav ?? ''); ?>
    </nav>

    <div class="n-sidebar-footer">
      <?php if (AcademicYearContext::activeYearId() !== null): ?>
        <div class="n-sidebar-year" title="<?= e(__('shell.current_year')) ?>">
          <?= icon('calendar') ?>
          <span class="n-nav-text"><?= e(AcademicYearContext::label() ?? '') ?></span>
        </div>
      <?php endif; ?>
      <span class="n-sidebar-version n-nav-text">Nizam 1.0</span>
    </div>
  </aside>

  <div class="nizam-main">
    <header class="nizam-topbar">
      <div class="n-topbar-start">
        <button class="btn btn-icon btn-ghost d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#nizamMobileNav"
                aria-label="<?= e(__('nav.menu')) ?>">
          <?= icon('menu') ?>
        </button>
        <button class="btn btn-icon btn-ghost d-none d-lg-inline-flex" type="button"
                id="sidebarToggle" aria-controls="nizamSidebar" aria-expanded="true"
                aria-label="<?= e(__('shell.collapse_sidebar')) ?>"
                data-label-collapse="<?= e(__('shell.collapse_sidebar')) ?>"
                data-label-expand="<?= e(__('shell.expand_sidebar')) ?>">
          <?= icon('menu') ?>
        </button>
        <div class="n-topbar-context">
          <div class="n-breadcrumb" aria-label="Breadcrumb">
            <a href="/dashboard"><?= e(__('shell.home')) ?></a>
            <span aria-hidden="true"><?= icon('chevron-right', 'n-flip-rtl') ?></span>
            <span aria-current="page"><?= e($pageTitle) ?></span>
          </div>
          <div class="n-topbar-title"><?= e($pageTitle) ?></div>
        </div>
      </div>

      <button type="button" class="n-command-trigger d-none d-md-flex" data-command-open
              aria-label="<?= e(__('shell.search_shortcut')) ?>">
        <?= icon('search') ?>
        <span><?= e(__('shell.search_placeholder')) ?></span>
        <kbd>Ctrl K</kbd>
      </button>

      <div class="n-topbar-actions">
        <?php if (AcademicYearContext::activeYearId() !== null): ?>
          <span class="n-year-pill d-none d-xl-inline-flex" title="<?= e(__('shell.current_year')) ?>">
            <?= icon('calendar') ?><span dir="ltr"><?= e(AcademicYearContext::label() ?? '') ?></span>
          </span>
        <?php endif; ?>

        <?php if ($quickActions !== []): ?>
          <div class="dropdown d-none d-sm-block">
            <button class="btn btn-icon btn-primary" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="<?= e(__('shell.quick_actions')) ?>" title="<?= e(__('shell.quick_actions')) ?>">
              <?= icon('plus') ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end n-quick-menu">
              <div class="dropdown-header"><?= e(__('shell.quick_actions')) ?></div>
              <?php foreach ($quickActions as $action): ?>
                <a class="dropdown-item" href="<?= e($action['href']) ?>">
                  <?= icon($action['icon']) ?><span><?= e($action['label']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="n-lang-switch" role="group" aria-label="<?= e(__('nav.language')) ?>">
          <a class="<?= currentLocale() === 'ar' ? 'active' : '' ?>" href="/lang?to=ar" lang="ar" title="<?= e(__('common.language_ar')) ?>">ع</a>
          <a class="<?= currentLocale() === 'en' ? 'active' : '' ?>" href="/lang?to=en" lang="en" title="<?= e(__('common.language_en')) ?>">EN</a>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <div class="dropdown" id="notif-bell-wrapper"
               data-empty-label="<?= e(__('notifications.empty')) ?>"
               data-new-label="<?= e(__('notifications.new')) ?>"
               data-priority-label="<?= e(__('notifications.priority_high')) ?>"
               data-just-now="<?= e(__('common.just_now')) ?>"
               data-minutes-ago="<?= e(__('common.minutes_ago')) ?>"
               data-hours-ago="<?= e(__('common.hours_ago')) ?>"
               data-days-ago="<?= e(__('common.days_ago')) ?>"
               data-locale="<?= e(currentLocale()) ?>">
            <button class="btn btn-icon btn-ghost position-relative" id="notifBellBtn"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="<?= e(__('notifications.title')) ?>">
              <?= icon('bell') ?>
              <span id="notif-badge" class="n-notification-count" hidden>0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end n-notification-menu" aria-labelledby="notifBellBtn">
              <div class="n-notification-menu-head">
                <strong><?= e(__('notifications.title')) ?></strong>
                <button type="button" class="btn btn-link btn-sm" id="notif-markall-link"><?= e(__('notifications.mark_all_read')) ?></button>
              </div>
              <ul id="notif-list" class="n-notification-menu-list" aria-live="polite">
                <li class="n-notification-menu-empty"><?= e(__('notifications.empty')) ?></li>
              </ul>
              <a class="n-notification-menu-footer" href="/notifications"><?= e(__('notifications.view_all')) ?></a>
            </div>
          </div>
        <?php endif; ?>

        <div class="dropdown">
          <button class="n-user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="n-avatar" aria-hidden="true"><?= e(mb_substr($_SESSION['full_name'] ?? '?', 0, 1)) ?></span>
            <span class="n-user-trigger-copy d-none d-xl-flex">
              <strong><?= e($_SESSION['full_name'] ?? '') ?></strong>
              <small><?= e(ucfirst((string) ($_SESSION['role_code'] ?? ''))) ?></small>
            </span>
            <?= icon('chevron-down', 'd-none d-xl-inline-block') ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end n-account-menu">
            <div class="n-account-summary">
              <span class="n-avatar"><?= e(mb_substr($_SESSION['full_name'] ?? '?', 0, 1)) ?></span>
              <span><strong><?= e($_SESSION['full_name'] ?? '') ?></strong><small><?= e(ucfirst((string) ($_SESSION['role_code'] ?? ''))) ?></small></span>
            </div>
            <div class="dropdown-divider"></div>
            <?php if (hasPermission('settings.manage')): ?>
              <a class="dropdown-item" href="/settings/profile"><?= icon('settings') ?><span><?= e(__('nav.settings')) ?></span></a>
            <?php endif; ?>
            <button class="dropdown-item" type="button" id="densityToggle"
                    data-comfortable="<?= e(__('shell.density_comfortable')) ?>"
                    data-compact="<?= e(__('shell.density_compact')) ?>">
              <?= icon('list') ?><span><?= e(__('shell.display_density')) ?>: <b data-density-label><?= e(__('shell.density_comfortable')) ?></b></span>
            </button>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#logoutConfirm">
              <?= icon('logout') ?><span><?= e(__('auth.logout.submit')) ?></span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <div class="offcanvas offcanvas-start n-mobile-nav d-lg-none" tabindex="-1" id="nizamMobileNav" aria-labelledby="nizamMobileNavLabel">
      <div class="offcanvas-header">
        <a class="nizam-brand" href="/dashboard" id="nizamMobileNavLabel">
          <span class="school-brand-mark school-brand-mark-logo"><img src="<?= e($logoUrl) ?>" alt=""></span>
          <span class="nizam-brand-copy"><span class="nizam-brand-name"><?= e($schoolName) ?></span><span class="nizam-brand-product">Nizam</span></span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.close')) ?>"></button>
      </div>
      <div class="offcanvas-body n-sidebar-nav">
        <button type="button" class="n-command-trigger w-100 mb-3" data-command-open data-bs-dismiss="offcanvas">
          <?= icon('search') ?><span><?= e(__('shell.search_placeholder')) ?></span>
        </button>
        <?php $renderNavGroups($visibleNavGroups, $navGroups, $activeNav ?? ''); ?>
      </div>
    </div>

    <main class="nizam-content" id="main-content" tabindex="-1">
      <?php foreach (Flash::consume() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert"
             <?= $flash['type'] === 'success' ? 'data-flash-autodismiss' : '' ?>>
          <?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-circle' : 'info'), 'n-icon-lg flex-shrink-0') ?>
          <span class="flex-grow-1"><?= e($flash['message']) ?></span>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
      <?php endforeach; ?>

      <?php if (empty($suppressPageTitle)): ?>
        <div class="n-page-head">
          <div class="n-page-head-main">
            <span class="n-page-head-icon"><?= icon($pageIcon) ?></span>
            <div><h1><?= e($pageTitle) ?></h1></div>
          </div>
        </div>
      <?php endif; ?>

    <!-- Shared dialogs -->
    <div class="modal fade" id="logoutConfirm" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-header"><h2 class="modal-title h6"><?= e(__('auth.logout.submit')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
          <div class="modal-body"><?= e(__('auth.logout.confirm')) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
            <form method="post" action="/logout" class="m-0"><?= CsrfMiddleware::field() ?><button type="submit" class="btn btn-primary"><?= e(__('auth.logout.submit')) ?></button></form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="nizamConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-header"><h2 class="modal-title h6"><?= e(__('common.confirm')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
          <div class="modal-body" id="nizamConfirmMessage"></div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="button" class="btn btn-danger" id="nizamConfirmButton"><?= e(__('common.confirm')) ?></button></div>
        </div>
      </div>
    </div>
<div class="n-command-overlay" id="nizamCommandPalette" hidden>
  <div class="n-command-dialog" role="dialog" aria-modal="true" aria-labelledby="nizamCommandTitle">
    <div class="n-command-search">
      <?= icon('search') ?>
      <input type="search" id="nizamCommandInput" autocomplete="off"
             placeholder="<?= e(__('shell.command_hint')) ?>" aria-label="<?= e(__('shell.command_hint')) ?>">
      <kbd>Esc</kbd>
    </div>
    <div class="n-command-meta"><strong id="nizamCommandTitle"><?= e(__('shell.command_title')) ?></strong><span><?= e(__('shell.search_shortcut')) ?></span></div>
    <div class="n-command-results" id="nizamCommandResults">
      <?php foreach ($visibleNavGroups as $groupKey => $items): ?>
        <div class="n-command-group" data-command-group>
          <div class="n-command-group-label"><?= e(__($navGroups[$groupKey])) ?></div>
          <?php foreach ($items as $item): ?>
            <a href="<?= e($item['href']) ?>" class="n-command-item" data-command-item data-search="<?= e(mb_strtolower(__($item['label']))) ?>">
              <span class="n-command-item-icon"><?= icon($item['icon']) ?></span>
              <span><?= e(__($item['label'])) ?></span>
              <?= icon('arrow-forward', 'n-command-arrow n-flip-rtl') ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($quickActions !== []): ?>
        <div class="n-command-group" data-command-group>
          <div class="n-command-group-label"><?= e(__('shell.quick_actions')) ?></div>
          <?php foreach ($quickActions as $action): ?>
            <a href="<?= e($action['href']) ?>" class="n-command-item" data-command-item data-search="<?= e(mb_strtolower($action['label'])) ?>">
              <span class="n-command-item-icon"><?= icon($action['icon']) ?></span>
              <span><?= e($action['label']) ?></span>
              <?= icon('arrow-forward', 'n-command-arrow n-flip-rtl') ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="n-command-empty" id="nizamCommandEmpty" hidden><?= e(__('shell.command_empty')) ?></div>
    </div>
  </div>
</div>
