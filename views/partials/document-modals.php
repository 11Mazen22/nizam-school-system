<?php
/**
 * The NIZAM document studio: one preview dialog and one bulk dialog for the
 * whole application.
 *
 * The preview shows the generated document itself inside a frame, with the
 * same typography, geometry and data the PDF will contain. Options are applied
 * by message, so switching signatures or the watermark never re-queries the
 * school database; zoom and page navigation operate on the already-rendered
 * document.
 *
 * The bulk dialog is deliberately honest: it shows the real number of files
 * produced, the file currently being rendered, and every document that failed
 * -- never a simulated percentage.
 */
?>
<div class="modal fade n-doc-modal" id="nDocPreviewModal" tabindex="-1" aria-hidden="true" aria-labelledby="nDocPreviewTitle">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <header class="n-doc-head">
        <span class="n-doc-head-icon" aria-hidden="true"><?= icon('file-text') ?></span>
        <div class="n-doc-head-copy">
          <h2 class="n-doc-head-title" id="nDocPreviewTitle" data-doc-preview-title><?= e(__('document.preview_title')) ?></h2>
          <p class="n-doc-head-meta" data-doc-preview-meta><?= e(__('document.preview_hint_short')) ?></p>
        </div>

        <div class="n-doc-switches" role="group" aria-label="<?= e(__('document.options')) ?>">
          <label class="n-doc-switch">
            <input type="checkbox" data-doc-option="signatures" checked>
            <span><?= e(__('document.option.signatures')) ?></span>
          </label>
          <label class="n-doc-switch">
            <input type="checkbox" data-doc-option="watermark">
            <span><?= e(__('document.option.watermark')) ?></span>
          </label>
          <label class="n-doc-switch">
            <input type="checkbox" data-doc-option="compact">
            <span><?= e(__('document.option.compact')) ?></span>
          </label>
        </div>

        <button type="button" class="n-doc-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>">
          <?= icon('close', 'n-icon-sm') ?>
        </button>
      </header>

      <div class="n-doc-stage">
        <div class="n-doc-loading" data-doc-loading>
          <span class="n-doc-spinner" aria-hidden="true"></span>
          <span><?= e(__('document.loading')) ?></span>
        </div>

        <div class="n-doc-frame-wrap" data-doc-frame-wrap>
          <iframe id="nDocPreviewFrame" class="n-doc-frame" title="<?= e(__('document.preview_title')) ?>"></iframe>
        </div>

        <div class="n-doc-error" data-doc-error hidden role="alert">
          <span class="n-doc-error-icon" aria-hidden="true"><?= icon('alert-triangle', 'n-icon-sm') ?></span>
          <div class="n-doc-error-copy">
            <strong><?= e(__('document.errors.title')) ?></strong>
            <p data-doc-error-message></p>
          </div>
          <button type="button" class="n-doc-error-retry" data-doc-retry>
            <?= icon('refresh', 'n-icon-sm') ?><span><?= e(__('common.retry')) ?></span>
          </button>
        </div>

        <div class="n-doc-zoombar" role="toolbar" aria-label="<?= e(__('document.preview_title')) ?>">
          <button type="button" data-doc-zoom="out" aria-label="<?= e(__('document.preview.zoom_out')) ?>">−</button>
          <output class="n-doc-zoom-level" data-doc-zoom-level>100%</output>
          <button type="button" data-doc-zoom="in" aria-label="<?= e(__('document.preview.zoom_in')) ?>">+</button>
          <span class="n-doc-zoom-sep" aria-hidden="true"></span>
          <button type="button" class="n-doc-zoom-fit" data-doc-zoom="fit"><?= e(__('document.preview.fit')) ?></button>
          <span class="n-doc-zoom-sep" aria-hidden="true"></span>
          <button type="button" data-doc-page="prev" aria-label="<?= e(__('document.preview.previous')) ?>">
            <?= icon('chevron-left', 'n-icon-sm') ?>
          </button>
          <span class="n-doc-page-label" data-doc-page-label dir="ltr">1 / 1</span>
          <button type="button" data-doc-page="next" aria-label="<?= e(__('document.preview.next')) ?>">
            <?= icon('chevron-right', 'n-icon-sm') ?>
          </button>
        </div>
      </div>

      <footer class="n-doc-foot">
        <p class="n-doc-foot-hint"><?= e(__('document.print_hint')) ?></p>
        <div class="n-doc-foot-actions">
          <div class="n-doc-downloads" role="group" aria-label="<?= e(__('document.download_as')) ?>">
            <button type="button" class="n-doc-download" data-doc-format="pdf" title="<?= e(__('document.format.pdf')) ?>">
              <?= icon('file-text', 'n-icon-sm') ?><span>PDF</span>
            </button>
            <button type="button" class="n-doc-download" data-doc-format="png" title="<?= e(__('document.format.png')) ?>">
              <?= icon('image', 'n-icon-sm') ?><span>PNG</span>
            </button>
            <button type="button" class="n-doc-download" data-doc-format="xlsx" title="<?= e(__('document.format.xlsx')) ?>">
              <?= icon('grid', 'n-icon-sm') ?><span>XLSX</span>
            </button>
            <button type="button" class="n-doc-download" data-doc-format="csv" title="<?= e(__('document.format.csv')) ?>">
              <?= icon('list', 'n-icon-sm') ?><span>CSV</span>
            </button>
            <button type="button" class="n-doc-download" data-doc-format="html" title="<?= e(__('document.format.html')) ?>">
              <?= icon('globe', 'n-icon-sm') ?><span>HTML</span>
            </button>
          </div>
          <button type="button" class="n-doc-print" data-doc-print>
            <?= icon('printer', 'n-icon-sm') ?><span><?= e(__('document.print')) ?></span>
          </button>
        </div>
      </footer>
    </div>
  </div>
