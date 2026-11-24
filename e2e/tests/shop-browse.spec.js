import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const cards = (page) => page.getByTestId('product-card');

test.beforeEach(async ({ page }) => {
    await signIn(page);
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

test('at 1440px a category page has 5 grid tracks of square cards, prices aligned, and the same tiles with or without JavaScript', async ({ browser, page }) => {
    const tileLayout = [];
    for (const javaScriptEnabled of [true, false]) {
        const context = await browser.newContext({ javaScriptEnabled, viewport: { width: 1440, height: 900 }, storageState: await page.context().storageState() });
        const visitor = await context.newPage();
        await visitor.goto('/beta/en/category/stickers');
        const grid = visitor.locator('[data-products]').first();
        const tracks = await grid.evaluate((list) => getComputedStyle(list).gridTemplateColumns.split(' ').length);
        expect(tracks).toBe(5);
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
