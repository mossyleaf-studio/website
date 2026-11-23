import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const header = (page) => page.locator('#shop header.site-header');
const button = (page) => header(page).locator('[data-shop-menu]');
const panel = (page) => header(page).locator('[data-categories]');
const height = async (locator) => (await locator.boundingBox())?.height ?? 0;

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the shop entry opens the list of categories and closes it again', async ({ page }) => {
    await page.goto('/beta/en/');
    await expect(button(page)).toHaveAttribute('aria-expanded', 'false');
    const controlled = header(page).locator(`#${await button(page).getAttribute('aria-controls')}`);
    await expect(controlled.locator('[data-categories]')).toHaveCount(1);
    await expect(controlled.locator('[data-collections]')).toHaveCount(1);
    await expect(button(page)).not.toHaveAttribute('aria-current');
    await expect(panel(page)).toBeHidden();

    await button(page).click();
    await expect(button(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(button(page)).toBeFocused();
    await expect(panel(page).getByRole('link')).toHaveText(['Prints', 'Stickers and tape', 'Paper goods']);
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
    await header(page).locator('[data-categories] a, [data-collections] a').last().focus();
    await page.keyboard.press('Tab');
    await expect(panel(page)).toBeHidden();
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

test('the product breadcrumb goes through home and the category', async ({ page }) => {
    await page.goto('/beta/en/shop/moss-print');
    const trail = page.locator('#shop [data-breadcrumb]');
    await expect(trail.getByRole('listitem')).toHaveText(['Home', 'Prints', 'Moss print']);
    await expect(trail.getByRole('link', { name: 'Prints' })).toHaveAttribute('href', '/beta/en/category/prints');
    await expect(button(page)).toHaveAttribute('aria-current', 'true');
});

test('under 56rem the menu lists the categories under the shop label', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/fr/category/prints');
    await expect(button(page)).toBeHidden();
    await page.locator('#shop .site-header__menu').click();
    const links = panel(page).getByRole('link');
    await expect(links).toHaveText(['Tirages', 'Stickers et masking tape', /^Papeterie/]);
    await expect(panel(page)).toHaveAccessibleName('Boutique');
    const label = page.locator('#shop-categories-label');
    await expect(label).toHaveAttribute('href', '/beta/fr/shop');
    await expect(label).toHaveCSS('text-transform', 'none');
    await expect(panel(page).getByRole('link', { name: 'Tirages' })).toHaveAttribute('aria-current', 'page');
    for (const box of await Promise.all((await links.all()).map((link) => link.boundingBox()))) expect(box.height).toBeGreaterThanOrEqual(44);
});

test('without JavaScript the shop entry shows its categories on hover and on focus', async ({ browser, page }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const crawler = await context.newPage();
    await crawler.goto('/beta/en/');
    const nav = crawler.locator('.shop-prerender__navigation');
    const entry = nav.locator('[data-shop-menu]');
    const categories = nav.locator('.shop-prerender__panel');
    await expect(categories.locator('[data-categories]')).toHaveCount(1);
    await expect(categories.locator('[data-collections]')).toHaveCount(1);
    await expect(entry).toHaveAttribute('href', '/beta/en/shop');
    expect(await height(categories)).toBeLessThanOrEqual(1);
    await entry.hover();
    await expect.poll(() => height(categories)).toBeGreaterThan(100);
    await crawler.mouse.move(0, 0);
    await expect.poll(() => height(categories)).toBeLessThanOrEqual(1);
    await entry.focus();
    await crawler.keyboard.press('Tab');
    await expect(categories.getByRole('link', { name: 'Prints' })).toBeFocused();
    await expect.poll(() => height(categories)).toBeGreaterThan(100);
    await context.close();
});

test('without JavaScript the phone header keeps the menu in wrapped rows and its categories folded', async ({ browser, page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/en/category/prints');
    const scripted = await height(header(page));
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    const crawler = await context.newPage();
    await crawler.goto('/beta/en/category/prints');
    const categories = crawler.locator('.shop-prerender__navigation .shop-prerender__panel');
    expect(await height(categories)).toBeLessThanOrEqual(1);
    expect(await height(crawler.locator('.shop-prerender__header'))).toBeLessThanOrEqual(scripted + 48);
    await crawler.locator('.shop-prerender__navigation [data-shop-menu]').focus();
    await crawler.keyboard.press('Tab');
    await expect.poll(() => height(categories)).toBeGreaterThan(100);
    const box = await categories.boundingBox();
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(390);
    expect(await crawler.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await context.close();
});

const INK = '88 62 47';
const PAPER = '255 253 244';
const BAND = '203 211 160';

const look = (page, scope) =>
    page.evaluate(async (scope) => {
        await document.fonts.ready;
        const context = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        const rgb = (color) => {
            context.clearRect(0, 0, 1, 1);
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);
            return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3).join(' ');
        };
        const style = (selector, pseudo) => getComputedStyle(document.querySelector(selector), pseudo);
        return {
            band: rgb(style(scope.header, '::before').backgroundColor),
            footer: rgb(style(scope.footer).backgroundColor),
            menu: rgb(style(scope.menu).color),
            cart: rgb(style(scope.cart).color),
            current: [rgb(style(scope.current).color), rgb(style(scope.current).backgroundColor)],
            heading: [style('#shop main h1').fontFamily, style('#shop main h1').fontWeight],
            body: [style('body').fontFamily, style('body').fontWeight],
            theme: document.querySelector('meta[name="theme-color"]').content,
            loaded: [...document.fonts].filter((face) => face.status === 'loaded').map((face) => face.family.replaceAll('"', '')),
        };
    }, scope);

const near = (seen, expected) => seen.split(' ').every((channel, index) => Math.abs(channel - expected.split(' ')[index]) <= 3);

const sheet = (seen) => {
    expect(near(seen.band, BAND), seen.band).toBe(true);
    expect(seen.footer).toBe(seen.band);
    expect(seen.theme).toBe('#cbd3a0');
    expect(seen.menu).toBe(INK);
    expect(seen.cart).toBe(INK);
    expect(seen.current).toEqual([PAPER, INK]);
    expect(seen.heading).toEqual([expect.stringMatching(/^"?Quicksand Variable"?, "?Quicksand"?, sans-serif$/), '300']);
    expect(seen.body).toEqual([expect.stringMatching(/^"?Huninn"?, sans-serif$/), '400']);
};

test('the band, the footer and the type follow the studio sheet, with and without JavaScript', async ({ browser, page }) => {
    const hosts = new Set();
    const fonts = [];
    page.on('request', (request) => {
        hosts.add(new URL(request.url()).host);
        if (/\/huninn-/.test(request.url())) fonts.push(new URL(request.url()).pathname.split('/').pop());
    });
    await page.goto('/beta/en/');
    const scripted = await look(page, { header: '#shop .site-header', footer: '#shop .site-footer', menu: '#shop .site-header__link', cart: '#shop [data-test="cart-link"]', current: '#shop .site-header .language-switch__link--current' });
    sheet(scripted);
    expect(scripted.loaded).toEqual(expect.arrayContaining(['Huninn', 'Quicksand Variable']));
    expect(scripted.loaded).not.toContain('Kalam');
    expect([...hosts]).toEqual([new URL(page.url()).host]);
    expect(fonts[0]).toMatch(/^huninn-latin-400-normal/);
    expect(fonts.filter((font) => !/^huninn-latin(-ext)?-400-normal/.test(font))).toEqual([]);

    const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const crawler = await context.newPage();
    await crawler.goto('/beta/en/');
    sheet(await look(crawler, { header: '.shop-prerender__header', footer: '.shop-prerender__footer', menu: '.shop-prerender__navigation a', cart: '.shop-prerender__cart', current: '.shop-prerender__header .shop-prerender__languages a[aria-current]' }));
    await context.close();
});