</div>

<div class="modal fade n-doc-modal" id="nDocBulkModal" tabindex="-1" aria-hidden="true" aria-labelledby="nDocBulkTitle">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <header class="n-doc-head n-doc-head--simple">
        <span class="n-doc-head-icon" aria-hidden="true"><?= icon('archive') ?></span>
        <div class="n-doc-head-copy">
          <h2 class="n-doc-head-title" id="nDocBulkTitle"><?= e(__('document.bulk.title')) ?></h2>
          <p class="n-doc-head-meta"><?= e(__('document.bulk.format_hint')) ?></p>
        </div>
        <button type="button" class="n-doc-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>">
          <?= icon('close', 'n-icon-sm') ?>
        </button>
      </header>

      <div class="n-doc-body">
        <div data-doc-bulk-setup>
          <div class="n-doc-field">
            <label class="n-doc-label" for="nDocBulkScope"><?= e(__('document.bulk.scope')) ?></label>
            <select class="n-doc-select" id="nDocBulkScope"></select>
          </div>

          <div class="n-doc-field">
            <span class="n-doc-label"><?= e(__('document.bulk.format')) ?></span>
            <div class="n-doc-formats" data-doc-bulk-formats role="radiogroup" aria-label="<?= e(__('document.bulk.format')) ?>"></div>
          </div>

          <p class="n-doc-summary" data-doc-bulk-summary></p>
        </div>

        <div data-doc-bulk-progress hidden>
          <div class="n-doc-progress-head">
            <span class="n-doc-progress-count" data-doc-bulk-count dir="ltr">0 / 0</span>
            <span class="n-doc-progress-percent" data-doc-bulk-percent dir="ltr">0%</span>
          </div>
          <div class="n-doc-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-doc-bulk-bar-wrap>
            <span data-doc-bulk-bar style="width:0%"></span>
          </div>
          <p class="n-doc-progress-current">
            <span data-doc-bulk-label><?= e(__('document.bulk.current')) ?></span>
            <strong data-doc-bulk-current></strong>
          </p>
          <ul class="n-doc-progress-log" data-doc-bulk-log aria-live="polite"></ul>
          <p class="n-doc-error-inline" data-doc-bulk-error hidden></p>
        </div>

        <div class="n-doc-done" data-doc-bulk-done hidden>
          <span class="n-doc-done-icon" aria-hidden="true"><?= icon('check-circle') ?></span>
          <h3><?= e(__('document.bulk.complete_title')) ?></h3>
          <p class="n-doc-done-file" data-doc-bulk-result dir="auto"></p>
          <p class="n-doc-done-stats" data-doc-bulk-stats></p>
          <ul class="n-doc-done-failures" data-doc-bulk-failures></ul>
        </div>
      </div>

      <footer class="n-doc-foot n-doc-foot--between">
        <button type="button" class="n-doc-ghost" data-bs-dismiss="modal"><?= e(__('common.close')) ?></button>
        <div class="n-doc-foot-actions">
          <button type="button" class="n-doc-ghost n-doc-ghost--danger" data-doc-bulk-cancel hidden>
            <?= e(__('common.cancel')) ?>
          </button>
          <button type="button" class="n-doc-ghost" data-doc-bulk-retry hidden>
            <?= icon('refresh', 'n-icon-sm') ?><span><?= e(__('common.retry')) ?></span>
          </button>
          <button type="button" class="n-doc-print" data-doc-bulk-start>
            <?= icon('archive', 'n-icon-sm') ?><span><?= e(__('document.bulk.start')) ?></span>
          </button>
        </div>
      </footer>
    </div>
  </div>
</div>

<div id="nDocI18n" hidden
     data-progress="<?= e(__('document.bulk.progress')) ?>"
     data-current="<?= e(__('document.bulk.current')) ?>"
     data-start="<?= e(__('document.bulk.start')) ?>"
     data-done="<?= e(__('document.bulk.done')) ?>"
     data-failed="<?= e(__('document.bulk.failed')) ?>"
     data-saved="<?= e(__('document.saved')) ?>"
     data-files="<?= e(__('document.files')) ?>"
     data-files-one="<?= e(__('document.files_one')) ?>"
     data-pages="<?= e(__('document.preview.pages')) ?>"
     data-bulk-error="<?= e(__('document.errors.generic')) ?>"
     data-session-expired="<?= e(__('document.session_expired')) ?>"
     data-print-slow="<?= e(__('document.print_slow')) ?>"
     data-print="<?= e(__('document.print')) ?>"
     data-downloaded="<?= e(__('document.bulk.downloaded')) ?>"
     data-retry="<?= e(__('common.retry')) ?>"
     data-no-data="<?= e(__('document.no_data')) ?>"
     data-summary="<?= e(__('document.bulk.summary')) ?>"
     data-summary-one="<?= e(__('document.bulk.summary_one')) ?>"></div>

<div class="n-toast-stack" id="nDocToasts" aria-live="polite" aria-atomic="false"></div>
