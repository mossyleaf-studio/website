import { expect, test } from '@playwright/test';
import { placePaidOrder } from './support/orders.js';
import { LOGO, PNG, signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const PUBLIC = { home: '/', 'beta-note': '/beta/note/' };
const SHOP = {
    'shop-home-fr': '/beta/fr/',
    'shop-home-en': '/beta/en/',
    'shop-about-fr': '/beta/fr/about',
    'shop-about-en': '/beta/en/about',
    'shop-contact-fr': '/beta/fr/contact',
    'shop-contact-en': '/beta/en/contact',
    'shop-not-found': '/beta/fr/no-such-page',
    'shop-listing-fr': '/beta/fr/shop',
    'shop-listing-en': '/beta/en/shop',
    'shop-listing-category': '/beta/fr/shop?category=stickers',
    'shop-product-variants-fr': '/beta/fr/shop/moss-print',
    'shop-product-variants-en': '/beta/en/shop/washi-tape-set',
    'shop-product-sold-out': '/beta/fr/shop/meadow-sticker-sheet',
    'shop-product-hidden': '/beta/en/shop/dew-greeting-card',
    'shop-conventions-fr': '/beta/fr/conventions',
    'shop-conventions-en': '/beta/en/conventions',
    'shop-legal-fr': '/beta/fr/legal/terms',
};
const OPEN_SHOP = {
    home: '',
    listing: 'shop',
    'listing-category': 'shop?category=stickers',
    'product-variants': 'shop/washi-tape-set',
    'product-sold-out': 'shop/meadow-sticker-sheet',
    'product-hidden': 'shop/dew-greeting-card',
    conventions: 'conventions',
    about: 'about',
    contact: 'contact',
    'legal-terms': 'legal/terms',
    'legal-privacy': 'legal/privacy',
    'not-found': 'no-such-page',
};
const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };
const ADMIN = { texts: '/admin/texts', links: '/admin/links', images: '/admin/images', products: '/admin/products', conventions: '/admin/conventions', legal: '/admin/legal', settings: '/admin/settings' };

async function send(request, method, url, data) {
    const response = await request.fetch(url, { method, data });
    expect(response.ok(), `${method} ${url}`).toBe(true);
}

async function writeTheFrenchTexts(request) {
    await send(request, 'PUT', '/api/admin/texts/identity', {
        studioName: 'mossyleaf.studio',
        intro: 'Illustrations of little critters among leaves and moss, drawn by hand.',
        metaDescription: 'Illustrations of little critters among leaves and moss, drawn by hand.',
        introFr: 'Des illustrations de petites bêtes parmi les feuilles et la mousse, dessinées à la main.',
        metaDescriptionFr: 'Des illustrations de petites bêtes parmi les feuilles et la mousse, dessinées à la main.',
    });
    await send(request, 'PUT', '/api/admin/texts/about', {
        title: 'About the studio',
        text: 'mossyleaf.studio is a tiny illustration studio drawing critters, ferns and mushrooms.\n\nPrints and stickers are made in small batches and sold at conventions and on [Etsy](https://www.etsy.com/shop/mossyleafstudio).',
        titleFr: 'À propos du studio',
        textFr: 'mossyleaf.studio est un tout petit studio d’illustration qui dessine des bestioles, des fougères et des champignons.\n\nLes affiches et les stickers sont imprimés en petites séries, vendus en convention et sur [Etsy](https://www.etsy.com/shop/mossyleafstudio).',
    });
    await send(request, 'PUT', '/api/admin/texts/shop-notes', { announcement: 'Free shipping in France from €40', announcementFr: 'Livraison offerte en France dès 40 €', priceNote: '', priceNoteFr: '' });
}

