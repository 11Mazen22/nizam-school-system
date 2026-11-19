#!/usr/bin/env node
/**
 * NIZAM document renderer.
 *
 * Prints a *document* -- never an application page -- from a fully
 * self-contained HTML file (fonts, seal and colours already embedded) into a
 * vector PDF or a set of high-resolution PNGs, one per designed page.
 *
 * Chromium is the rendering engine on purpose: it shapes Arabic correctly,
 * embeds the real fonts, keeps text selectable and searchable in the PDF, and
 * honours the document's own millimetre page geometry through
 * preferCSSPageSize. No browser header/footer is ever emitted.
 *
 * Usage:
 *   node bin/document-render.mjs --html doc.html --out doc.pdf --mode pdf
 *   node bin/document-render.mjs --html doc.html --out page --mode png --scale 3
 */
import { chromium } from 'playwright';
import { pathToFileURL } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';

function arg(name, fallback = null) {
  const index = process.argv.indexOf('--' + name);
  return index > -1 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

const htmlPath = arg('html');
const outPath = arg('out');
const mode = arg('mode', 'pdf');
const scale = Math.max(1, Math.min(4, parseFloat(arg('scale', '2')) || 2));

if (!htmlPath || !outPath) {
  console.error('document-render: --html and --out are required');
  process.exit(2);
}
if (!fs.existsSync(htmlPath)) {
  console.error('document-render: input not found: ' + htmlPath);
  process.exit(2);
}

const browser = await chromium.launch({
  args: ['--font-render-hinting=none', '--disable-lcd-text', '--force-color-profile=srgb'],
});

try {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 1800 },
    deviceScaleFactor: mode === 'png' ? scale : 1,
  });
  const page = await context.newPage();
  page.on('pageerror', (error) => console.error('document-render: page error: ' + error.message));

  await page.goto(pathToFileURL(path.resolve(htmlPath)).href, { waitUntil: 'load', timeout: 60000 });

  // Never capture a document with a fallback font: wait for the embedded
  // faces to actually finish loading before a single pixel is produced.
  await page.evaluate(async () => {
    if (document.fonts && document.fonts.ready) {
      await document.fonts.ready;
    }
    await Promise.all(Array.from(document.images).map((image) => (
      image.complete ? Promise.resolve() : new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      })
    )));
  });

  const pageCount = await page.$$eval('.doc-page', (nodes) => nodes.length);
  if (pageCount === 0) {
    throw new Error('no .doc-page elements found in the document');
  }

  if (mode === 'pdf') {
    await page.emulateMedia({ media: 'print' });
    await page.pdf({
      path: outPath,
      printBackground: true,
      preferCSSPageSize: true,
      displayHeaderFooter: false,
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      tagged: true,
    });
    const bytes = fs.statSync(outPath).size;
    process.stdout.write(JSON.stringify({ ok: true, pages: pageCount, bytes }));
  } else {
    fs.mkdirSync(path.dirname(path.resolve(outPath)), { recursive: true });
    const handles = await page.$$('.doc-page');
    for (let index = 0; index < handles.length; index++) {
      await handles[index].screenshot({ path: `${outPath}-${index + 1}.png`, type: 'png' });
    }
    process.stdout.write(JSON.stringify({ ok: true, pages: handles.length }));
  }
} catch (error) {
  console.error('document-render: ' + (error && error.stack ? error.stack : String(error)));
  process.exitCode = 1;
} finally {
  await browser.close();
}
