import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const cards = (page) => page.getByTestId('product-card');
const titles = (page) => page.locator('[data-test="product-card"] [data-card-title]');

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('a category page shows its products in admin order under its title, without the retired one', async ({ page }) => {
    await page.goto('/beta/en/category/stickers');
    await expect(page.getByRole('heading', { level: 1, name: 'Stickers and tape' })).toBeVisible();
    await expect(titles(page)).toHaveText(['Meadow sticker sheet', 'Leaf sticker pack', 'Washi tape set']);
    await page.goto('/beta/en/category/cards');
    expect((await titles(page).allTextContents()).join()).not.toContain('Wall calendar');
});

test('choosing a category in the Shop panel shows only its products and marks the entry and the category current', async ({ page }) => {
    await page.goto('/beta/en/');
    const shop = page.locator('#shop header.site-header [data-shop-menu]');
    const panel = page.locator('#shop header.site-header [data-categories]');
    await shop.click();
    await panel.getByRole('link', { name: 'Prints' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/prints$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Prints' })).toBeVisible();
    await expect(titles(page)).toHaveText(['Moss print', 'Fern print']);
    await expect(shop).toHaveAttribute('aria-current', 'true');
    await shop.click();
    await expect(panel.getByRole('link', { name: 'Prints' })).toHaveAttribute('aria-current', 'page');
    await expect(panel.getByRole('link', { name: 'Stickers and tape' })).not.toHaveAttribute('aria-current');
});

test('the shop entrance lands on the first category', async ({ page }) => {
    await page.goto('/beta/en/shop');
    await expect(page).toHaveURL(/\/beta\/en\/category\/prints$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Prints' })).toBeVisible();
});

test('the product breadcrumb leads back to its category', async ({ page }) => {
    await page.goto('/beta/en/shop/moss-print');
    await page.locator('#shop [data-breadcrumb]').getByRole('link', { name: 'Prints' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/prints$/);
    await expect(titles(page)).toHaveText(['Moss print', 'Fern print']);
});

test('an unknown category answers the shop 404', async ({ page }) => {
    await page.goto('/beta/en/category/nothing-here');
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
});

test('the language switch keeps the category', async ({ page }) => {
    await page.goto('/beta/fr/category/prints');
    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'English' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/prints$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Prints' })).toBeVisible();
});

test('a product opens from its card and choosing a variant changes the stock and the quantity bounds', async ({ page }) => {
    await page.goto('/beta/en/category/stickers');
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
    await page.goto('/beta/en/category/stickers');
    await expect(cards(page).filter({ hasText: 'Meadow sticker sheet' }).locator('[data-label]')).toHaveText('Sold out');
});

test('a hidden product is flagged in its category and on its page', async ({ page }) => {
    const products = await (await page.request.get('/api/admin/products')).json();
    const dew = products.find((product) => product.reference === 'CRD-002');
    if (dew.visible) {
        await page.request.delete(`/api/admin/products/${dew.id}/visibility`);
    }
    await page.goto('/beta/en/category/cards');
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

const boxes = async (locator) => Promise.all((await locator.all()).map((item) => item.boundingBox()));
const firstRow = (all) => all.filter((box) => Math.abs(box.y - all[0].y) < 1);

for (const { width, height, columns } of [{ width: 1440, height: 900, columns: 5 }, { width: 800, height: 900, columns: 3 }, { width: 390, height: 844, columns: 2 }]) {
    test(`at ${width}px a category page has ${columns} grid tracks of square cards, prices aligned, and the same tiles with or without JavaScript`, async ({ browser, page }) => {
        const tileLayout = [];
        for (const javaScriptEnabled of [true, false]) {
            const context = await browser.newContext({ javaScriptEnabled, viewport: { width, height }, storageState: await page.context().storageState() });
            const visitor = await context.newPage();
            await visitor.goto('/beta/en/category/stickers');
            const grid = visitor.locator('[data-products]').first();
            const tracks = await grid.evaluate((list) => getComputedStyle(list).gridTemplateColumns.split(' ').length);
            expect(tracks).toBe(columns);
            const cards = await boxes(grid.locator(':scope > li'));
            expect(cards.length).toBeGreaterThan(1);
            for (const box of await boxes(grid.locator(':scope > li > a > :first-child'))) expect(Math.abs(box.width - box.height)).toBeLessThan(1);
            const prices = firstRow(await boxes(grid.locator('[data-card-price]')));
            for (const box of prices) expect(Math.abs(box.y - prices[0].y)).toBeLessThan(1);
            await visitor.goto('/beta/en/');
            const tiles = visitor.locator('[data-tiles] > li');
            const photos = await boxes(tiles.locator(':scope > a > :first-child'));
            const labels = await boxes(tiles.locator(':scope > a > :last-child'));
            const items = await boxes(tiles);
            for (const box of photos) expect(Math.abs(box.width - box.height)).toBeLessThan(1);
            tileLayout.push(photos.map((photo, index) => [Math.round(labels[index].y - photo.y - photo.height), Math.round(items[index].height)]));
            await context.close();
        }
        expect(tileLayout[1]).toEqual(tileLayout[0]);
    });
}