async function writeTheConventionsAndLegalPages(request) {
    const year = new Date().getFullYear();
    await send(request, 'POST', '/api/admin/conventions', { name: 'Salon du dessin de Lyon', place: 'Halle Tony Garnier, Lyon', startsOn: `${year + 1}-03-14`, endsOn: `${year + 1}-03-16`, url: 'https://example.com/salon', note: 'Stand 42, next to the windows. Prints, stickers and a few originals.', noteFr: 'Stand 42, près des fenêtres. Affiches, stickers et quelques originaux.' });
    await send(request, 'POST', '/api/admin/conventions', { name: 'Fête des mousses', place: 'Parc de la Tête d’Or, Lyon', startsOn: `${year + 1}-05-30`, endsOn: `${year + 1}-06-01`, url: '', note: '', noteFr: 'Table ronde sous les arbres.' });
    await send(request, 'POST', '/api/admin/conventions', { name: 'Marché d’hiver', place: 'Croix-Rousse, Lyon', startsOn: `${year + 1}-12-30`, endsOn: `${year + 2}-01-02`, url: 'https://example.com/hiver', note: 'Winter market under the lights.', noteFr: 'Marché d’hiver sous les lumières.' });
    await send(request, 'PUT', '/api/admin/legal-pages/terms', {
        text: '## Orders\nEvery order is confirmed by email once SumUp has taken the payment.\nPrices include VAT.\n\n## Delivery\n- France: 3 to 5 working days\n- European Union: 5 to 10 working days\n- Rest of the world: 10 to 20 working days\n\n## Questions\nWrite through [the contact page](https://mossyleaf.studio/en/contact).',
        textFr: '## Commandes\nChaque commande est confirmée par e-mail une fois le paiement accepté par SumUp.\nLes prix sont TTC.\n\n## Livraison\n- France : 3 à 5 jours ouvrés\n- Union européenne : 5 à 10 jours ouvrés\n- Reste du monde : 10 à 20 jours ouvrés\n\n## Questions\nÉcrivez via [la page contact](https://mossyleaf.studio/fr/contact).',
    });
    await send(request, 'PUT', '/api/admin/legal-pages/privacy', { text: 'No tracker, no advertising cookie.', textFr: 'Aucun traceur, aucun cookie publicitaire.' });
}

const CART = 'mossyleaf.cart';
const line = (sku, quantity) => ({ sku, quantity });

async function fillTheCart(page, lines, country = 'FR') {
    await page.evaluate(([key, value]) => (value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value)), [CART, lines === null ? null : JSON.stringify({ lines, country })]);
}

async function shootTheCart(page, size) {
    await page.goto('/beta/fr/shop');
    for (const [locale, lines, units] of [['fr', [line('PRT-001-A4', 1), line('STK-003-B', 1), line('CRD-001', 1)], '3 articles'], ['en', [line('PRT-001-A4', 2), line('STK-003-G', 1), line('CRD-001', 1), line('PRT-002', 1)], '5 items']]) {
        await fillTheCart(page, lines);
        await page.goto(`/beta/${locale}/cart`);
        await expect(page.getByTestId('cart-line')).toHaveCount(lines.length);
        await expect(page.getByTestId('cart-units')).toHaveText(units);
        await expect(page.getByTestId('missing-for-free')).toHaveCount(locale === 'fr' ? 1 : 0);
        await expect(page.getByTestId('order-summary')).not.toHaveAttribute('aria-busy', 'true');
        await page.screenshot({ path: `shots/shop-cart-${locale}-${size}.png`, fullPage: true, animations: 'disabled' });
    }

    await fillTheCart(page, [line('PRT-001-A3', 1), line('STK-001', 1), line('STK-003-B', 5), line('NO-SUCH-SKU', 1)], 'DE');
    await page.goto('/beta/fr/cart');
    await expect(page.getByTestId('cart-issues')).toBeVisible();
    await expect(page.getByTestId('cart-line')).toHaveCount(2);
    await expect(page.getByTestId('order-summary')).not.toHaveAttribute('aria-busy', 'true');
    expect(JSON.parse(await page.evaluate((key) => localStorage.getItem(key), CART))).toEqual({ lines: [line('PRT-001-A3', 1), line('STK-003-B', 2)], country: 'DE' });
    await page.screenshot({ path: `shots/shop-cart-issues-${size}.png`, fullPage: true, animations: 'disabled' });

    await fillTheCart(page, null);
    await page.goto('/beta/fr/cart');
    await expect(page.getByTestId('cart-empty')).toBeVisible();
    await page.screenshot({ path: `shots/shop-cart-empty-${size}.png`, fullPage: true, animations: 'disabled' });

    await page.goto('/beta/fr/shop/moss-print');
    await page.getByRole('button', { name: 'Un de plus' }).click();
    await page.getByTestId('add-to-cart').click();
    await expect(page.getByTestId('cart-added')).toBeVisible();
    await expect(page.getByTestId('cart-count')).toHaveText('2');
    await expect(page.getByTestId('cart-announcement')).toHaveText('Votre panier contient maintenant 2 articles');
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: `shots/shop-product-added-${size}.png`, fullPage: true, animations: 'disabled' });
    await page.locator('header.site-header').screenshot({ path: `shots/shop-header-count-${size}.png`, animations: 'disabled' });
    await fillTheCart(page, null);
}

