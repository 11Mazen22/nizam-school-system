<?php
/**
 * Document footer.
 *
 * The two authorized signatories are resolved once by the renderer and
 * repeated here according to each role's configured placement (every page by
 * default). A role without a configured name still prints its honest title
 * and a blank signing line -- NIZAM never invents a signature, a name or a
 * stamp. Signature/stamp artwork, when the school uploaded it, is painted by
 * the role-scoped classes declared once in the document head.
 *
 * @var \App\Services\Documents\Document $document
 * @var \App\Services\Documents\DocumentIdentity $identity
 * @var int $pageNumber
 * @var int $totalPages
 * @var callable $pageLabel
 * @var list<array<string,mixed>> $signatories
 */
$visibleSignatories = array_values(array_filter(
    $signatories,
    static function (array $signatory) use ($pageNumber, $totalPages): bool {
        return match ((string) ($signatory['placement'] ?? 'every_page')) {
            'first_page' => $pageNumber === 1,
            'last_page' => $pageNumber === $totalPages,
            default => true,
        };
    }
));
?>
<footer class="doc-footer">
  <?php if ($visibleSignatories !== []): ?>
    <div class="doc-signatures">
      <?php foreach ($visibleSignatories as $signatory): ?>
        <?php
        $slug = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $signatory['role']));
        if ($slug === '') {
            continue;
        }
        $hasName = trim((string) $signatory['name']) !== '';
        // Typography is the school's configuration, resolved once by
        // SignatoryService and bounded there: nothing here trusts a raw row.
        $align = in_array((string) ($signatory['alignment'] ?? 'center'), ['start', 'center', 'end'], true)
            ? (string) ($signatory['alignment'] ?? 'center')
            : 'center';
        $nameStyle = sprintf(
            "font-family:'%s', 'Nizam Sans';font-size:%spt;font-weight:%d;color:%s;letter-spacing:%smm;",
            (string) $signatory['font_family'],
            number_format((float) ($signatory['font_size'] ?? 10.5), 1, '.', ''),
            (int) ($signatory['font_weight'] ?? 600),
            (string) ($signatory['text_color'] ?? '#1F2937'),
            number_format((float) ($signatory['letter_spacing'] ?? 0), 2, '.', '')
        );
        ?>
        <?php /* Alignment applies to the whole block, typography to the name. */ ?>
        <div class="doc-signature<?= $hasName ? ' doc-signature--configured' : '' ?>" data-signature-role="<?= e($slug) ?>" style="text-align:<?= e($align) ?>">
          <div class="doc-signature-area">
            <?php if ($signatory['signature'] !== null): ?>
              <span class="doc-signature-image" data-role="<?= e($slug) ?>" role="img"
                    aria-label="<?= e($signatory['name'] !== '' ? $signatory['name'] : $signatory['title']) ?>"></span>
            <?php endif; ?>
            <?php if ($signatory['stamp'] !== null): ?>
              <span class="doc-signature-stamp" data-role="<?= e($slug) ?>" role="img"
                    aria-label="<?= e(__('document.stamp')) ?>"></span>
            <?php endif; ?>
          </div>
          <div class="doc-signature-line" aria-hidden="true"></div>
          <?php if ($hasName): ?>
            <div class="doc-signature-name" style="<?= e($nameStyle) ?>"><?= e($signatory['name']) ?></div>
          <?php endif; ?>
          <div class="doc-signature-role"><?= e($signatory['title']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="doc-footer-meta">
    <div><?= e(__('document.issued_on')) ?>: <?= e($identity->issueDate) ?></div>
    <div><?= e(__('document.generated_by')) ?></div>
    <div class="doc-page-number"><?= e($pageLabel($pageNumber)) ?></div>
  </div>
</footer>
