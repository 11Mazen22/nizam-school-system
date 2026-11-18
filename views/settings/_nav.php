<?php
/**
 * Shared side-rail for the five §O-21 Settings screens. @var string $activeSettingsTab
 * Deliberately five separate pages, not five panels in one form -- so saving
 * "Localization" can never accidentally also resubmit whatever is sitting in
 * the Security tab, and so each screen's own server-side validation stays
 * scoped to exactly the fields it owns.
 *
 * Opens the two-column settings layout (.n-settings-layout) and the rail;
 * views/settings/_end.php closes them. Between the two requires sits the
 * tab's own content (usually one .n-settings-card).
 */
$tabs = [
    'profile' => ['icon' => 'building', 'label' => 'settings.tab.profile'],
    'modules' => ['icon' => 'puzzle', 'label' => 'settings.tab.modules'],
    'localization' => ['icon' => 'grid', 'label' => 'settings.tab.localization'],
    'academic' => ['icon' => 'calendar', 'label' => 'settings.tab.academic'],
    'structure' => ['icon' => 'layers', 'label' => 'settings.tab.structure'],
    'security' => ['icon' => 'lock', 'label' => 'settings.tab.security'],
    'privacy' => ['icon' => 'shield-check', 'label' => 'settings.tab.privacy'],
    'backup' => ['icon' => 'database', 'label' => 'settings.tab.backup'],
    'offline' => ['icon' => 'inbox', 'label' => 'settings.tab.offline'],
];
?>
<div class="n-settings-layout">
  <nav class="n-settings-nav" aria-label="<?= e(__('settings.title')) ?>">
    <?php foreach ($tabs as $slug => $tab): ?>
      <a class="nav-link<?= $activeSettingsTab === $slug ? ' active' : '' ?>" href="/settings/<?= e($slug) ?>">
        <?= icon($tab['icon']) ?>
        <span><?= e(__($tab['label'])) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="n-settings-content">