async function shootTheContactForm(page, size) {
    const api = '**/api/shop/fr/contact';
    await page.goto('/beta/fr/contact');
    const form = page.getByTestId('contact-form');
    const send = form.getByRole('button', { name: 'Envoyer le message' });
    await send.click();
    await expect(form.getByTestId('field-error')).toHaveCount(3);
    await expect(page.getByLabel('Votre nom')).toBeFocused();
    await page.screenshot({ path: `shots/shop-contact-errors-${size}.png`, fullPage: true, animations: 'disabled' });

    await page.getByLabel('Votre nom').fill('Lou');
    await page.getByLabel('Votre e-mail').fill('lou@example.com');
    await page.getByLabel('Votre message').fill('Bonjour ! Je voudrais une affiche de la grenouille aux fougères en A3 pour la convention de Lyon.');
    await page.route(api, (route) => route.fulfill({ status: 429, contentType: 'application/problem+json', body: JSON.stringify({ title: 'Too many requests', status: 429, detail: 'Try again later.', code: 'problem.too_many_requests' }) }));
    await send.click();
    await expect(page.getByTestId('contact-failure')).toBeVisible();
    await expect(page.getByLabel('Votre nom')).toHaveValue('Lou');
    await page.screenshot({ path: `shots/shop-contact-failure-${size}.png`, fullPage: true, animations: 'disabled' });

    await page.unroute(api);
    await send.click();
    await expect(page.getByTestId('contact-sent')).toBeFocused();
    await expect(page.getByTestId('contact-form')).toHaveCount(0);
    await page.screenshot({ path: `shots/shop-contact-sent-${size}.png`, fullPage: true, animations: 'disabled' });

    const closed = await page.context().newPage();
    await closed.setViewportSize(page.viewportSize());
    await closed.addInitScript(() => document.addEventListener('readystatechange', () => {
        const element = document.getElementById('site-content');
        if (document.readyState !== 'interactive' || !element) return;
        const props = JSON.parse(element.textContent);
        element.textContent = JSON.stringify({ ...props, data: { ...props.data, endpoint: null } });
    }));
    await closed.goto('/beta/en/contact');
    await expect(closed.getByTestId('contact-links')).toBeVisible();
    await expect(closed.getByTestId('contact-form')).toHaveCount(0);
    await closed.screenshot({ path: `shots/shop-contact-without-form-${size}.png`, fullPage: true });
    await closed.close();
}

async function shootTheSelections(page, size) {
    for (const [name, url, ticks] of [['products', '/admin/products', [0, 2, 5]], ['links', '/admin/links', [0, 1]], ['conventions', '/admin/conventions', [0, 2]], ['images', '/admin/images', [1, 2]]]) {
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        for (const index of ticks) await page.getByTestId('select-row').nth(index).click();
        await expect(page.getByTestId('selection-bar')).toHaveCSS('opacity', '1');
        await page.screenshot({ path: `shots/admin-${name}-selection-${size}.png`, animations: 'disabled' });
        await page.screenshot({ path: `shots/admin-${name}-selection-full-${size}.png`, fullPage: true, animations: 'disabled' });
    }
}

async function shootTheLoadingBar(page, size) {
    await page.goto('/beta/fr/');
    let release;
    const held = new Promise((resolve) => {
        release = resolve;
    });
    await page.route('**/beta/fr/conventions', async (route) => {
        await held;
        await route.continue();
    });
    await page.locator('#shop a[href="/beta/fr/conventions"]').filter({ visible: true }).first().click();
    await expect(page.locator('.progress-bar--visible')).toBeVisible();
    await page.screenshot({ path: `shots/shop-loading-bar-${size}.png`, animations: 'disabled' });
    release();
    await expect(page).toHaveURL(/\/beta\/fr\/conventions$/);
    await page.unroute('**/beta/fr/conventions');
}

