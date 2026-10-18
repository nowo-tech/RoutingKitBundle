import { test, expect } from '@playwright/test';
import { ensurePath, ensureRedirect } from './helpers';

/**
 * REQ-DEMO-013 / REQ-UI-003 — the Routing Kit admin panel on the UiKit page contract: list, form, confirm dialog and
 * the no-JS confirm page, rendered inside the demo host layout (nowo_ui_kit.layouts.admin).
 */
test.describe('routing kit panel', () => {
  test('home links the panel', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByRole('heading', { level: 1, name: 'Routing Kit Bundle' })).toBeVisible();
  });

  test('paths list and form use the UiKit scaffolds', async ({ page }) => {
    await ensurePath(page, 'app_about', 'es', '/sobre-nosotros');

    await page.goto('/_routing/');
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: 'Routing Kit' })).toBeVisible();
    await expect(page.locator('[data-nowo-ui-region="area-nav"]')).toBeVisible();
    await expect(page.getByTestId('routing-kit-nav-routes')).toHaveAttribute('aria-current', 'page');
    await expect(page.getByTestId('routing-kit-role-gate-warning')).toBeVisible();
    // the signed JSON import opens in the UiKit dialog (web_ui.import_display: modal, default)
    await expect(page.getByTestId('routing-kit-import-form')).toBeHidden();
    await page.getByTestId('routing-kit-import-open').click();
    const importDialog = page.getByTestId('routing-kit-import-dialog');
    await expect(importDialog).toBeVisible();
    await expect(importDialog.getByLabel('Signed JSON payload')).toBeVisible();
    await expect(importDialog.getByLabel('Replace all existing rows')).not.toBeChecked();
    await importDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(importDialog).toBeHidden();

    // consistent cells: UiKit code slot for route / path, translated enabled badge, no duplicated row-count caption
    await expect(page.locator('tbody code.nowo-ui-code', { hasText: '/sobre-nosotros' })).toBeVisible();
    await expect(page.locator('tbody tr', { hasText: '/sobre-nosotros' }).getByText('Yes', { exact: true })).toBeVisible();
    await expect(page.locator('table caption')).toHaveCount(0);

    // icon row actions (nowo_ui_kit.row_actions_display: icon) keep an accessible name
    const row = page.locator('tbody tr', { hasText: '/sobre-nosotros' });
    await expect(row.locator('.nowo-ui-row-actions--icon svg').first()).toBeVisible();
    await row.getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Edit path' })).toBeVisible();
    await expect(page.locator('[data-nowo-ui-region="danger-zone"]')).toBeVisible();
    await page.getByRole('link', { name: 'Cancel' }).click();
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
  });

  test('redirect delete goes through the UiKit confirm dialog', async ({ page }) => {
    const source = `/e2e-${Date.now()}`;
    await ensureRedirect(page, source, '/about', 'e2e');

    const row = page.locator('tbody tr', { hasText: source });
    await row.getByTestId('redirect-delete').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('heading', { name: `Delete redirect ${source}?` })).toBeVisible();
    await expect(dialog).toContainText(`Visitors to ${source} are no longer sent to /about`);
    await dialog.getByRole('button', { name: 'Delete' }).click();

    await expect(page.getByTestId('redirect-status')).toContainText('Redirect deleted.');
    await expect(page.locator('tbody tr', { hasText: source })).toHaveCount(0);
  });

  test('redirect delete has a no-JS confirm page', async ({ page }) => {
    const source = `/e2e-page-${Date.now()}`;
    await ensureRedirect(page, source, '/about');
    const href = await page.locator('tbody tr', { hasText: source }).getByRole('link', { name: 'Edit' }).getAttribute('href');

    await page.goto(String(href).replace(/\/edit$/, '/delete'));
    await expect(page.locator('[data-nowo-ui-page="confirm"]')).toBeVisible();
    await page.getByTestId('redirect-delete-form').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByTestId('redirect-status')).toContainText('Redirect deleted.');
  });

  test('path delete has a no-JS confirm page (POST with CSRF)', async ({ page }) => {
    const path = `/e2e-del-${Date.now()}`;
    await ensurePath(page, 'homepage', 'es', path);
    const href = await page.locator('tbody tr', { hasText: path }).getByRole('link', { name: 'Edit' }).getAttribute('href');

    await page.goto(String(href).replace('/edit/', '/delete/'));
    await expect(page.locator('[data-nowo-ui-page="confirm"]')).toBeVisible();
    await expect(page.getByTestId('routing-kit-nav-routes')).toHaveAttribute('aria-current', 'page');
    const form = page.getByTestId('routing-kit-delete-form');
    await expect(form.locator('input[type="hidden"][name="_csrf_token"]')).toHaveCount(1);
    await form.getByRole('button', { name: 'Delete' }).click();
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.locator('tbody tr', { hasText: path })).toHaveCount(0);
  });

  test('without JavaScript, delete links to the confirm page', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const source = `/e2e-nojs-${Date.now()}`;
    await ensureRedirect(page, source, '/about');

    await page.locator('tbody tr', { hasText: source }).locator('a.nowo-ui-action__fallback').click();
    await expect(page.locator('[data-nowo-ui-page="confirm"]')).toBeVisible();
    await page.getByTestId('redirect-delete-form').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByTestId('redirect-status')).toContainText('Redirect deleted.');
    await context.close();
  });
});
