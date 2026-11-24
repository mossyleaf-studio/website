import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const header = (page) => page.locator('#shop header.site-header');
const button = (page, key = 'categories') => header(page).locator(`[data-menu="${key}"]`);
const panel = (page) => header(page).locator('[data-categories]');
const collections = (page) => header(page).locator('[data-collections]');
const height = async (locator) => (await locator.boundingBox())?.height ?? 0;

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the categories entry opens its list and closes it again', async ({ page }) => {
    await page.goto('/beta/en/');
    await expect(button(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(button(page)).not.toHaveAttribute('aria-current');
    await expect(panel(page)).toBeHidden();

    await button(page).click();
    await expect(button(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(button(page)).toBeFocused();
    await expect(panel(page).getByRole('link')).toHaveText(['Prints', 'Stickers and tape', 'Paper goods']);
    await expect(header(page).locator('#menu-categories').getByRole('heading')).toBeHidden();
    await expect(header(page).getByRole('menu')).toHaveCount(0);

    await button(page).click();
    await expect(panel(page)).toBeHidden();

    await button(page).focus();
    await page.keyboard.press('Enter');
    await expect(panel(page)).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(panel(page).getByRole('link', { name: 'Prints' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(button(page)).toBeFocused();

    await page.keyboard.press('Space');
    await expect(panel(page)).toBeVisible();
    await page.mouse.click(10, 800);
    await expect(panel(page)).toBeHidden();

    await button(page).click();
    await panel(page).getByRole('link').last().focus();
    await page.keyboard.press('Tab');
    await expect(panel(page)).toBeHidden();
});

test('opening one entry closes the other and each lists its own links', async ({ page }) => {
    await page.goto('/beta/en/');
    await button(page).click();
    await expect(panel(page)).toBeVisible();
    await button(page, 'collections').hover();
    await expect(collections(page)).toBeHidden();
    await button(page, 'collections').click();
    await expect(collections(page)).toBeVisible();
    await expect(panel(page)).toBeHidden();
    await expect(button(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(button(page, 'collections')).toHaveAttribute('aria-expanded', 'true');
    await expect(collections(page).getByRole('link')).toHaveText(['Forest critters', 'Autumn market Hidden']);
    await page.keyboard.press('Escape');
    await expect(collections(page)).toBeHidden();
    await expect(button(page, 'collections')).toBeFocused();
});

test('the panel lies above the page and a category opens its page with the entry and the category current', async ({ page }) => {
    await page.goto('/beta/en/');
    await button(page).hover();
    await expect(panel(page)).toBeHidden();
    await button(page).click();
    const link = panel(page).getByRole('link', { name: 'Stickers and tape' });
    for (const item of await panel(page).getByRole('link').all()) {
        const box = await item.boundingBox();
        expect(await page.evaluate(([x, y]) => document.elementFromPoint(x, y)?.closest('[data-categories]') !== null, [box.x + box.width / 2, box.y + box.height / 2])).toBe(true);
    }
    await link.click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/stickers$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Stickers and tape' })).toBeVisible();
    await expect(page.getByTestId('product-card').first()).toBeVisible();
    await expect(panel(page)).toBeHidden();
    await expect(button(page)).toHaveAttribute('aria-current', 'true');
    await expect(button(page, 'collections')).not.toHaveAttribute('aria-current');
    await button(page).click();
    await expect(panel(page).getByRole('link', { name: 'Stickers and tape' })).toHaveAttribute('aria-current', 'page');
    await expect(panel(page).getByRole('link', { name: 'Prints' })).not.toHaveAttribute('aria-current');

    await panel(page).getByRole('link', { name: 'Prints' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/category\/prints$/);
    await page.goBack();
    await expect(page).toHaveURL(/\/beta\/en\/category\/stickers$/);
    await expect(panel(page)).toBeHidden();
    await expect(button(page)).toHaveAttribute('aria-expanded', 'false');
});

test('under 56rem the menu holds both groups under their headings in one sheet', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/fr/category/prints');
    await expect(button(page)).toBeHidden();
    await page.locator('#shop .site-header__menu').click();
    await expect(button(page)).toBeHidden();
    await expect(button(page, 'collections')).toBeHidden();
    const links = panel(page).getByRole('link');
    await expect(links).toHaveText(['Tirages', 'Stickers et masking tape', /^Papeterie/]);
    await expect(panel(page)).toHaveAccessibleName('Catégories');
    await expect(collections(page)).toHaveAccessibleName('Collections');
    const headings = header(page).getByRole('heading');
    await expect(headings).toHaveText(['Catégories', 'Collections']);
    await expect(header(page).locator('#site-navigation').getByRole('link', { name: 'Catégories', exact: true })).toHaveCount(0);
    await expect(panel(page).getByRole('link', { name: 'Tirages' })).toHaveAttribute('aria-current', 'page');
});

test('without JavaScript each entry shows its panel on hover and on focus', async ({ browser, page }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const crawler = await context.newPage();
    await crawler.goto('/beta/en/');
    const nav = crawler.locator('.shop-prerender__navigation');
    for (const [key, href, first] of [['categories', '/beta/en/shop', 'Prints'], ['collections', '/beta/en/collection/forest-critters', 'Forest critters']]) {
        const entry = nav.locator(`[data-menu="${key}"]`);
        const sheet = nav.locator(`#menu-${key}`);
        await expect(sheet.locator(`[data-${key}]`)).toHaveCount(1);
        await expect(entry).toHaveAttribute('href', href);
        expect(await height(sheet)).toBeLessThanOrEqual(1);
        await entry.hover();
        await expect.poll(() => height(sheet)).toBeGreaterThan(80);
        await crawler.mouse.move(0, 0);
        await expect.poll(() => height(sheet)).toBeLessThanOrEqual(1);
        await entry.focus();
        await crawler.keyboard.press('Tab');
        await expect(sheet.getByRole('link', { name: first })).toBeFocused();
        await expect.poll(() => height(sheet)).toBeGreaterThan(80);
    }
    await context.close();
});
