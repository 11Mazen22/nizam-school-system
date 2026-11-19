<!-- Document preview: the actual generated document, not the application page -->
<div class="modal fade" id="nDocPreviewModal" tabindex="-1" aria-hidden="true" aria-labelledby="nDocPreviewTitle">
  <div class="modal-dialog modal-xl modal-dialog-centered n-doc-modal">
    <div class="modal-content">
      <div class="modal-header n-doc-modal-head">
        <div class="n-doc-modal-title">
          <span class="n-doc-modal-icon"><?= icon('file-text') ?></span>
          <div>
            <h2 class="modal-title h6 mb-0" id="nDocPreviewTitle"><?= e(__('document.preview_title')) ?></h2>
            <small class="text-muted" id="nDocPreviewMeta"><?= e(__('document.preview_hint')) ?></small>
          </div>
        </div>
        <div class="n-doc-modal-tools">
          <label class="n-doc-toggle">
            <input type="checkbox" data-doc-option="signatures" checked>
            <span><?= e(__('document.option.signatures')) ?></span>
          </label>
          <label class="n-doc-toggle">
            <input type="checkbox" data-doc-option="watermark">
            <span><?= e(__('document.option.watermark')) ?></span>
          </label>
          <label class="n-doc-toggle">
            <input type="checkbox" data-doc-option="compact">
            <span><?= e(__('document.option.compact')) ?></span>
          </label>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
      </div>
      <div class="modal-body n-doc-preview-body">
        <div class="n-doc-loading" data-doc-loading>
          <span class="n-doc-spinner" aria-hidden="true"></span>
          <span><?= e(__('document.loading')) ?></span>
        </div>
        <div class="n-doc-preview-frame">
          <iframe id="nDocPreviewFrame" title="<?= e(__('document.preview_title')) ?>" loading="lazy"></iframe>
        </div>
        <div class="n-doc-error" data-doc-error hidden></div>
      </div>
      <div class="modal-footer n-doc-modal-foot">
        <span class="n-doc-foot-hint"><?= e(__('document.print_hint')) ?></span>
        <div class="n-doc-foot-actions">
          <button type="button" class="btn btn-outline-secondary" data-doc-action="pdf" data-doc-scope="modal">
            <?= icon('download') ?><span><?= e(__('document.export.pdf')) ?></span>
          </button>
          <button type="button" class="btn btn-outline-secondary" data-doc-action="png" data-doc-scope="modal">
            <?= icon('image') ?><span><?= e(__('document.export.png')) ?></span>
          </button>
          <button type="button" class="btn btn-primary" data-doc-action="print" data-doc-scope="modal">
            <?= icon('printer') ?><span><?= e(__('document.export.print')) ?></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bulk export with real, per-document progress -->
<div class="modal fade" id="nDocBulkModal" tabindex="-1" aria-hidden="true" aria-labelledby="nDocBulkTitle">
  <div class="modal-dialog modal-dialog-centered n-doc-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h6" id="nDocBulkTitle"><?= e(__('document.bulk.title')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div data-doc-bulk-setup>
          <div class="mb-3">
            <label class="form-label" for="nDocBulkScope"><?= e(__('document.bulk.scope')) ?></label>
            <select class="form-select" id="nDocBulkScope"></select>
          </div>
          <div class="mb-1">
            <label class="form-label" for="nDocBulkFormat"><?= e(__('document.bulk.format')) ?></label>
            <select class="form-select" id="nDocBulkFormat">
              <option value="pdf">PDF</option>
              <option value="png">PNG</option>
              <option value="xlsx">Excel (.xlsx)</option>
            </select>
          </div>
        </div>

        <div data-doc-bulk-progress hidden>
          <div class="n-doc-progress-head">
            <strong data-doc-bulk-label><?= e(__('document.bulk.current')) ?></strong>
            <span data-doc-bulk-count class="n-doc-progress-count" dir="ltr"></span>
          </div>
          <div class="n-doc-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-doc-bulk-bar-wrap>
            <span data-doc-bulk-bar style="width:0%"></span>
          </div>
          <p class="n-doc-progress-item" data-doc-bulk-item></p>
          <p class="n-doc-progress-error" data-doc-bulk-error hidden></p>
        </div>

        <div class="n-doc-bulk-done" data-doc-bulk-done hidden>
          <span class="n-doc-bulk-check" aria-hidden="true"><?= icon('check-circle', 'n-icon-xl') ?></span>
          <p class="mb-0" data-doc-bulk-result></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('common.close')) ?></button>
        <button type="button" class="btn btn-outline-danger" data-doc-bulk-cancel hidden><?= e(__('common.cancel')) ?></button>
        <button type="button" class="btn btn-primary" data-doc-bulk-start><?= e(__('document.bulk.start')) ?></button>
      </div>
    </div>
  </div>
</div>

<div id="nDocI18n" hidden
     data-progress="<?= e(__('document.bulk.progress')) ?>"
     data-done="<?= e(__('document.bulk.done')) ?>"
     data-failed="<?= e(__('document.bulk.failed')) ?>"
     data-current="<?= e(__('document.bulk.current')) ?>"
     data-start="<?= e(__('document.bulk.start')) ?>"
     data-bulk-error="<?= e(__('document.errors.generic')) ?>"
     data-popup-blocked="<?= e(__('document.errors.popup_blocked')) ?>"></div>

<div class="n-toast-stack" id="nDocToasts" aria-live="polite" aria-atomic="false"></div>
