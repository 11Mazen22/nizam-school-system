/**
 * Settings -> Authorized signatures: live preview.
 *
 * Deliberately a preview of the *document block*, not a drawing canvas. The
 * school is configuring an authorized name, its role title and the Arabic
 * typeface that distinguishes it from the other signatory; the same values are
 * what the document footer renders. No signature is invented here.
 */
(function () {
  'use strict';

  var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';

  document.querySelectorAll('[data-signature-preview]').forEach(function (form) {
    var nameAr = form.querySelector('[data-preview-name]');
    var nameEn = form.querySelector('[data-preview-name-en]');
    var titleAr = form.querySelector('[data-preview-title]');
    var titleEn = form.querySelector('[data-preview-title-en]');
    var font = form.querySelector('[data-preview-font]');
    var outName = form.querySelector('[data-preview-name-out]');
    var outTitle = form.querySelector('[data-preview-title-out]');
    var signaturePreview = form.querySelector('[data-preview-signature]');
    var stampPreview = form.querySelector('[data-preview-stamp]');
    var signatureInput = form.querySelector('[data-image-input="signature"]');
    var stampInput = form.querySelector('[data-image-input="stamp"]');
    var sizeInput = form.querySelector('[data-preview-size]');
    var weightInput = form.querySelector('[data-preview-weight]');
    var colorInput = form.querySelector('[data-preview-color]');
    var spacingInput = form.querySelector('[data-preview-spacing]');
    var alignInput = form.querySelector('[data-preview-align]');
    var previewBox = form.querySelector('[data-preview-box]');

    if (!outName || !outTitle) {
      return;
    }

    var placeholder = outName.textContent.trim();
    var initialTitle = outTitle.textContent.trim();

    function update() {
      var ar = nameAr ? nameAr.value.trim() : '';
      var en = nameEn ? nameEn.value.trim() : '';
      var name = rtl ? (ar || en) : (en || ar);
      outName.textContent = name || placeholder;

      var tar = titleAr ? titleAr.value.trim() : '';
      var ten = titleEn ? titleEn.value.trim() : '';
      var title = rtl ? (tar || ten) : (ten || tar);
      outTitle.textContent = title || initialTitle;

      if (font) {
        var option = font.options[font.selectedIndex];
        var family = option && option.dataset ? (option.dataset.family || '') : '';
        if (family) {
          outName.style.fontFamily = "'" + family + "', 'Nizam Sans'";
        }
      }

      // The preview uses the same properties the document footer writes, so
      // what the administrator approves is what the next export prints.
      if (sizeInput) {
        var size = parseFloat(sizeInput.value || '10.5');
        if (isFinite(size)) {
          outName.style.fontSize = Math.max(8, Math.min(16, size)) + 'pt';
        }
      }
      if (weightInput) {
        outName.style.fontWeight = weightInput.value;
      }
      if (colorInput) {
        outName.style.color = colorInput.value;
      }
      if (spacingInput) {
        var tracking = parseFloat(spacingInput.value || '0');
        if (isFinite(tracking)) {
          outName.style.letterSpacing = Math.max(0, Math.min(2, tracking)) + 'mm';
        }
      }
      if (alignInput) {
        if (previewBox) {
          previewBox.style.textAlign = alignInput.value;
        }
        outName.style.textAlign = alignInput.value;
        outTitle.style.textAlign = alignInput.value;
      }
    }

    function previewFile(input, target) {
      if (!input || !target) {
        return;
      }
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || !/^image\//.test(file.type)) {
          return;
        }
        var url = URL.createObjectURL(file);
        target.style.backgroundImage = "url('" + url + "')";
        var probe = new Image();
        probe.onload = function () {
          URL.revokeObjectURL(url);
        };
        probe.src = url;
      });
    }

    [nameAr, nameEn, titleAr, titleEn, font, sizeInput, weightInput, colorInput, spacingInput, alignInput].forEach(function (input) {
      if (!input) {
        return;
      }
      input.addEventListener('input', update);
      input.addEventListener('change', update);
    });

    previewFile(signatureInput, signaturePreview);
    previewFile(stampInput, stampPreview);
    update();
  });
})();
