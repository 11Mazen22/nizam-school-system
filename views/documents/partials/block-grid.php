<?php
/**
 * @var array<string, mixed> $block
 */
$continued = !empty($block['continuation']);
?>
<section class="doc-block">
  <h2 class="doc-block-title">
    <?= e((string) ($block['title'] ?? '')) ?>
    <?php if ($continued): ?><span class="doc-block-continued">(<?= e(__('document.continued')) ?>)</span><?php endif; ?>
  </h2>
  <table class="doc-grid">
    <colgroup>
      <col style="width:20mm">
      <?php foreach ($block['head'] ?? [] as $ignored): ?>
        <col>
      <?php endforeach; ?>
    </colgroup>
    <thead>
      <tr>
        <th class="grid-corner"><?= e((string) ($block['corner'] ?? '')) ?></th>
        <?php foreach ($block['head'] ?? [] as $heading): ?>
          <th><?= e((string) $heading) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($block['rows'] ?? [] as $row): ?>
        <tr>
          <td class="grid-period">
            <?= e((string) ($row['label'] ?? '')) ?>
            <?php if (!empty($row['meta'])): ?><small><?= e((string) $row['meta']) ?></small><?php endif; ?>
          </td>
          <?php foreach ($row['cells'] ?? [] as $cell): ?>
            <?php $kind = (string) ($cell['type'] ?? 'empty'); ?>
            <?php if ($kind === 'break'): ?>
              <td class="grid-break"><?= e((string) ($cell['text'] ?? __('document.break'))) ?></td>
            <?php elseif ($kind === 'empty'): ?>
              <td class="grid-empty">—</td>
            <?php else: ?>
              <td class="tone-<?= (int) ($cell['tone'] ?? 1) ?><?= !empty($cell['clash']) ? ' grid-cell--clash' : '' ?><?= !empty($cell['resolved']) ? ' grid-cell--resolved' : '' ?><?= !empty($cell['unassigned']) ? ' grid-cell--unassigned' : '' ?>"
                  <?php if (!empty($cell['other'])): ?>title="<?= e(__('document.clash_other_class')) ?>: <?= e((string) $cell['other']) ?>"<?php endif; ?>>
                <span class="grid-cell">
                  <span class="grid-subject"><?= e((string) ($cell['subject'] ?? '')) ?></span>
                  <?php if (!empty($cell['teacher'])): ?><span class="grid-meta"><?= e((string) $cell['teacher']) ?></span><?php endif; ?>
                  <?php if (!empty($cell['clash'])): ?>
                    <?php /* One compact line: the other class is repeated in the per-class
                             legend and in the conflicts workbook, so the paper only needs
                             the marker and the class code to stay readable. */ ?>
                    <span class="grid-clash" title="<?= e(__('document.clash_other_class')) ?>: <?= e((string) ($cell['other'] ?? '')) ?>">⚠ <?= e(trim((string) ($cell['other'] ?? '')) !== '' ? (string) $cell['other'] : __('document.clash_badge')) ?></span>
                  <?php elseif (!empty($cell['resolved'])): ?>
                    <span class="grid-resolved" title="<?= e(__('document.clash_printed_teacher')) ?>: <?= e((string) ($cell['printed_teacher'] ?? '')) ?>">✓ <?= e(__('document.clash_status_resolved')) ?></span>
                  <?php elseif (!empty($cell['unassigned'])): ?>
                    <span class="grid-unassigned"><?= e(__('document.legend_unassigned')) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($cell['class'])): ?><span class="grid-class"><?= e((string) $cell['class']) ?></span><?php endif; ?>
                </span>
              </td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!empty($block['legend'])): ?>
    <div class="doc-legend" style="margin-top:1.8mm;">
      <?php foreach ($block['legend'] as $item): ?>
        <span class="doc-legend-item">
          <span class="doc-legend-swatch <?= e((string) ($item['class'] ?? '')) ?>" style="<?= e((string) ($item['style'] ?? '')) ?>"></span>
          <?= e((string) ($item['label'] ?? '')) ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
