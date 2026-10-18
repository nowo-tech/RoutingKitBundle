import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';
import { ensurePath, ensureRedirect } from './helpers';

/**
 * REQ-DEMO-013 — the panel in the demo frame (demo nav + UiKit page + Symfony toolbar), with realistic rows.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1280;
const PAD = 16;
const CHROME = 'nav, header, main';

/**
 * REQ-DEMO-013 framing: wait for the WebProfiler toolbar, grow the viewport so the whole page fits above it, then
 * capture the padded union of the demo chrome (nav / header / main) and the toolbar.
 */
async function fitViewport(page: Page): Promise<void> {
  const toolbar = page.locator('.sf-toolbar .sf-toolbarreset');
  await expect(toolbar).toBeVisible({ timeout: 15_000 });
  // the toolbar first renders a "Loading…" stub (itself a .sf-toolbar-block): wait for the real panels
  await expect(page.locator('.sf-toolbar').first()).not.toContainText('Loading', { timeout: 15_000 });
  const toolbarHeight = Math.ceil((await toolbar.boundingBox())?.height ?? 36);
  const contentBottom = await page.evaluate((sel) => Math.max(...[...document.querySelectorAll(sel)]
    .filter((el) => !el.closest('.sf-toolbar, dialog'))
    .map((el) => el.getBoundingClientRect().bottom + window.scrollY)), CHROME);
  await page.setViewportSize({ width: WIDTH, height: Math.max(720, Math.ceil(contentBottom) + 24 + toolbarHeight) });
  await page.evaluate(() => window.scrollTo(0, 0));
}

async function shoot(page: Page, file: string): Promise<void> {
  await expect(page.locator('.sf-toolbar').first()).not.toContainText('Loading');
  const box = await page.evaluate((sel) => {
    const rects = [...document.querySelectorAll(`${sel}, .sf-toolbar .sf-toolbarreset`)]
      .map((el) => el.getBoundingClientRect())
      .filter((r) => r.width > 0 && r.height > 0);
    return {
      x: Math.min(...rects.map((r) => r.left)),
      y: Math.min(...rects.map((r) => r.top)),
      r: Math.max(...rects.map((r) => r.right)),
      b: Math.max(...rects.map((r) => r.bottom)),
    };
  }, CHROME);
  const vp = page.viewportSize()!;
  const x = Math.max(0, box.x - PAD);
  const y = Math.max(0, box.y - PAD);
  await page.screenshot({
    path: resolve(outDir, `${file}.png`),
    clip: { x, y, width: Math.min(vp.width, box.r + PAD) - x, height: Math.min(vp.height, box.b + PAD) - y },
  });
}

test.use({ viewport: { width: WIDTH, height: 900 } });

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('panel screenshots', () => {
  test('paths list', async ({ page }) => {
    await ensurePath(page, 'app_about', 'es', '/sobre-nosotros');
    await ensurePath(page, 'app_blog_show', 'es', '/blog/{slug}');
    await ensurePath(page, 'app_about', 'en', '/about-us');

    await page.goto('/_routing/');
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await fitViewport(page);
    await shoot(page, 'paths-list');
  });

  test('paths list with the import dialog', async ({ page }) => {
    await ensurePath(page, 'app_about', 'es', '/sobre-nosotros');

    await page.goto('/_routing/');
    await fitViewport(page);
    await page.getByTestId('routing-kit-import-open').click();
    await expect(page.getByTestId('routing-kit-import-dialog')).toBeVisible();
    await page.waitForTimeout(300); // dialog open transition
    await shoot(page, 'import-dialog');
  });

  test('redirects list with the delete confirm dialog', async ({ page }) => {
    await ensureRedirect(page, '/tratamientos/unas-encarnadas', '/about', 'Old WordPress site');
    await ensureRedirect(page, '/promo-2025', 'https://example.com/sale', 'Campaign ended');

    await page.goto('/_routing/redirects');
    await fitViewport(page);
    await page.locator('tbody tr', { hasText: '/promo-2025' }).getByTestId('redirect-delete').click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.waitForTimeout(300); // dialog open transition
    await shoot(page, 'redirects-confirm');
  });
});
