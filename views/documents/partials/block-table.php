<?php
/**
 * @var array<string, mixed> $block
 */
$columns = $block['columns'] ?? [];
$isCompact = !empty($block['compact']);
$continued = !empty($block['continuation']);
$cellValue = static function (mixed $cell): array {
    if (is_array($cell)) {
        return ['value' => (string) ($cell['value'] ?? ''), 'class' => (string) ($cell['class'] ?? ''), 'raw' => (bool) ($cell['raw'] ?? false)];
    }
    return ['value' => (string) $cell, 'class' => '', 'raw' => false];
};
?>
<section class="doc-block">
  <?php if (!empty($block['title'])): ?>
    <h2 class="doc-block-title">
      <?= e((string) $block['title']) ?>
      <?php if ($continued): ?><span class="doc-block-continued">(<?= e(__('document.continued')) ?>)</span><?php endif; ?>
    </h2>
  <?php endif; ?>
  <table class="doc-table<?= $isCompact ? ' doc-table--compact' : '' ?>">
    <colgroup>
      <?php foreach ($columns as $column): ?>
        <col<?= isset($column['width']) ? ' style="width:' . (float) $column['width'] . 'mm"' : '' ?>>
      <?php endforeach; ?>
    </colgroup>
    <thead>
      <tr>
        <?php foreach ($columns as $column): ?>
          <th<?= !empty($column['align']) ? ' style="text-align:' . e((string) $column['align']) . '"' : '' ?>><?= e((string) ($column['label'] ?? '')) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($block['rows'] ?? [] as $row): ?>
        <tr>
          <?php $index = 0; foreach ($row as $cell): ?>
            <?php
            $parsed = $cellValue($cell);
            $class = $parsed['class'];
            $column = $columns[$index] ?? [];
            if ($class === '' && isRtlSafeNumericCell($parsed['value'])) {
                $class = 'cell-num';
            }
            ?>
            <td class="<?= e(trim($class)) ?>"<?= !empty($column['align']) ? ' style="text-align:' . e((string) $column['align']) . '"' : '' ?>>
              <?= $parsed['raw'] ? $parsed['value'] : e($parsed['value']) ?>
            </td>
          <?php $index++; endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <?php if (!empty($block['totals'])): ?>
      <tfoot>
        <tr>
          <?php $index = 0; foreach ($block['totals'] as $cell): ?>
            <?php
            $parsed = $cellValue($cell);
            $column = $columns[$index] ?? [];
            // A count-only total spans the identity columns; a spanning cell
            // gives up its column alignment so the label reads from the start
            // of the row instead of floating in a narrow numbering column.
            $span = is_array($cell) ? max(1, (int) ($cell['colspan'] ?? 1)) : 1;
            ?>
            <td class="<?= e($parsed['class']) ?>"<?= $span > 1 ? ' colspan="' . $span . '"' : '' ?><?= $span === 1 && !empty($column['align']) ? ' style="text-align:' . e((string) $column['align']) . '"' : '' ?>><?= e($parsed['value']) ?></td>
          <?php $index += $span; endforeach; ?>
        </tr>
      </tfoot>
    <?php endif; ?>
  </table>
  <?php if (!empty($block['note'])): ?>
    <div class="doc-notes" style="margin-top:1.6mm;"><?= e((string) $block['note']) ?></div>
  <?php endif; ?>
</section>
