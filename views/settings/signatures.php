<?php
/**
 * Settings -> Authorized signatures.
 *
 * Two institutional signatories, configured once. The form never asks for a
 * signature to be drawn in the browser: the school either types the
 * authorized name and prints it in a chosen Arabic typeface, or uploads the
 * actual scanned signature/stamp that the institution authorises.
 *
 * @var ?string $error
 * @var int $errorId
 * @var list<array<string,mixed>> $rows
 * @var array<string,array{family:string,label:string,regular:string,bold:?string}> $fonts
 * @var list<string> $placements
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'signatures';
$pageScripts = ['/assets/js/signature-preview.js'];

require dirname(__DIR__) . '/layout/start.php';
require __DIR__ . '/_nav.php';
?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.signatures')) ?></h2>
    <p><?= e(__('settings.desc.signatures')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <div class="alert alert-info d-flex gap-2 mb-4">
      <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
      <div class="small"><?= e(__('settings.signatures.intro')) ?></div>
    </div>

    <?php if ($rows === []): ?>
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('edit') ?></div>
        <p class="mb-0"><?= e(__('settings.signatures.empty')) ?></p>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <?php foreach ($rows as $row): ?>
        <?php
        $id = (int) $row['id'];
        $role = (string) $row['role_code'];
        $fontKey = (string) ($row['font_key'] ?? 'aref-ruqaa');
        $fontFamily = $fonts[$fontKey]['family'] ?? $fonts['aref-ruqaa']['family'];
        $configured = (bool) ($row['configured'] ?? false);
        $placement = (string) ($row['placement'] ?? 'every_page');
        $nameAr = (string) ($row['name_ar'] ?? '');
        $nameEn = (string) ($row['name_en'] ?? '');
        $titleAr = (string) ($row['title_ar'] ?? '');
        $titleEn = (string) ($row['title_en'] ?? '');
        $previewName = $nameAr !== '' ? $nameAr : $nameEn;
        $previewTitle = $titleAr !== '' ? $titleAr : ($titleEn !== '' ? $titleEn : (string) $row['role_label']);
        ?>
        <div class="col-xl-6">
          <form method="post" action="/settings/signatures/<?= $id ?>" enctype="multipart/form-data"
                class="n-settings-card h-100<?= $errorId === $id ? ' border border-danger' : '' ?>"
                data-unsaved-guard data-loading-submit data-signature-preview>
            <?= CsrfMiddleware::field() ?>
            <div class="n-settings-card-head d-flex justify-content-between align-items-start gap-2">
              <div>
                <h3 class="h6 mb-1"><?= e($row['role_label']) ?></h3>
                <p class="mb-0 small text-body-secondary"><?= e(__('settings.signatures.role_help')) ?></p>
              </div>
              <span class="badge <?= $configured ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= e($configured ? __('settings.signatures.configured') : __('settings.signatures.not_configured')) ?>
              </span>
            </div>
            <div class="n-settings-card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="name_ar_<?= $id ?>"><?= e(__('settings.signatures.name_ar')) ?></label>
                  <input type="text" class="form-control" id="name_ar_<?= $id ?>" name="name_ar"
                         value="<?= e($nameAr) ?>" maxlength="150" dir="rtl" autocomplete="off"
                         data-preview-name>
                  <div class="form-text"><?= e(__('settings.signatures.name_help')) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="name_en_<?= $id ?>"><?= e(__('settings.signatures.name_en')) ?></label>
                  <input type="text" class="form-control" id="name_en_<?= $id ?>" name="name_en"
                         value="<?= e($nameEn) ?>" maxlength="150" autocomplete="off"
                         data-preview-name-en>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="title_ar_<?= $id ?>"><?= e(__('settings.signatures.title_ar')) ?></label>
                  <input type="text" class="form-control" id="title_ar_<?= $id ?>" name="title_ar"
                         value="<?= e($titleAr) ?>" maxlength="100" dir="rtl"
                         data-preview-title>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="title_en_<?= $id ?>"><?= e(__('settings.signatures.title_en')) ?></label>
                  <input type="text" class="form-control" id="title_en_<?= $id ?>" name="title_en"
                         value="<?= e($titleEn) ?>" maxlength="100" data-preview-title-en>
                </div>
              </div>

              <div class="row">
                <div class="col-md-7 mb-3">
                  <label class="form-label" for="font_<?= $id ?>"><?= e(__('settings.signatures.font')) ?></label>
                  <select class="form-select" id="font_<?= $id ?>" name="font_key" data-preview-font>
                    <?php foreach ($fonts as $key => $font): ?>
                      <option value="<?= e($key) ?>" data-family="<?= e($font['family']) ?>"
                        <?= $key === $fontKey ? 'selected' : '' ?>>
                        <?= e(__($font['label'])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text"><?= e(__('settings.signatures.font_help')) ?></div>
                </div>
                <div class="col-md-5 mb-3">
                  <label class="form-label" for="placement_<?= $id ?>"><?= e(__('settings.signatures.placement')) ?></label>
                  <select class="form-select" id="placement_<?= $id ?>" name="placement">
                    <?php foreach ($placements as $option): ?>
                      <option value="<?= e($option) ?>" <?= $option === $placement ? 'selected' : '' ?>>
                        <?= e(__('settings.signatures.placement.' . $option)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <?php
              $fontSize = (string) ($row['font_size'] ?? '10.5');
              $fontWeight = (int) ($row['font_weight'] ?? 600);
              $textColor = (string) ($row['text_color'] ?? '#1F2937');
              $letterSpacing = (string) ($row['letter_spacing'] ?? '0');
              $alignment = (string) ($row['alignment'] ?? 'center');
              ?>
              <div class="row">
                <div class="col-6 col-md-3 mb-3">
                  <label class="form-label" for="font_size_<?= $id ?>"><?= e(__('settings.signatures.font_size')) ?></label>
                  <input type="number" class="form-control" id="font_size_<?= $id ?>" name="font_size"
                         min="8" max="16" step="0.5" value="<?= e($fontSize) ?>" data-preview-size>
                </div>
                <div class="col-6 col-md-3 mb-3">
                  <label class="form-label" for="font_weight_<?= $id ?>"><?= e(__('settings.signatures.font_weight')) ?></label>
                  <select class="form-select" id="font_weight_<?= $id ?>" name="font_weight" data-preview-weight>
                    <?php foreach ([400, 600, 700] as $weight): ?>
                      <option value="<?= $weight ?>" <?= $fontWeight === $weight ? 'selected' : '' ?>>
                        <?= e(__('settings.signatures.weight.' . $weight)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6 col-md-3 mb-3">
                  <label class="form-label" for="text_color_<?= $id ?>"><?= e(__('settings.signatures.text_color')) ?></label>
                  <input type="color" class="form-control form-control-color w-100" id="text_color_<?= $id ?>"
                         name="text_color" value="<?= e($textColor) ?>" data-preview-color>
                </div>
                <div class="col-6 col-md-3 mb-3">
                  <label class="form-label" for="letter_spacing_<?= $id ?>"><?= e(__('settings.signatures.letter_spacing')) ?></label>
                  <input type="number" class="form-control" id="letter_spacing_<?= $id ?>" name="letter_spacing"
                         min="0" max="2" step="0.05" value="<?= e($letterSpacing) ?>" data-preview-spacing>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5 mb-3">
                  <label class="form-label" for="alignment_<?= $id ?>"><?= e(__('settings.signatures.alignment')) ?></label>
                  <select class="form-select" id="alignment_<?= $id ?>" name="alignment" data-preview-align>
                    <?php foreach (['start', 'center', 'end'] as $option): ?>
                      <option value="<?= e($option) ?>" <?= $alignment === $option ? 'selected' : '' ?>>
                        <?= e(__('settings.signatures.align.' . $option)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label d-block"><?= e(__('settings.signatures.preview')) ?></label>
                <div class="n-signature-preview" data-preview-box>
                  <div class="n-signature-preview-area">
                    <span class="n-signature-preview-image" data-preview-signature
                          <?= !empty($row['signature_preview']) ? 'style="background-image:url(\'' . e((string) $row['signature_preview']) . '\')"' : '' ?>></span>
                    <span class="n-signature-preview-stamp" data-preview-stamp
                          <?= !empty($row['stamp_preview']) ? 'style="background-image:url(\'' . e((string) $row['stamp_preview']) . '\')"' : '' ?>></span>
                  </div>
                  <div class="n-signature-preview-line"></div>
                  <div class="n-signature-preview-name" data-preview-name-out
                       style="font-family:'<?= e($fontFamily) ?>', 'Nizam Sans';font-size:<?= e($fontSize) ?>pt;font-weight:<?= (int) $fontWeight ?>;color:<?= e($textColor) ?>;letter-spacing:<?= e($letterSpacing) ?>mm">
                    <?= e($previewName !== '' ? $previewName : __('settings.signatures.preview_placeholder')) ?>
                  </div>
                  <div class="n-signature-preview-title" data-preview-title-out><?= e($previewTitle) ?></div>
                </div>
                <div class="form-text"><?= e(__('settings.signatures.preview_help')) ?></div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="signature_<?= $id ?>"><?= e(__('settings.signatures.signature_image')) ?></label>
                  <input type="file" class="form-control" id="signature_<?= $id ?>" name="signature"
                         accept="image/png,image/jpeg" data-image-input="signature">
                  <div class="form-text"><?= e(__('settings.signatures.image_help')) ?></div>
                  <?php if (!empty($row['signature_path'])): ?>
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="remove_signature_<?= $id ?>">
                      <label class="form-check-label small" for="remove_signature_<?= $id ?>"><?= e(__('settings.signatures.remove_image')) ?></label>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="stamp_<?= $id ?>"><?= e(__('settings.signatures.stamp_image')) ?></label>
                  <input type="file" class="form-control" id="stamp_<?= $id ?>" name="stamp"
                         accept="image/png,image/jpeg" data-image-input="stamp">
                  <div class="form-text"><?= e(__('settings.signatures.stamp_help')) ?></div>
                  <?php if (!empty($row['stamp_path'])): ?>
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" name="remove_stamp" value="1" id="remove_stamp_<?= $id ?>">
                      <label class="form-check-label small" for="remove_stamp_<?= $id ?>"><?= e(__('settings.signatures.remove_image')) ?></label>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                       id="active_<?= $id ?>" <?= dbBool($row['is_active'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="active_<?= $id ?>"><?= e(__('settings.signatures.active')) ?></label>
                <div class="form-text"><?= e(__('settings.signatures.active_help')) ?></div>
              </div>

              <div class="n-settings-save">
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                  <?= icon('check') ?>
                  <span><?= e(__('common.save')) ?></span>
                </button>
              </div>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>
