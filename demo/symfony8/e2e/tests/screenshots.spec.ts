import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';
import { ensurePath, ensureRedirect } from './helpers';

/** The Symfony WebProfiler toolbar (dev) is part of the demo frame: wait until it has loaded. */
async function waitForToolbar(page: Page): Promise<void> {
  const toolbar = page.locator('.sf-toolbar .sf-toolbar-block').first();
  if (await page.locator('.sf-toolbar').count()) {
    await expect(toolbar).toBeVisible();
  }
}

/**
 * REQ-DEMO-013 — the panel in the demo frame (demo nav + UiKit page + Symfony toolbar), with realistic rows.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.use({ viewport: { width: 1280, height: 900 } });

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
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'paths-list.png'), fullPage: true });
  });

  test('redirects list with the delete confirm dialog', async ({ page }) => {
    await ensureRedirect(page, '/tratamientos/unas-encarnadas', '/about', 'Old WordPress site');
    await ensureRedirect(page, '/promo-2025', 'https://example.com/sale', 'Campaign ended');

    await page.goto('/_routing/redirects');
    await page.locator('tbody tr', { hasText: '/promo-2025' }).getByTestId('redirect-delete').click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'redirects-confirm.png'), fullPage: true });
  });
});
