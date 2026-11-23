import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const header = (page) => page.locator('#shop header.site-header');
const button = (page) => header(page).locator('[data-shop-menu]');
const collections = (page) => header(page).locator('[data-collections]');
const chips = (page) => page.locator('#shop nav[data-filters]');
const chip = (page, name) => chips(page).locator('a[data-filter]').filter({ hasText: name });
const cards = (page) => page.getByTestId('product-card');

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the Shop panel lists the collections under the categories and a click opens the collection', async ({ page }) => {
    await page.goto('/beta/en/');
    await button(page).click();
    await expect(collections(page).getByRole('link')).toHaveText(['Forest critters', 'Autumn market Hidden']);
    await expect(header(page).getByRole('heading', { name: 'Collections' })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(collections(page)).toBeHidden();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
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
    await expect(header(page).locator('[data-categories]').getByRole('link', { name: 'Prints' })).not.toHaveAttribute('aria-current');
});

test('the phone menu lists the collections too', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/en/');
    await page.locator('#shop .site-header__menu').click();
    await expect(collections(page).getByRole('link', { name: 'Forest critters' })).toBeVisible();
    await collections(page).getByRole('link', { name: 'Forest critters' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/collection\/forest-critters$/);
    await expect(cards(page)).toHaveCount(3);
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

test('on a collection page the chips filter by category', async ({ page }) => {
    await page.goto('/beta/en/collection/forest-critters');
    await expect(chips(page)).toHaveAccessibleName('Filter by category');
    await expect(chips(page).locator('a[data-filter]')).toHaveText(['All', 'Prints', 'Stickers and tape', 'Paper goods']);
    await page.evaluate(() => {
        window.__kept = true;
    });
    await chip(page, 'Prints').click();
    await expect(page).toHaveURL(/\/beta\/en\/collection\/forest-critters\?category=prints$/);
    await expect(cards(page)).toHaveCount(1);
    await expect(cards(page).first()).toContainText('Moss print');
    await expect(chip(page, 'Prints')).toHaveAttribute('aria-current', 'true');
    await chip(page, 'All').click();
    await expect(cards(page)).toHaveCount(3);
    expect(await page.evaluate(() => window.__kept)).toBe(true);
});

test('an unknown filter answers the shop 404', async ({ page }) => {
    const response = await page.goto('/beta/en/category/cards?collection=nope');
    expect(response.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
    expect((await page.goto('/beta/en/collection/forest-critters?category=cards-nope')).status()).toBe(404);
    expect((await page.goto('/beta/en/collection/nope')).status()).toBe(404);
});

test('the language switch keeps the filter', async ({ page }) => {
    await page.goto('/beta/en/category/cards?collection=forest-critters');
    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'Français' }).click();
    await expect(page).toHaveURL(/\/beta\/fr\/category\/cards\?collection=forest-critters$/);
    await expect(chips(page)).toHaveAccessibleName('Filtrer par collection');
    await expect(chip(page, 'Bestioles de la forêt')).toHaveAttribute('aria-current', 'true');

    await page.goto('/beta/fr/collection/forest-critters?category=prints');
    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'English' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/collection\/forest-critters\?category=prints$/);
    await expect(chip(page, 'Prints')).toHaveAttribute('aria-current', 'true');
});

test('the product page names its collection and the link leads to it', async ({ page }) => {
    await page.goto('/beta/en/shop/moss-print');
    const line = page.locator('[data-product-collection]');
    await expect(line).toHaveText('Collection: Forest critters');
    expect(await line.evaluate((element) => element.textContent.replace(/\s+/g, ' ').trim())).toBe('Collection: Forest critters');
    await line.getByRole('link', { name: 'Forest critters' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/collection\/forest-critters$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Forest critters' })).toBeVisible();

    await page.goto('/beta/en/shop/fern-print');
    await expect(page.locator('[data-product-collection]')).toHaveCount(0);
});

test('the preview marks a collection that is not shown', async ({ page }) => {
    await page.goto('/beta/en/collection/autumn-market');
    await expect(page.getByRole('heading', { level: 1, name: 'Autumn market' })).toBeVisible();
    await expect(page.locator('[data-hidden] [data-label]')).toHaveText('Hidden');
    await expect(cards(page)).toHaveCount(1);
    await expect(chips(page)).toHaveCount(0);
    await page.goto('/beta/en/shop/dew-greeting-card');
    await expect(page.locator('[data-product-collection]')).toHaveText('Collection: Autumn market Hidden');
    await expect(page.locator('[data-product-collection] [data-label]')).toHaveText('Hidden');
});

test('showing a collection in the admin removes its Hidden mark from the preview menu', async ({ page }) => {
    const toggle = async () => {
        await page.goto('/admin/products');
        await page.getByTestId('collections-open').click();
        const switchButton = page.getByTestId('collection-row').filter({ hasText: 'Autumn market' }).getByTestId('collection-shown');
        const shown = (await switchButton.getAttribute('aria-checked')) === 'true';
        await Promise.all([page.waitForResponse((response) => response.url().endsWith('/visibility') && response.ok()), switchButton.click()]);
        return !shown;
    };
    const marks = async () => {
        await page.goto('/beta/en/');
        await button(page).click();
        return collections(page).locator('[data-label]').count();
    };
    expect(await marks()).toBe(1);
    await toggle();
    try {
        expect(await marks()).toBe(0);
        await expect(collections(page).getByRole('link')).toHaveText(['Forest critters', 'Autumn market']);
    } finally {
        await toggle();
    }
    expect(await marks()).toBe(1);
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
