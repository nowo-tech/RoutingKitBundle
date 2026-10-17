import { expect, type Page } from '@playwright/test';

/** Creates a path row through the panel form (route + locale + path) unless the list already shows it. */
export async function ensurePath(page: Page, route: string, locale: string, path: string): Promise<void> {
  await page.goto('/_routing/');
  if (await page.locator('tbody tr', { hasText: path }).filter({ hasText: route }).count()) {
    return;
  }
  await page.getByRole('link', { name: 'New path' }).click();
  await expect(page.locator('[data-nowo-ui-page="form"]')).toBeVisible();
  await page.locator('select[name="route_name"]').selectOption(route);
  await page.locator('select[name="locale"]').selectOption(locale);
  await page.locator('input[name="path"]').fill(path);
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
  await expect(page.locator('tbody tr', { hasText: path }).first()).toBeVisible();
}

/** Creates an operator redirect unless one already exists for the source path. */
export async function ensureRedirect(page: Page, source: string, target: string, note = ''): Promise<void> {
  await page.goto('/_routing/redirects');
  if (await page.locator('tbody tr', { hasText: source }).count()) {
    return;
  }
  await page.getByTestId('redirect-new').click();
  await page.locator('input[name="url_redirect[source_path]"]').fill(source);
  await page.locator('input[name="url_redirect[target_url]"]').fill(target);
  await page.locator('input[name="url_redirect[note]"]').fill(note);
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByTestId('redirect-status')).toContainText('Redirect created.');
}