async function fillTheCheckout(page) {
    await page.getByLabel('E-mail').fill('lou@example.com');
    await page.getByLabel('Nom et prénom').fill('Lou Martin');
    await page.getByLabel('Adresse', { exact: true }).fill('12 rue des Fougères');
    await page.getByLabel('Code postal').fill('69001');
    await page.getByLabel('Ville').fill('Lyon');
    await page.getByTestId('checkout-terms').check();
}

async function placeAnOrder(page) {
    await fillTheCart(page, [line('CRD-002', 2), line('STK-002', 3), line('PRT-002', 1)]);
    await page.goto('/beta/fr/checkout');
    await expect(page.getByTestId('checkout-pay')).toBeEnabled();
    await fillTheCheckout(page);
    await page.getByTestId('checkout-pay').click();
    await page.waitForURL(/\/_fake\/sumup\//);
}

async function shootTheCheckout(page, size) {
    await page.goto('/beta/fr/shop');
    await fillTheCart(page, [line('CRD-002', 2), line('STK-002', 3), line('PRT-002', 1)]);
    for (const [locale, pay] of [['fr', /^Payer \d/], ['en', /^Pay €\d/]]) {
        await page.goto(`/beta/${locale}/checkout`);
        await expect(page.getByTestId('checkout-pay')).toHaveText(pay);
        await expect(page.getByTestId('checkout-pay')).toBeEnabled();
        await page.screenshot({ path: `shots/shop-checkout-${locale}-${size}.png`, fullPage: true, animations: 'disabled' });
    }
    if (size === 'phone') {
        await page.getByTestId('checkout-summary-toggle').click();
        await expect(page.getByTestId('order-summary')).toBeVisible();
        await page.screenshot({ path: 'shots/shop-checkout-summary-open-phone.png', fullPage: true, animations: 'disabled' });
    }

    await page.goto('/beta/fr/checkout');
    await expect(page.getByTestId('checkout-pay')).toBeEnabled();
    await page.getByTestId('checkout-pay').click();
    await expect(page.getByTestId('field-error')).toHaveCount(6);
    await expect(page.getByLabel('E-mail')).toBeFocused();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: `shots/shop-checkout-errors-${size}.png`, fullPage: true, animations: 'disabled' });

    await placeAnOrder(page);
    await page.getByTestId('fake-sumup-back').click();
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'pending');
    await expect(page.getByTestId('order-test')).toBeVisible();
    await page.screenshot({ path: `shots/shop-order-pending-${size}.png`, fullPage: true, animations: 'disabled' });
    await page.getByTestId('order-pay').click();
    await page.getByTestId('fake-sumup-pay').click();
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
    await expect(page.getByTestId('cart-count')).toHaveText('0');
    await page.screenshot({ path: `shots/shop-order-paid-${size}.png`, fullPage: true, animations: 'disabled' });

    await placeAnOrder(page);
    await page.getByTestId('fake-sumup-decline').click();
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'failed');
    await page.screenshot({ path: `shots/shop-order-failed-${size}.png`, fullPage: true, animations: 'disabled' });
    await fillTheCart(page, null);
    return page.url();
}

