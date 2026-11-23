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

test('the menu lists Categories and Collections as two entries of their own', async ({ page }) => {
    await page.goto('/beta/en/');
    await expect(header(page).locator('#site-navigation > li > :is(a, button)')).toHaveText(['Home', 'Categories', 'Collections', 'Conventions', 'About', 'Contact']);
    await expect(header(page).getByRole('button', { name: 'Categories' })).toHaveAttribute('data-menu', 'categories');
    await expect(header(page).getByRole('button', { name: 'Collections' })).toHaveAttribute('data-menu', 'collections');
    await expect(header(page).locator('[data-shop-menu], #shop-panel, #shop-categories')).toHaveCount(0);
    for (const key of ['categories', 'collections']) {
        await expect(button(page, key)).toHaveAttribute('aria-controls', `menu-${key}`);
        await expect(header(page).locator(`#menu-${key} > ul[data-${key}]`)).toHaveCount(1);
    }
    const row = await Promise.all((await header(page).locator('#site-navigation > li').all()).map((item) => item.boundingBox()));
    expect(new Set(row.map((box) => Math.round(box.y))).size).toBe(1);
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
    const sheet = await header(page).locator('#menu-categories').boundingBox();
    const entry = await button(page).boundingBox();
    expect(Math.abs(sheet.x + sheet.width / 2 - (entry.x + entry.width / 2))).toBeLessThanOrEqual(1);
    expect(sheet.width).toBeLessThanOrEqual(352);
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

test('the collections entry is current on a collection page', async ({ page }) => {
    await page.goto('/beta/en/collection/forest-critters');
    await expect(button(page, 'collections')).toHaveAttribute('aria-current', 'true');
    await expect(button(page)).not.toHaveAttribute('aria-current');
    await button(page, 'collections').click();
    await expect(collections(page).getByRole('link', { name: 'Forest critters' })).toHaveAttribute('aria-current', 'page');
});

test('the product breadcrumb goes through home and the category', async ({ page }) => {
    await page.goto('/beta/en/shop/moss-print');
    const trail = page.locator('#shop [data-breadcrumb]');
    await expect(trail.getByRole('listitem')).toHaveText(['Home', 'Prints', 'Moss print']);
    await expect(trail.getByRole('link', { name: 'Prints' })).toHaveAttribute('href', '/beta/en/category/prints');
    await expect(button(page)).toHaveAttribute('aria-current', 'true');
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
    await expect(headings.first()).toHaveCSS('text-transform', 'none');
    await expect(header(page).locator('#site-navigation').getByRole('link', { name: 'Catégories', exact: true })).toHaveCount(0);
    await expect(panel(page).getByRole('link', { name: 'Tirages' })).toHaveAttribute('aria-current', 'page');
    for (const box of await Promise.all((await header(page).locator('[data-categories] a, [data-collections] a').all()).map((link) => link.boundingBox()))) expect(box.height).toBeGreaterThanOrEqual(44);
    const top = await header(page).locator('#menu-categories').boundingBox();
    const bottom = await header(page).locator('#menu-collections').boundingBox();
    expect(Math.abs(top.y + top.height - bottom.y)).toBeLessThanOrEqual(1);
});

test('under 56rem the group of the current page is marked in the menu', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/en/shop/moss-print');
    await page.locator('#shop .site-header__menu').click();
    const group = header(page).locator('#menu-categories').getByRole('heading', { name: 'Categories' });
    await expect(group).toHaveAttribute('aria-current', 'true');
    await expect(group).toHaveCSS('font-weight', '700');
    await expect(header(page).locator('#menu-collections').getByRole('heading')).not.toHaveAttribute('aria-current');
    await expect(header(page).locator('#menu-collections').getByRole('heading')).not.toHaveCSS('font-weight', '700');
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

test('the header has the same height with and without JavaScript', async ({ browser, page }) => {
    for (const [width, locale] of [[360, 'en'], [360, 'fr'], [390, 'en'], [390, 'fr'], [900, 'en'], [1440, 'en']]) {
        const viewport = { width, height: 844 };
        await page.setViewportSize(viewport);
        await page.goto(`/beta/${locale}/category/prints`);
        const scripted = await height(header(page));
        const context = await browser.newContext({ javaScriptEnabled: false, viewport, storageState: await page.context().storageState() });
        const crawler = await context.newPage();
        await crawler.goto(`/beta/${locale}/category/prints`);
        const plain = await height(crawler.locator('.shop-prerender__header'));
        expect(plain - scripted, `${width}px ${locale}`).toBeLessThanOrEqual(width < 896 ? 48 : 2);
        expect(plain - scripted, `${width}px ${locale}`).toBeGreaterThanOrEqual(-2);
        await context.close();
    }
});

test('without JavaScript the phone header keeps the menu in wrapped rows and its panels folded', async ({ browser, page }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    const crawler = await context.newPage();
    await crawler.goto('/beta/en/category/prints');
    for (const key of ['categories', 'collections']) {
        const sheet = crawler.locator(`.shop-prerender__navigation #menu-${key}`);
        expect(await height(sheet)).toBeLessThanOrEqual(1);
        await crawler.locator(`.shop-prerender__navigation [data-menu="${key}"]`).focus();
        await crawler.keyboard.press('Tab');
        await expect.poll(() => height(sheet)).toBeGreaterThan(80);
        const box = await sheet.boundingBox();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(390);
        expect(await crawler.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    }
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
