import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/products');
});

const rowOf = (page, name) => page.getByTestId('collection-row').filter({ hasText: name });
const names = (page) => page.getByTestId('collection-row').evaluateAll((rows) => rows.map((row) => row.querySelector('strong, b, [data-name]')?.textContent?.trim() ?? row.textContent.trim()));
const choose = async (page, option) => {
    await page.getByTestId('product-collection-filter').click();
    await page.getByRole('option', { name: option, exact: true }).click();
};
const references = (page) => page.getByTestId('product-row').evaluateAll((rows) => rows.map((item) => item.dataset.reference));
const orderOf = async (page) => (await (await page.request.get('/api/admin/collections')).json()).map((collection) => collection.name);

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
});

test('moving a collection up keeps the order after a reload', async ({ page }) => {
    expect(await orderOf(page)).toEqual(['Forest critters', 'Autumn market']);
    await page.getByTestId('collections-open').click();
    await Promise.all([page.waitForResponse((response) => response.url().endsWith('/api/admin/collections/order') && response.ok()), page.getByTestId('collection-move-up').nth(1).click()]);
    try {
        expect(await orderOf(page)).toEqual(['Autumn market', 'Forest critters']);
        await page.reload();
        await page.getByTestId('collections-open').click();
        await expect(page.getByTestId('collection-row').first()).toContainText('Autumn market');
    } finally {
        await page.goto('/admin/products');
        await page.getByTestId('collections-open').click();
        await Promise.all([page.waitForResponse((response) => response.url().endsWith('/api/admin/collections/order') && response.ok()), page.getByTestId('collection-move-up').nth(1).click()]);
        expect(await orderOf(page)).toEqual(['Forest critters', 'Autumn market']);
    }
});

test('the product list filters by collection and shows it in the meta line', async ({ page }) => {
    await choose(page, 'Forest critters');
    expect(await references(page)).toEqual(expect.arrayContaining(['PRT-001', 'STK-001', 'CRD-001']));
    expect(await references(page)).not.toContain('PRT-002');
    await expect(page.getByTestId('product-row').first().locator('.products-page__meta')).toContainText('Forest critters');

    await choose(page, 'No collection');
    const without = await references(page);
    expect(without).toContain('PRT-002');
    expect(without).not.toContain('PRT-001');
    expect(without).not.toContain('CRD-002');

    await choose(page, 'All collections');
    expect(await references(page)).toEqual(expect.arrayContaining(['PRT-001', 'PRT-002']));
});

test('the product editor shows its collection', async ({ page }) => {
    await page.locator('[data-test="product-row"][data-reference="PRT-001"]').getByTestId('product-edit').click();
    await expect(page.getByTestId('product-collection')).toContainText('Forest critters');
    await page.goto('/admin/products');
    await page.locator('[data-test="product-row"][data-reference="PRT-002"]').getByTestId('product-edit').click();
    await expect(page.getByTestId('product-collection')).toContainText('None');
});

test('the public preview shows nothing of the collections yet', async ({ page }) => {
    for (const path of ['/beta/en/', '/beta/fr/', '/beta/en/shop']) {
        const html = await (await page.request.get(path)).text();
        expect(html).not.toContain('Forest critters');
        expect(html).not.toContain('Bestioles de la forêt');
        expect(html).not.toContain('Autumn market');
    }
});