async function shootTheAdminOrders(page) {
    const orders = await (await page.request.get('/api/admin/orders')).json();
    const paid = orders.find((order) => order.status === 'paid');
    expect(paid, 'the checkout shots leave a paid order').toBeDefined();
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        await page.goto('/admin/orders');
        for (const tab of ['toShip', 'other']) {
            await page.getByTestId(`order-tab-${tab}`).click();
            await expect(page.getByTestId('order-row').first()).toBeVisible();
            await page.screenshot({ path: `shots/admin-orders-${tab}-${size}.png`, fullPage: true, animations: 'disabled' });
        }
        await page.getByTestId('select-all').click();
        await expect(page.getByTestId('selection-bar')).toHaveCSS('opacity', '1');
        await page.screenshot({ path: `shots/admin-orders-selection-${size}.png`, animations: 'disabled' });
        await page.getByTestId('bulk-delete').getByRole('button').click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await page.screenshot({ path: `shots/admin-orders-bulk-confirm-${size}.png`, animations: 'disabled' });
        await page.keyboard.press('Escape');
        await page.getByTestId('selection-clear').click();
        await page.goto(`/admin/orders/${paid.id}`);
        await expect(page.getByTestId('order-page')).toBeVisible();
        await page.screenshot({ path: `shots/admin-order-to-ship-${size}.png`, fullPage: true, animations: 'disabled' });
        await page.getByTestId('order-ship').click();
        await page.getByTestId('order-ship-tracking').fill('LA123456789FR');
        await page.screenshot({ path: `shots/admin-order-ship-dialog-${size}.png`, animations: 'disabled' });
        await page.keyboard.press('Escape');
    }
    await page.goto(`/admin/orders/${paid.id}`);
    await page.getByTestId('order-ship').click();
    await page.getByTestId('order-ship-tracking').fill('LA123456789FR');
    await page.getByTestId('order-ship-submit').click();
    await expect(page.getByTestId('order-tracking')).toBeVisible();
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        await page.goto(`/admin/orders/${paid.id}`);
        await expect(page.getByTestId('order-tracking')).toBeVisible();
        await page.screenshot({ path: `shots/admin-order-shipped-${size}.png`, fullPage: true, animations: 'disabled' });
        await page.goto(paid.statusUrl);
        await expect(page.getByTestId('order-tracking-number')).toHaveText('LA123456789FR');
        await page.screenshot({ path: `shots/shop-order-shipped-${size}.png`, fullPage: true, animations: 'disabled' });
    }
}

async function setZones(request, enabled) {
    for (const [code, body] of Object.entries(ZONES)) await send(request, 'PUT', `/api/admin/shipping-zones/${code}`, { ...body, enabled });
}

async function shootThePublishBar(page) {
    const states = [['blocked', () => setZones(page.request, false)], ['ready', () => setZones(page.request, true)], ['open', () => send(page.request, 'POST', '/api/admin/publication', { page: 'full' })]];
    for (const [state, prepare] of states) {
        await prepare();
        for (const [size, viewport] of Object.entries(VIEWPORTS)) {
            await page.setViewportSize(viewport);
            await page.goto('/admin/texts');
            const bar = page.getByTestId('publish-bar');
            await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', state === 'open' ? 'true' : 'false');
            if (state !== 'open') await expect(bar.getByTestId('publish-ready-count')).toBeVisible();
            if (state === 'blocked') await expect(bar.getByTestId('readiness-shipping')).toHaveAttribute('data-state', 'blocker');
            if (state === 'ready') await expect(bar.getByTestId('readiness-shipping')).toHaveCount(0);
            await page.screenshot({ path: `shots/admin-publish-${state}-${size}.png`, animations: 'disabled' });
        }
    }
}

