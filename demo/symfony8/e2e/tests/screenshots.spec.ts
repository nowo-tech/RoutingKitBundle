import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';
import { ensurePath, ensureRedirect } from './helpers';

/**
 * REQ-DEMO-013 — the panel in the demo platform shell (no Symfony toolbar), with realistic rows.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1280;

/**
 * REQ-DEMO-013 framing: hide the Symfony toolbar (it is not part of the demo platform), then grow the viewport so the
 * whole page fits and capture it.
 */
async function fitViewport(page: Page): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
  const bottom = await page.evaluate(() => Math.max(...[...document.querySelectorAll('.nowo-ui-main, .nowo-ui-aside, main')]
    .map((el) => el.getBoundingClientRect().bottom + window.scrollY)));
  await page.setViewportSize({ width: WIDTH, height: Math.max(720, Math.ceil(bottom) + 24) });
  await page.evaluate(() => window.scrollTo(0, 0));
}

async function shoot(page: Page, file: string): Promise<void> {
  await page.screenshot({ path: resolve(outDir, `${file}.png`) });
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
