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
    await expect(page.getByTestId('routing-kit-nav-routes')).toHaveAttribute('aria-current', 'page');
    await expect(page.getByTestId('routing-kit-role-gate-warning')).toBeVisible();
    await expect(page.getByTestId('routing-kit-import-form')).toBeVisible();

    const row = page.locator('tbody tr', { hasText: '/sobre-nosotros' });
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
    await expect(dialog).toContainText(`Delete the redirect from ${source} to /about?`);
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
});
