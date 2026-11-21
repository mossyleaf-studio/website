import { expect, test } from '@playwright/test';
import { fillForm, openCheckout, pay } from './support/orders.js';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };

async function publish(page, button) {
    const bar = page.getByTestId('publish-bar');
    await bar.getByRole('button', { name: button }).click();
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/publication') && response.request().method() === 'POST' && response.ok()),
        page.getByRole('alertdialog').getByRole('button', { name: button }).click(),
    ]);
}

async function mark(page) {
    await page.evaluate(() => {
        window.__walk = 'alive';
    });
}

async function alive(page) {
    return page.evaluate(() => window.__walk);
}

async function head(page) {
    const [shown, fresh] = await page.evaluate(async () => {
        const read = (doc) =>
            JSON.stringify({
                style: doc.documentElement.getAttribute('style'),
                canonical: [...doc.querySelectorAll('link[rel="canonical"]')].map((link) => link.getAttribute('href')),
                alternates: [...doc.querySelectorAll('link[rel="alternate"]')].map((link) => `${link.hreflang} ${link.getAttribute('href')}`),
                robots: [...doc.querySelectorAll('meta[name="robots"]')].map((meta) => meta.content),
                cache: [...doc.querySelectorAll('meta[name="turbo-cache-control"]')].map((meta) => meta.content),
            });
        const html = await (await fetch(window.location.href)).text();
        return [read(document), read(new DOMParser().parseFromString(html, 'text/html'))];
    });
    expect(shown).toBe(fresh);
}

async function visit(page, prefix, href, url) {
    await page.evaluate(() => document.documentElement.setAttribute('style', '--font-display: stale'));
    await page.locator(`#shop a[href="${prefix}${href}"]`).filter({ visible: true }).first().click();
    await expect(page).toHaveURL(url);
    await expect(page.locator('#shop')).toHaveAttribute('data-page', /.+/);
    expect(await alive(page), href).toBe('alive');
    await head(page);
}

async function arrived(page) {
    await expect(page.locator('#shop')).toBeFocused();
    await expect(page.locator('#shop-announcer')).toHaveText(await page.title());
    expect((await page.title()).length).toBeGreaterThan(0);
}

async function walk(page, prefix) {
    await page.goto(`${prefix}/en/`);
    await mark(page);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    const titles = [await page.title()];

    const steps = [
        ['/en/shop', 'shop', 'listing'],
        ['/en/shop?category=prints', 'shop\\?category=prints', 'filtered listing'],
        ['/en/shop/moss-print', 'shop/moss-print', 'product'],
    ];
    for (const [href, pattern] of steps) {
        await visit(page, prefix, href, new RegExp(`${prefix}/en/${pattern}$`));
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await arrived(page);
        titles.push(await page.title());
    }
    await expect(page.getByRole('heading', { level: 1, name: 'Moss print' })).toBeVisible();
    expect(await page.title()).toContain('Moss print');

    await page.getByTestId('add-to-cart').click();
    await expect(page.getByTestId('cart-added')).toBeVisible();
    await expect(page.getByTestId('cart-count')).toHaveText('1');

    await visit(page, prefix, '/en/cart', new RegExp(`${prefix}/en/cart$`));
    await arrived(page);
    await expect(page.getByTestId('cart-count')).toHaveText('1');
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    titles.push(await page.title());

    await visit(page, prefix, '/en/conventions', new RegExp(`${prefix}/en/conventions$`));
    await arrived(page);
    titles.push(await page.title());
    await visit(page, prefix, '/en/legal/terms', new RegExp(`${prefix}/en/legal/terms$`));
    await arrived(page);
    titles.push(await page.title());
    expect(new Set(titles).size).toBeGreaterThan(4);

    await page.locator('#shop nav[data-languages]').first().getByRole('link', { name: 'Français' }).click();
    await expect(page).toHaveURL(new RegExp(`${prefix}/fr/legal/terms$`));
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    expect(await alive(page)).toBe('alive');

    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${prefix}/en/legal/terms$`));
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('#shop[data-page]')).toHaveCount(1);
    await expect(page.locator('#shop .site-header').first()).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${prefix}/en/conventions$`));
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${prefix}/en/cart$`));
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-count')).toHaveText('1');
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${prefix}/en/shop/moss-print$`));
    await expect(page.getByRole('heading', { level: 1, name: 'Moss print' })).toBeVisible();
    await page.goForward();
    await expect(page).toHaveURL(new RegExp(`${prefix}/en/cart$`));
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    expect(await alive(page)).toBe('alive');
    await expect(page.locator('#shop')).toHaveCount(1);
}

