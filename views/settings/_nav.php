<?php
/**
 * Shared sub-nav for the five §O-21 Settings screens. @var string $activeSettingsTab
 * Deliberately five separate pages, not five panels in one form -- so saving
 * "Localization" can never accidentally also resubmit whatever is sitting in
 * the Security tab, and so each screen's own server-side validation stays
 * scoped to exactly the fields it owns.
 */
$tabs = [
    'profile' => 'settings.tab.profile',
    'localization' => 'settings.tab.localization',
    'academic' => 'settings.tab.academic',
    'security' => 'settings.tab.security',
    'backup' => 'settings.tab.backup',
];
?>
<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $slug => $labelKey): ?>
    <li class="nav-item">
      <a class="nav-link<?= $activeSettingsTab === $slug ? ' active' : '' ?>" href="/settings/<?= e($slug) ?>">
        <?= e(__($labelKey)) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
