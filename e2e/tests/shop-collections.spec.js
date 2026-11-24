import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const header = (page) => page.locator('#shop header.site-header');
const button = (page) => header(page).locator('[data-menu="collections"]');
const collections = (page) => header(page).locator('[data-collections]');
const chips = (page) => page.locator('#shop nav[data-filters]');
const chip = (page, name) => chips(page).locator('a[data-filter]').filter({ hasText: name });
const cards = (page) => page.getByTestId('product-card');

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the Collections panel lists the collections and a click opens the collection', async ({ page }) => {
    await page.goto('/beta/en/');
    await button(page).click();
    await expect(collections(page).getByRole('link')).toHaveText(['Forest critters', 'Autumn market Hidden']);
    await expect(header(page).locator('#menu-collections').getByRole('heading')).toBeHidden();

    await page.keyboard.press('Escape');
    await expect(collections(page)).toBeHidden();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await expect(collections(page).getByRole('link').first()).toBeFocused();
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/\/beta\/en\/collection\/forest-critters$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Forest critters' })).toBeVisible();
    await expect(cards(page)).toHaveCount(3);
    await expect(button(page)).toHaveAttribute('aria-current', 'true');
    await button(page).click();
    await expect(collections(page).getByRole('link', { name: 'Forest critters' })).toHaveAttribute('aria-current', 'page');
    await expect(collections(page).getByRole('link', { name: 'Autumn market' })).not.toHaveAttribute('aria-current');
    await expect(header(page).locator('[data-menu="categories"]')).not.toHaveAttribute('aria-current');
});

test('on a category page the chips filter by collection and All comes back', async ({ page }) => {
    await page.goto('/beta/en/category/cards');
    await expect(chips(page)).toHaveAccessibleName('Filter by collection');
    await expect(chips(page).locator('a[data-filter]')).toHaveText(['All', 'Forest critters', 'Autumn market Hidden']);
    await expect(chip(page, 'All')).toHaveAttribute('aria-current', 'true');
    await expect(cards(page)).toHaveCount(2);

    await page.evaluate(() => {
        window.__kept = true;
    });
    await chip(page, 'Forest critters').click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/cards\?collection=forest-critters$/);
    await expect(cards(page)).toHaveCount(1);
    await expect(cards(page).first()).toContainText('Postcard set');
    await expect(chip(page, 'Forest critters')).toHaveAttribute('aria-current', 'true');
    await expect(chip(page, 'All')).not.toHaveAttribute('aria-current');
    await expect(page.getByRole('heading', { level: 1, name: 'Paper goods' })).toBeVisible();

    await chip(page, 'Autumn market').click();
    await expect(page).toHaveURL(/collection=autumn-market$/);
    await expect(cards(page)).toHaveCount(1);
    await expect(cards(page).first()).toHaveAttribute('href', '/beta/en/shop/dew-greeting-card');

    await chip(page, 'All').click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/cards$/);
    await expect(cards(page)).toHaveCount(2);
    expect(await page.evaluate(() => window.__kept)).toBe(true);
});

test('without JavaScript the collections of the header and the chips are links', async ({ page, browser }) => {
    const crawler = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const view = await crawler.newPage();
    await view.goto('/beta/en/category/cards');
    await expect(view.locator('[data-collections] a[href="/beta/en/collection/forest-critters"]')).toHaveCount(1);
    await expect(chips(view).locator('a[data-filter]')).toHaveCount(3);
    await expect(chip(view, 'All')).toHaveAttribute('aria-current', 'true');
    await chip(view, 'Forest critters').click();
    await expect(view).toHaveURL(/category\/cards\?collection=forest-critters$/);
    await expect(chip(view, 'Forest critters')).toHaveAttribute('aria-current', 'true');
    await expect(view.locator('.shop-prerender__catalogue')).toContainText('Postcard set');

    await view.locator('[data-collections] a[href="/beta/en/collection/forest-critters"]').evaluate((link) => link.click());
    await expect(view).toHaveURL(/\/beta\/en\/collection\/forest-critters$/);
    await expect(chips(view).locator('a[data-filter]')).toHaveCount(4);
    await crawler.close();
});