test.describe('in the preview', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page);
    });

    test('the pages change without a reload and history works', async ({ page }) => {
        await walk(page, '/beta');
    });

    test('an order page left while it asks its status leaves no poll behind', async ({ page }) => {
        test.setTimeout(60_000);
        await openCheckout(page, [{ sku: 'STK-002', quantity: 1 }]);
        await fillForm(page);
        await pay(page);
        const sumUp = page.url();
        const statusUrl = await page.getByRole('link', { name: 'Back to the shop' }).getAttribute('href');

        const asked = [];
        let release;
        const held = new Promise((resolve) => {
            release = resolve;
        });
        await page.route('**/beta/api/shop/en/orders/*', async (route) => {
            asked.push(route.request().url());
            if (asked.length === 1) await held;
            await route.continue();
        });
        await page.goto(statusUrl);
        await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'pending');
        await mark(page);
        await expect.poll(() => asked.length, { timeout: 10_000 }).toBe(1);

        await visit(page, '/beta', '/en/cart', /\/beta\/en\/cart$/);
        release();
        await page.waitForTimeout(6000);
        expect(asked).toHaveLength(1);

        await page.unroute('**/beta/api/shop/en/orders/*');
        await page.goto(sumUp);
        await page.getByTestId('fake-sumup-decline').click();
        await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'failed');
    });

    test('the links that leave the shop reload the document', async ({ page }) => {
        await page.goto('/beta/en/');
        await mark(page);
        await expect(page.getByRole('complementary', { name: 'Draft preview' })).toBeVisible();
        await page.getByRole('complementary', { name: 'Draft preview' }).getByRole('link', { name: 'Growing note' }).click();
        await expect(page).toHaveURL(/\/beta\/note\/$/);
        await expect(page.locator('html[data-layout="shop"]')).toHaveCount(0);
        expect(await alive(page)).toBeUndefined();

        await page.goto('/beta/en/');
        await mark(page);
        await page.getByRole('complementary', { name: 'Draft preview' }).getByRole('link', { name: 'Back to the admin' }).click();
        await expect(page).toHaveURL(/\/admin\//);
        expect(await alive(page)).toBeUndefined();
    });
});

test.describe('on the public address', () => {
    test.afterAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await signIn(page);
        await page.request.post('/api/admin/publication', { data: { page: 'note' } });
        await context.close();
    });

    test('the open shop changes page without a reload, then the closed shop ends on the note', async ({ page, browser }) => {
        test.setTimeout(120_000);
        const editor = await browser.newContext();
        const admin = await editor.newPage();
        await signIn(admin);
        for (const [code, body] of Object.entries(ZONES)) {
            expect((await admin.request.put(`/api/admin/shipping-zones/${code}`, { data: body })).ok()).toBe(true);
        }
        await admin.goto('/admin/texts');
        await publish(admin, 'Publish');

        await walk(page, '');

        await admin.goto('/admin/texts');
        await publish(admin, 'Show the growing note');
        await editor.close();

        await page.goto('/en/shop');
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
        await expect(page.locator('html[data-layout="shop"]')).toHaveCount(0);
        expect(await page.evaluate(() => typeof window.Turbo)).toBe('undefined');
    });
});
