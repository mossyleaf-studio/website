import { expect, test } from '@playwright/test';
import { fillForm, MAILPIT, messages, pay, REFERENCE } from './support/orders.js';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };
const PUBLIC_PAGES = ['/en/', '/fr/', '/en/category/prints', '/fr/category/stickers', '/en/shop/moss-print', '/fr/conventions', '/fr/contact', '/en/legal/terms', '/fr/about'];

async function setZones(request, enabled) {
    for (const [code, body] of Object.entries(ZONES)) {
        const response = await request.put(`/api/admin/shipping-zones/${code}`, { data: { ...body, enabled } });
        expect(response.ok(), `zone ${code}`).toBe(true);
    }
}

async function publish(page, button) {
    await page.getByTestId('publish-bar').getByRole('button', { name: button }).click();
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/publication') && response.request().method() === 'POST' && response.ok()),
        page.getByRole('alertdialog').getByRole('button', { name: button }).click(),
    ]);
}

async function productId(request, reference) {
    const products = await (await request.get('/api/admin/products')).json();
    return products.find((product) => product.reference === reference).id;
}

test.afterAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await signIn(page);
    await setZones(page.request, true);
    const id = await productId(page.request, 'STK-002');
    await page.request.put(`/api/admin/products/${id}/visibility`);
    await page.request.post('/api/admin/publication', { data: { page: 'note' } });
    await context.close();
});

test('with every shipping zone off, Publish is disabled and the list says why', async ({ page }) => {
    await signIn(page);
    await setZones(page.request, false);
    await page.goto('/admin/texts');

    const bar = page.getByTestId('publish-bar');
    await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'false');
    await expect(bar.getByTestId('publish-action')).toBeDisabled();
    await expect(bar.getByTestId('readiness-shipping')).toHaveAttribute('data-state', 'blocker');
    await expect(bar.getByTestId('publish-blocked')).toBeVisible();
    await expect(bar.getByTestId('publish-ready-count')).toBeVisible();
    await expect(bar.getByTestId('readiness-products')).toHaveCount(0);

    await setZones(page.request, true);
    await page.reload();
    await expect(bar.getByTestId('publish-ready-count')).toBeVisible();
    await expect(bar.getByTestId('readiness-shipping')).toHaveCount(0);
    await expect(bar.getByTestId('publish-blocked')).toHaveCount(0);
    await expect(bar.getByTestId('publish-action')).toBeEnabled();
});

