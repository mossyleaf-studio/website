import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/products');
});

const rowOf = (page, name) => page.getByTestId('collection-row').filter({ hasText: name });

test('a collection gets its two labels and is shown then hidden', async ({ page }) => {
    const english = unique('Critters');
    const french = unique('Bestioles');
    await page.getByTestId('collections-open').click();
    await expect(page.getByTestId('collection-settings')).toBeVisible();
    const row = rowOf(page, 'Autumn market');
    const field = row.getByTestId('collection-label');
    await field.locator('input[name$="Fr"]').fill(french);
    await field.locator('input:not([name$="Fr"])').fill(english);
    await Promise.all([page.waitForResponse((response) => response.url().includes('/api/admin/collections/') && response.request().method() === 'PUT' && response.ok()), page.getByTestId('collections-save').click()]);

    const saved = await (await page.request.get('/api/admin/collections')).json();
    const autumn = saved.find((collection) => collection.name === 'Autumn market');
    expect(autumn).toMatchObject({ label: english, labelFr: french, shown: false });

    await page.reload();
    await page.getByTestId('collections-open').click();
    const toggle = rowOf(page, 'Autumn market').getByTestId('collection-shown');
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
    await Promise.all([page.waitForResponse((response) => response.url().endsWith('/visibility') && response.request().method() === 'PUT' && response.ok()), toggle.click()]);
    await expect(toggle).toHaveAttribute('aria-checked', 'true');
    await Promise.all([page.waitForResponse((response) => response.url().endsWith('/visibility') && response.request().method() === 'DELETE' && response.ok()), toggle.click()]);
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
    expect((await (await page.request.get('/api/admin/collections')).json()).find((collection) => collection.name === 'Autumn market').shown).toBe(false);
    expect((await page.request.put(`/api/admin/collections/${autumn.id}`, { data: { label: '', labelFr: '' } })).ok()).toBe(true);
});
