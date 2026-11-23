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
 * Before producing any file the renderer measures every designed page. If a
 * page's content is taller than its paper (which the composer tries hard to
 * prevent, but a font substitution or a long unbroken string can still
 * cause), it applies one of three tightening levels and measures again. That
 * way an exported page is never silently clipped underneath the footer, and
 * the documented page numbering always matches the physical PDF.
 *
 * Usage:
 *   node bin/document-render.mjs --html doc.html --out doc.pdf --mode pdf
 *   node bin/document-render.mjs --html doc.html --out page  --mode png --scale 3
 *   node bin/document-render.mjs --html doc.html --out any   --mode measure
 *
 * Output (single line JSON on stdout):
 *   { ok, pages, fitted, overflow, width, height, bytes }
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
const scale = Math.max(1, Math.min(3, parseFloat(arg('scale', '2')) || 2));

if (!htmlPath || !outPath) {
  console.error('document-render: --html and --out are required');
  process.exit(2);
}
if (!fs.existsSync(htmlPath)) {
  console.error('document-render: input not found: ' + htmlPath);
  process.exit(2);
}

const browser = await chromium.launch({
  args: [
    '--font-render-hinting=none',
    '--disable-lcd-text',
    '--force-color-profile=srgb',
    '--disable-dev-shm-usage',
  ],
});

try {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 1800 },
    deviceScaleFactor: mode === 'png' ? scale : 1,
  });
  const page = await context.newPage();
  page.on('pageerror', (error) => console.error('document-render: page error: ' + error.message));

  await page.goto(pathToFileURL(path.resolve(htmlPath)).href, {
    waitUntil: 'load',
    timeout: 60000,
  });

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

  // Printed geometry is the authoritative one: measure under the same media
  // the PDF is produced with, so measurement and output cannot disagree.
  if (mode !== 'png') {
    await page.emulateMedia({ media: 'print' });
  }

  const pageCount = await page.$$eval('.doc-page', (nodes) => nodes.length);
  if (pageCount === 0) {
    throw new Error('no .doc-page elements found in the document');
  }

  const measure = () => page.evaluate(() => {
    let overflowing = 0;
    let worst = 0;
    document.querySelectorAll('.doc-page').forEach((paper) => {
      const flow = paper.querySelector('.doc-flow');
      if (!flow) {
        return;
      }
      // A sub-pixel rounding difference is not an overflow; a clipped row is.
      const delta = flow.scrollHeight - flow.clientHeight;
      if (delta > 2) {
        overflowing += 1;
        worst = Math.max(worst, delta);
      }
    });
    return { overflowing, worst };
  });

  let fitted = 0;
  let state = await measure();

  while (state.overflowing > 0 && fitted < 3) {
    fitted += 1;
    await page.evaluate((level) => document.body.classList.add('doc-autofit-' + level), fitted);
    await page.evaluate(() => new Promise((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(resolve));
    }));
    state = await measure();
  }

  const geometry = await page.$eval('.doc-page', (paper) => {
    const box = paper.getBoundingClientRect();
    return { width: Math.round(box.width), height: Math.round(box.height) };
  });

  const basePayload = {
    ok: true,
    pages: pageCount,
    fitted,
    overflow: state.overflowing > 0,
    worst: Math.round(state.worst),
    width: geometry.width,
    height: geometry.height,
  };

  if (mode === 'measure') {
    process.stdout.write(JSON.stringify(basePayload) + '\n');
  } else if (mode === 'pdf') {
    await page.pdf({
      path: outPath,
      printBackground: true,
      preferCSSPageSize: true,
      displayHeaderFooter: false,
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      tagged: true,
    });
    const bytes = fs.statSync(outPath).size;
    process.stdout.write(JSON.stringify({ ...basePayload, bytes }) + '\n');
  } else {
    fs.mkdirSync(path.dirname(path.resolve(outPath)), { recursive: true });
    const handles = await page.$$('.doc-page');
    for (let index = 0; index < handles.length; index++) {
      await handles[index].screenshot({
        path: `${outPath}-${index + 1}.png`,
        type: 'png',
        animations: 'disabled',
      });
    }
    process.stdout.write(JSON.stringify({ ...basePayload, pages: handles.length, bytes: 0 }) + '\n');
  }
} catch (error) {
  console.error('document-render: ' + (error && error.stack ? error.stack : String(error)));
  process.exitCode = 1;
} finally {
  await browser.close();
}
