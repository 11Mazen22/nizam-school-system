<?php
/**
 * Reusable instant list lookup.
 *
 * The input filters the already-rendered target in the browser with no
 * request and no Enter key. Its value is reflected into ?q= so a refresh or a
 * shared URL restores the same view, and the form remains a normal GET form
 * for browsers without JavaScript.
 *
 * @var string $listSearchTarget CSS selector for the table / list / card container
 * @var string $listSearchAction current list URL, used by the non-JS fallback
 * @var array  $listSearchHidden extra query values to preserve on a non-JS submit
 * @var string $listSearchValue  current query, when the page already exposes one
 */
$listSearchTarget = (string) ($listSearchTarget ?? '');
$listSearchAction = (string) ($listSearchAction ?? '');
$listSearchHidden = is_array($listSearchHidden ?? null) ? $listSearchHidden : [];
$listSearchValue = (string) ($listSearchValue ?? ($_GET['q'] ?? ''));
$filterId = 'list-search-' . substr(sha1($listSearchTarget . $listSearchAction), 0, 10);
?>
<form method="get" action="<?= e($listSearchAction) ?>" class="n-filter-panel n-list-search no-print">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field flex-grow-1">
      <?= icon('search') ?>
      <label class="visually-hidden" for="<?= e($filterId) ?>"><?= e(__('common.search')) ?></label>
      <input id="<?= e($filterId) ?>" type="search" class="form-control" name="q"
             value="<?= e($listSearchValue) ?>"
             placeholder="<?= e(__('common.search')) ?>"
             autocomplete="off"
             data-table-filter="<?= e($listSearchTarget) ?>"
             data-filter-empty="<?= e(__('common.no_results')) ?>">
    </div>
    <?php foreach ($listSearchHidden as $key => $hiddenValue): ?>
      <?php if ((string) $hiddenValue !== ''): ?>
        <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $hiddenValue) ?>">
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</form>