async function shootTheOpenShop(browser) {
    const visitors = {};
    let orderUrl;
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        const context = await browser.newContext({ viewport });
        const visitor = await context.newPage();
        visitors[size] = visitor;
        for (const locale of ['fr', 'en']) {
            for (const [name, path] of Object.entries(OPEN_SHOP)) {
                const response = await visitor.goto(`/${locale}/${path}`);
                expect(response.status(), `${locale} ${name}`).toBe('not-found' === name || 'product-hidden' === name ? 404 : 200);
                await expect(visitor.getByRole('complementary')).toHaveCount(0);
                await visitor.waitForLoadState('networkidle');
                await visitor.screenshot({ path: `shots/open-${name}-${locale}-${size}.png`, fullPage: true, animations: 'disabled' });
            }
        }

        const { url } = await placePaidOrder(visitor, [{ sku: 'CRD-001', quantity: 2 }, { sku: 'PRT-001-A4', quantity: 1 }], '');
        orderUrl = url;
        await expect(visitor.getByTestId('order-test')).toHaveCount(0);
        await visitor.screenshot({ path: `shots/open-order-paid-en-${size}.png`, fullPage: true, animations: 'disabled' });
        await visitor.goto(url.replace('/en/order/', '/fr/order/'));
        await expect(visitor.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
        await visitor.screenshot({ path: `shots/open-order-paid-fr-${size}.png`, fullPage: true, animations: 'disabled' });

        await visitor.goto('/en/shop');
        await visitor.evaluate(([key, value]) => localStorage.setItem(key, value), [CART, JSON.stringify({ lines: [line('CRD-001', 2), line('PRT-001-A4', 1)], country: 'FR' })]);
        for (const [name, locale] of [['cart', 'fr'], ['cart', 'en'], ['checkout', 'fr'], ['checkout', 'en']]) {
            await visitor.goto(`/${locale}/${name}`);
            await expect(visitor.getByTestId(name === 'cart' ? 'cart-line' : 'checkout-pay').first()).toBeVisible();
            await expect(visitor.getByTestId('order-summary')).not.toHaveAttribute('aria-busy', 'true');
            await visitor.screenshot({ path: `shots/open-${name}-${locale}-${size}.png`, fullPage: true, animations: 'disabled' });
        }
        await visitor.evaluate((key) => localStorage.removeItem(key), CART);
        await visitor.goto('/fr/cart');
        await expect(visitor.getByTestId('cart-empty')).toBeVisible();
        await visitor.screenshot({ path: `shots/open-cart-empty-fr-${size}.png`, fullPage: true, animations: 'disabled' });
        await visitor.goto('/fr/shop/moss-print');
        await visitor.getByTestId('add-to-cart').click();
        await expect(visitor.getByTestId('cart-added')).toBeVisible();
        await visitor.evaluate(() => window.scrollTo(0, 0));
        await visitor.screenshot({ path: `shots/open-product-added-fr-${size}.png`, fullPage: true, animations: 'disabled' });
        await visitor.evaluate((key) => localStorage.removeItem(key), CART);
    }

    const crawler = await (await browser.newContext({ javaScriptEnabled: false, viewport: VIEWPORTS.phone })).newPage();
    for (const [name, path, marker] of [['home', '/fr/', '#shop main h1'], ['listing', '/fr/shop', '#shop main h1'], ['product', '/fr/shop/moss-print', '#shop main h1'], ['legal', '/en/legal/terms', '#shop main [data-blocks]'], ['contact', '/fr/contact', '#shop main [data-noscript]'], ['checkout', '/fr/checkout', '#shop main [data-noscript]'], ['order', new URL(orderUrl).pathname, '#shop main [data-order]']]) {
        await crawler.goto(path);
        await expect(crawler.locator(marker).first(), name).toBeVisible();
        await crawler.screenshot({ path: `shots/open-${name}-without-javascript.png`, fullPage: true });
    }
    await crawler.context().close();
    for (const visitor of Object.values(visitors)) await visitor.context().close();
}

