import { expect, test } from '@playwright/test';
import { PNG, signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/products');
});

const row = (page, reference) => page.locator(`[data-test="product-row"][data-reference="${reference}"]`);
const references = (page) => page.getByTestId('product-row').evaluateAll((rows) => rows.map((item) => item.dataset.reference));

test('a hidden product gets a photo and its texts, then goes live', async ({ page }) => {
    const english = unique('Dew card');
    const french = unique('Carte rosée');
    await expect(row(page, 'CRD-002').getByTestId('product-visibility')).not.toBeChecked();
    await row(page, 'CRD-002').getByTestId('product-edit').click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await expect(page.getByTestId('product-preview')).toHaveAttribute('href', /^\/beta\/en\/shop\/[a-z0-9-]+$/);
    const preview = await page.request.get(await page.getByTestId('product-preview').getAttribute('href'));
    expect(preview.ok()).toBeTruthy();
    await expect(page.getByTestId('photo-card')).toHaveCount(0);

    await page.getByTestId('photo-upload').setInputFiles(PNG);
    await expect(page.getByTestId('photo-card')).toHaveCount(1);
    await expect(page.getByTestId('photo-cover')).toBeVisible();

    const form = page.getByTestId('product-form');
    await form.locator('input[name="title"]').fill(english);
    await form.locator('input[name="titleFr"]').fill(french);
    await form.locator('[name="description"]').fill('A greeting card with a dewdrop.');
    await form.locator('[name="descriptionFr"]').fill('Une carte de voeux avec une goutte de rosée.');
    await Promise.all([page.waitForResponse((response) => response.url().includes('/api/admin/products/') && response.request().method() === 'PUT' && response.ok()), page.getByTestId('product-save').click()]);
    await expect(page.getByTestId('product-save')).toBeDisabled();

    await page.getByTestId('product-visibility').click();
    await expect(page.getByTestId('product-visibility')).toBeChecked();

    await page.reload();
    await expect(form.locator('input[name="title"]')).toHaveValue(english);
    await expect(form.locator('input[name="titleFr"]')).toHaveValue(french);
    await expect(page.getByTestId('photo-card')).toHaveCount(1);
    await expect(page.getByTestId('product-visibility')).toBeChecked();

    await page.goto('/admin/products');
    await expect(row(page, 'CRD-002').getByTestId('product-visibility')).toBeChecked();
    await expect(row(page, 'CRD-002').getByTestId('product-chip')).toHaveCount(0);
});

test('a category gets its French name', async ({ page }) => {
    const french = unique('Papeterie');
    await page.getByTestId('category-names-open').click();
    const field = page.getByTestId('category-label').last();
    await field.locator('input[name$="Fr"]').fill(french);
    await Promise.all([page.waitForResponse((response) => response.url().includes('/api/admin/categories/') && response.ok()), page.getByTestId('category-names-save').click()]);

    const labels = await page.request.get('/api/admin/categories');
    expect((await labels.json()).map((category) => category.labelFr)).toContain(french);
});

test('moving a category in the dialog reorders the Shop menu of the preview', async ({ page }) => {
    const menu = async () => {
        await page.goto('/beta/en/');
        await page.locator('#shop header.site-header [data-shop-menu]').click();
        return page.locator('#shop header.site-header [data-categories] a').allTextContents();
    };
    const move = async (direction, index) => {
        await page.goto('/admin/products');
        await page.getByTestId('category-names-open').click();
        await Promise.all([
            page.waitForResponse((response) => response.url().endsWith('/api/admin/categories/order') && response.ok()),
            page.getByTestId(`category-move-${direction}`).nth(index).click(),
        ]);
    };
    const before = await menu();
    expect(before).toEqual(['Prints', 'Stickers and tape', 'Paper goods']);
    await move('up', 1);
    try {
        await expect(page.getByTestId('category-move-down').nth(0)).toBeFocused();
        expect(await menu()).toEqual(['Stickers and tape', 'Prints', 'Paper goods']);
        await page.goto('/beta/en/shop');
        await expect(page).toHaveURL(/\/beta\/en\/category\/stickers$/);
    } finally {
        await move('down', 0);
    }
    expect(await menu()).toEqual(before);
});

test('the products are reordered and the order survives a reload', async ({ page }) => {
    const before = await references(page);
    await row(page, before[0]).getByRole('button', { name: /^Move .+ down$/ }).click();
    const after = [before[1], before[0], ...before.slice(2)];
    await expect.poll(() => references(page)).toEqual(after);
    await page.waitForResponse((response) => response.url().endsWith('/api/admin/products/order') && response.ok());

    await page.reload();
    expect(await references(page)).toEqual(after);

    await row(page, before[0]).getByRole('button', { name: /^Move .+ up$/ }).click();
    await expect.poll(() => references(page)).toEqual(before);
});

test('a failed load says so and retries instead of claiming there is no product', async ({ page }) => {
    await page.goto('/admin/texts');
    await page.route('**/api/admin/products', (route) => route.fulfill({ status: 500, contentType: 'application/problem+json', body: '{}' }));
    await page.getByRole('link', { name: 'Products' }).click();
    await expect(page.getByTestId('products-failed')).toBeVisible();
    await expect(page.getByTestId('products-empty')).toHaveCount(0);

    await page.unroute('**/api/admin/products');
    await page.getByTestId('products-failed').getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByTestId('product-row').first()).toBeVisible();
});

test('switching Studio pick puts the product in the Studio picks of the preview home and off removes it', async ({ page }) => {
    const picks = async () => {
        await page.goto('/beta/en/');
        return page.locator('[data-picks] [data-card-title]').allTextContents();
    };
    expect(await picks()).not.toContain('Leaf sticker pack');
    await page.goto('/admin/products');
    await expect(row(page, 'STK-002').getByTestId('product-picked')).toHaveCount(0);
    await expect(row(page, 'PRT-001').getByTestId('product-picked')).toBeVisible();
    await row(page, 'STK-002').getByTestId('product-edit').click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await expect(page.getByTestId('product-pick')).not.toBeChecked();
    await expect(page.getByTestId('product-pick-state')).toHaveText('Not in the “Studio picks” row of the shop home.');
    await page.getByTestId('product-pick').click();
    await expect(page.getByTestId('product-pick')).toBeChecked();
    await expect(page.getByTestId('product-pick-state')).toHaveText('Shown in the “Studio picks” row of the shop home.');
    try {
        expect(await picks()).toContain('Leaf sticker pack');
        await page.goto('/admin/products');
        await expect(row(page, 'STK-002').getByTestId('product-picked')).toBeVisible();
    } finally {
        await page.goto('/admin/products');
        await row(page, 'STK-002').getByTestId('product-edit').click();
        if (await page.getByTestId('product-pick').isChecked()) await page.getByTestId('product-pick').click();
        await expect(page.getByTestId('product-pick')).not.toBeChecked();
    }
    expect(await picks()).not.toContain('Leaf sticker pack');
    await page.goto('/admin/products');
    await expect(row(page, 'STK-002').getByTestId('product-picked')).toHaveCount(0);
});