test('the studio opens the shop, a visitor buys a postcard set, then the studio shows the note again', async ({ page, browser, request }) => {
    test.setTimeout(120_000);
    await request.delete(`${MAILPIT}/api/v1/messages`);
    await signIn(page);
    await page.goto('/admin/texts');

    const bar = page.getByTestId('publish-bar');
    await test.step('the editor reads the list and publishes', async () => {
        await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'false');
        await expect(bar.getByTestId('publish-ready-count')).toBeVisible();
        for (const key of ['identity', 'payment', 'mail', 'shipping', 'products', 'terms', 'legalNotice', 'privacy']) {
            await expect(bar.getByTestId(`readiness-${key}`)).toHaveCount(0);
        }
        await expect(bar.getByTestId('publish-action')).toBeEnabled();
        await publish(page, 'Publish');
        await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'true');
        await expect(bar).toContainText('The shop is open. Last published');
    });

    const french = await browser.newContext({ locale: 'fr-FR' });
    const english = await browser.newContext({ locale: 'en-GB' });
    const visitor = await english.newPage();
    const noCookie = async () => {
        expect(await french.cookies()).toEqual([]);
        expect(await english.cookies()).toEqual([]);
    };

    await test.step('the home address leads to the shop in the language of the visitor', async () => {
        const frenchPage = await french.newPage();
        await frenchPage.goto('/');
        await expect(frenchPage).toHaveURL(/\/fr\/$/);
        await visitor.goto('/');
        await expect(visitor).toHaveURL(/\/en\/$/);

        const answer = await english.request.get('/', { maxRedirects: 0, headers: { 'Accept-Language': 'fr-FR,fr;q=0.9' } });
        expect(answer.status()).toBe(302);
        expect(answer.headers().location).toMatch(/\/fr\/$/);
        expect(answer.headers().vary).toContain('Accept-Language');
        expect(answer.headers()['set-cookie']).toBeUndefined();
        await noCookie();
    });

    await test.step('the public pages answer, without the draft banner', async () => {
        for (const url of PUBLIC_PAGES) {
            const response = await visitor.goto(url);
            expect(response.status(), url).toBe(200);
            await expect(visitor.locator('html[data-layout="shop"]'), url).toHaveCount(1);
            await expect(visitor.getByRole('complementary', { name: /Draft preview|Aperçu du brouillon/ }), url).toHaveCount(0);
            await expect(visitor.getByRole('heading', { level: 1 }).first(), url).toBeVisible();
        }
        const listing = await english.request.get('/en/category/prints');
        expect(listing.headers()['cache-control']).toContain('public');
        const etag = listing.headers().etag;
        expect(etag).toBeTruthy();
        expect((await english.request.get('/en/category/prints', { headers: { 'If-None-Match': etag } })).status()).toBe(304);
        await noCookie();
    });

    await test.step('a product hidden in the admin disappears from the shop', async () => {
        await visitor.goto('/en/category/stickers');
        await expect(visitor.getByTestId('product-card').filter({ hasText: 'Leaf sticker pack' })).toHaveCount(1);
        const id = await productId(page.request, 'STK-002');
        expect((await page.request.delete(`/api/admin/products/${id}/visibility`)).ok()).toBe(true);

        await visitor.goto('/en/category/stickers');
        await expect(visitor.getByTestId('product-card').filter({ hasText: 'Leaf sticker pack' })).toHaveCount(0);
        await expect(visitor.getByTestId('product-card').filter({ hasText: 'Dew greeting card' })).toHaveCount(0);
        for (const slug of ['leaf-sticker-pack', 'dew-greeting-card']) {
            const response = await visitor.goto(`/en/shop/${slug}`);
            expect(response.status(), slug).toBe(404);
            await expect(visitor.getByRole('heading', { name: 'Page not found' })).toBeVisible();
        }
        const quote = await english.request.post('/api/shop/en/quote', { data: { lines: [{ sku: 'STK-002', quantity: 1 }], country: 'FR' } });
        expect(quote.status()).toBe(200);
        expect((await quote.json()).lines).toHaveLength(0);
        expect((await page.request.put(`/api/admin/products/${id}/visibility`)).ok()).toBe(true);
    });

    let reference;
    let orderUrl;
    await test.step('a visitor puts a product in the cart, pays on the SumUp page and sees a real order', async () => {
        await visitor.goto('/en/shop/postcard-set');
        await visitor.getByTestId('add-to-cart').click();
        await expect(visitor.getByTestId('cart-added')).toBeVisible();
        await visitor.getByTestId('see-cart').click();
        await expect(visitor).toHaveURL(/\/en\/cart$/);
        await expect(visitor.getByTestId('cart-line')).toHaveCount(1);
        await expect(visitor.getByTestId('summary-goods')).toContainText('12.00');
        await expect(visitor.getByTestId('summary-shipping')).toContainText('4.50');
        await expect(visitor.getByTestId('summary-total')).toContainText('16.50');

        await visitor.getByTestId('checkout').click();
        await expect(visitor).toHaveURL(/\/en\/checkout$/);
        await expect(visitor.getByTestId('checkout-pay')).toBeEnabled();
        await fillForm(visitor);
        await pay(visitor);
        await visitor.getByTestId('fake-sumup-pay').click();

        await expect(visitor).toHaveURL(/\/en\/order\//);
        await expect(visitor.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
        await expect(visitor.getByTestId('order-test')).toHaveCount(0);
        orderUrl = visitor.url();
        reference = (await visitor.getByTestId('order-reference').textContent()).match(REFERENCE)[0];
        await expect(visitor.getByTestId('cart-count')).toHaveText('0');
        const headers = (await english.request.get(orderUrl)).headers();
        expect(headers['cache-control']).toContain('no-store');
        await noCookie();

        await expect.poll(async () => (await messages(request)).length).toBe(2);
        for (const mail of await messages(request)) expect(mail.Subject).not.toContain('[TEST]');
    });

    await test.step('the order waits to be shipped in the admin, without the test label', async () => {
        await page.goto('/admin/orders');
        await page.getByTestId('order-tab-toShip').click();
        const row = page.locator(`[data-test="order-row"][data-reference="${reference}"]`);
        await expect(row).toBeVisible();
        await expect(row).toContainText('Camille Martin');
        await expect(row.getByTestId('order-test')).toHaveCount(0);
    });

    await test.step('the contact form reaches the studio mailbox', async () => {
        await request.delete(`${MAILPIT}/api/v1/messages`);
        await visitor.goto('/fr/contact');
        await visitor.getByLabel('Votre nom').fill('Alix Dupont');
        await visitor.getByLabel('Votre e-mail').fill('alix@example.test');
        await visitor.getByLabel('Votre message').fill('Bonjour, avez-vous des cartes en A5 ?');
        await visitor.getByTestId('contact-send').click();
        await expect(visitor.getByTestId('contact-sent')).toBeVisible();
        await expect.poll(async () => (await messages(request)).length).toBe(1);
        const mail = await (await request.get(`${MAILPIT}/api/v1/message/latest`)).json();
        expect(mail.To.map((address) => address.Address)).toEqual(['studio@mossyleaf.test']);
        expect(mail.ReplyTo.map((address) => address.Address)).toEqual(['alix@example.test']);
        await noCookie();
    });

    await test.step('the sitemap lists the pages in both languages and robots.txt points to it', async () => {
        const sitemap = await (await english.request.get('/sitemap.xml')).text();
        for (const path of ['/en/', '/fr/', '/en/category/prints', '/fr/category/prints', '/en/shop/moss-print', '/fr/shop/moss-print', '/en/conventions', '/fr/conventions', '/en/about', '/fr/contact', '/en/legal/terms', '/fr/legal/privacy']) {
            expect(sitemap, path).toContain(`${path}</loc>`);
        }
        expect(sitemap).toContain('hreflang="fr"');
        expect(sitemap).toContain('hreflang="en"');
        expect(sitemap).toContain('hreflang="x-default"');
        for (const hidden of ['/en/shop</loc>', '/cart', '/checkout', '/order/', 'dew-greeting-card', 'wall-calendar']) expect(sitemap, hidden).not.toContain(hidden);

        const robots = await (await english.request.get('/robots.txt')).text();
        expect(robots).toMatch(/^Sitemap: .+\/sitemap\.xml$/m);
        expect(robots).toContain('Disallow: /en/order/');
    });

    await test.step('the studio shows the note again: the shop closes, the order page stays', async () => {
        await page.goto('/admin/texts');
        await publish(page, 'Show the growing note');
        await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'false');

        await visitor.goto('/');
        await expect(visitor).toHaveURL(/\/$/);
        await expect(visitor.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
        await expect(visitor.locator('html[data-layout="shop"]')).toHaveCount(0);
        for (const url of ['/en/shop', '/fr/shop/moss-print', '/en/cart', '/en/legal/terms']) {
            await visitor.goto(url);
            await expect(visitor, url).toHaveURL(/\/$/);
            await expect(visitor.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
        }
        expect((await english.request.post('/api/shop/en/quote', { data: { lines: [], country: 'FR' } })).status()).toBe(404);

        const order = await visitor.goto(orderUrl);
        expect(order.status()).toBe(200);
        await expect(visitor.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
        await noCookie();
    });

    await french.close();
    await english.close();
});