test('screenshots of the site and the admin', async ({ page }) => {
    test.setTimeout(240_000);
    await signIn(page);
    await page.setViewportSize(VIEWPORTS.phone);
    await page.goto(SHOP['shop-home-fr']);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'shots/shop-home-without-logo-phone.png', fullPage: true });
    await page.goto('/admin/texts');
    await page.getByTestId('section-logo').locator('input[type="file"]').setInputFiles(LOGO);
    await expect(page.getByTestId('section-logo').getByRole('img')).toBeVisible();
    await page.goto('/admin/images');
    await page.locator('input[type="file"]').setInputFiles([PNG, PNG, PNG]);
    await expect(page.getByTestId('artwork-card')).toHaveCount(3);
    await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Feature' }).click();
    await expect(page.getByTestId('artwork-card').first().getByText('Featured', { exact: true })).toBeVisible();
    await writeTheFrenchTexts(page.request);
    await writeTheConventionsAndLegalPages(page.request);

    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        for (const [name, url] of Object.entries({ ...PUBLIC, ...SHOP, ...Object.fromEntries(Object.entries(ADMIN).map(([key, value]) => [`admin-${key}`, value])) })) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/${name}-${size}.png`, fullPage: true });
        }
        await page.goto('/admin/products');
        await page.getByTestId('product-edit').first().click();
        await expect(page.getByTestId('product-editor')).toBeVisible();
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `shots/admin-product-editor-${size}.png`, fullPage: true, animations: 'disabled' });
        await page.goto('/admin/links');
        await page.getByTestId('link-row').first().getByRole('button', { name: 'Edit' }).click();
        await expect(page.getByTestId('link-editor')).toBeVisible();
        await page.screenshot({ path: `shots/admin-link-editor-${size}.png`, animations: 'disabled' });
        await shootTheSelections(page, size);
    }

    let order = null;
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        await shootTheLoadingBar(page, size);
        await shootTheContactForm(page, size);
        await shootTheCart(page, size);
        order = await shootTheCheckout(page, size);
    }
    await shootTheAdminOrders(page);

    const crawler = await (await page.context().browser().newContext({ javaScriptEnabled: false, viewport: VIEWPORTS.desktop })).newPage();
    await crawler.goto('/');
    await crawler.screenshot({ path: 'shots/home-without-javascript.png', fullPage: true });

    const editorWithoutScript = await (await page.context().browser().newContext({ javaScriptEnabled: false, viewport: VIEWPORTS.phone, storageState: await page.context().storageState() })).newPage();
    await editorWithoutScript.goto(SHOP['shop-home-fr']);
    await expect(editorWithoutScript.locator('#shop main h1')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-home-without-javascript.png', fullPage: true });
    await editorWithoutScript.goto(SHOP['shop-product-variants-fr']);
    await expect(editorWithoutScript.locator('#shop main h1')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-product-without-javascript.png', fullPage: true });
    await editorWithoutScript.setViewportSize(VIEWPORTS.desktop);
    await editorWithoutScript.goto(SHOP['shop-listing-fr']);
    await editorWithoutScript.screenshot({ path: 'shots/shop-listing-without-javascript.png', fullPage: true });
    await editorWithoutScript.goto(SHOP['shop-legal-fr']);
    await expect(editorWithoutScript.locator('#shop main [data-blocks]')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-legal-without-javascript.png', fullPage: true });
    await editorWithoutScript.goto('/beta/fr/contact');
    await expect(editorWithoutScript.locator('#shop main [data-noscript]')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-contact-without-javascript.png', fullPage: true });
    await editorWithoutScript.goto('/beta/fr/checkout');
    await expect(editorWithoutScript.locator('#shop main [data-noscript]')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-checkout-without-javascript.png', fullPage: true });
    await editorWithoutScript.goto(new URL(order).pathname);
    await expect(editorWithoutScript.locator('#shop main [data-order]')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-order-without-javascript.png', fullPage: true });
    await editorWithoutScript.context().close();

    try {
        await shootThePublishBar(page);
        await shootTheOpenShop(page.context().browser());
    } finally {
        await setZones(page.request, true);
        await send(page.request, 'POST', '/api/admin/publication', { page: 'note' });
    }

    await page.goto('/admin/images');
    for (let count = 3; count > 0; count -= 1) {
        await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByTestId('artwork-card')).toHaveCount(count - 1);
    }
});

test('screenshots of the admin layout', async ({ page }) => {
    test.setTimeout(120_000);
    await signIn(page);
    await placePaidOrder(page, [{ sku: 'CRD-001', quantity: 2 }, { sku: 'PRT-001-A4', quantity: 1 }]);
    const orders = await (await page.request.get('/api/admin/orders')).json();
    const products = await (await page.request.get('/api/admin/products')).json();
    const pages = { ...ADMIN, orders: '/admin/orders', order: `/admin/orders/${orders[0].id}`, product: `/admin/products/${products[0].id}`, 'not-found': '/admin/nowhere' };
    for (const [size, viewport] of Object.entries({ ...VIEWPORTS, laptop: { width: 1100, height: 800 } })) {
        await page.setViewportSize(viewport);
        for (const [name, path] of Object.entries(pages)) {
            await page.goto(path);
            await expect(page.getByTestId('publish-readiness')).toBeVisible();
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/layout-${name}-${size}.png`, fullPage: true, animations: 'disabled' });
        }
        if (size !== 'desktop') {
            await page.goto('/admin/orders');
            await page.getByTestId('publish-readiness').getByRole('button').click();
            await expect(page.getByTestId('publish-readiness').getByRole('listitem').first()).toBeVisible();
            await page.screenshot({ path: `shots/layout-list-open-${size}.png`, fullPage: true, animations: 'disabled' });
        }
    }
});
