import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const cards = (page) => page.getByTestId('product-card');
const titles = (page) => page.locator('[data-test="product-card"] [data-card-title]');

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the listing shows the demo products in admin order, without the retired one', async ({ page }) => {
    await page.goto('/beta/en/shop');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const all = await titles(page).allTextContents();
    expect(all.slice(0, 5)).toEqual(['Moss print', 'Fern print', 'Meadow sticker sheet', 'Leaf sticker pack', 'Washi tape set']);
    expect(all.join()).not.toContain('Wall calendar');
});

test('a category chip filters the listing and marks itself current', async ({ page }) => {
    await page.goto('/beta/en/shop');
    const chips = page.getByTestId('category-filter');
    await expect(chips.getByRole('link', { name: 'All products' })).toHaveAttribute('aria-current', 'page');
    await chips.getByRole('link', { name: 'Prints' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/shop\?category=prints$/);
    await expect(chips.getByRole('link', { name: 'Prints' })).toHaveAttribute('aria-current', 'page');
    await expect(chips.getByRole('link', { name: 'All products' })).not.toHaveAttribute('aria-current');
    await expect(titles(page)).toHaveText(['Moss print', 'Fern print']);
});

test('an unknown category answers the shop 404', async ({ page }) => {
    await page.goto('/beta/en/shop?category=nothing-here');
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
});

test('a product opens from its card and choosing a variant changes the stock and the quantity bounds', async ({ page }) => {
    await page.goto('/beta/en/shop');
    await cards(page).filter({ hasText: 'Washi tape set' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/shop\/washi-tape-set$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Washi tape set' })).toBeVisible();

    const stock = page.getByTestId('stock-state');
    const quantity = page.getByRole('spinbutton', { name: 'Quantity' });
    await expect(stock).toHaveText('In stock');
    await expect(quantity).toHaveAttribute('max', '10');

    await page.getByLabel('Blue').check({ force: true });
    await expect(stock).toHaveText('Last pieces');
    await expect(quantity).toHaveAttribute('max', '2');
    await page.getByRole('button', { name: 'One more' }).click();
    await expect(quantity).toHaveValue('2');
    await expect(page.getByRole('button', { name: 'One more' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'One more' })).toBeFocused();
    await expect(page.getByRole('button', { name: 'Add to cart' })).toBeVisible();
});

test('a sold-out product says so and offers no quantity', async ({ page }) => {
    await page.goto('/beta/en/shop/meadow-sticker-sheet');
    await expect(page.getByTestId('stock-state')).toHaveText('Sold out');
    await expect(page.getByTestId('quantity-stepper')).toHaveCount(0);
    await page.goto('/beta/en/shop');
    await expect(cards(page).filter({ hasText: 'Meadow sticker sheet' }).locator('[data-label]')).toHaveText('Sold out');
});

test('a hidden product is flagged in the listing and on its page', async ({ page }) => {
    const products = await (await page.request.get('/api/admin/products')).json();
    const dew = products.find((product) => product.reference === 'CRD-002');
    if (dew.visible) {
        await page.request.delete(`/api/admin/products/${dew.id}/visibility`);
    }
    await page.goto('/beta/en/shop');
    await expect(cards(page).filter({ hasText: dew.title || dew.name }).locator('[data-label]')).toHaveText('Hidden');
    await page.goto(`/beta/en/shop/${dew.slug}`);
    await expect(page.locator('[data-hidden]').first()).toBeVisible();
});

test('the retired product answers the shop 404', async ({ page }) => {
    await page.goto('/beta/en/shop/wall-calendar-2026');
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
});

test('the language switch keeps the product page', async ({ page }) => {
    await page.goto('/beta/fr/shop/moss-print');
    await expect(page.getByRole('heading', { level: 1, name: 'Tirage Mousse' })).toBeVisible();
    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'English' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/shop\/moss-print$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Moss print' })).toBeVisible();
});

test('showing another photo keeps the gallery the same size', async ({ page }) => {
    await page.goto('/beta/en/shop/moss-print');
    const thumbs = page.getByTestId('gallery-thumb');
    const cover = page.getByTestId('product-gallery').locator('img').first();
    const thumbsTop = () => thumbs.first().evaluate((thumb) => thumb.getBoundingClientRect().top + window.scrollY);
    await expect(thumbs).toHaveCount(2);
    await expect(cover).toHaveJSProperty('complete', true);
    const before = await thumbsTop();
    await thumbs.nth(1).click();
    await expect(thumbs.nth(1)).toHaveAttribute('aria-pressed', 'true');
    await expect(cover).toHaveJSProperty('complete', true);
    expect(await thumbsTop()).toBe(before);
    await thumbs.first().click();
    await expect(cover).toHaveJSProperty('complete', true);
    expect(await thumbsTop()).toBe(before);
});
