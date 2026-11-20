import { expect, test } from '@playwright/test';
import { LOGO, PNG, signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const PUBLIC = { home: '/', beta: '/beta/' };
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
const ADMIN = { texts: '/admin/texts', links: '/admin/links', images: '/admin/images', products: '/admin/products', conventions: '/admin/conventions', legal: '/admin/legal' };

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

test('screenshots of the site and the admin', async ({ page }) => {
    test.setTimeout(120_000);
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
    }

    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        await shootTheContactForm(page, size);
    }

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
    await editorWithoutScript.context().close();

    await page.goto('/admin/images');
    for (let count = 3; count > 0; count -= 1) {
        await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByTestId('artwork-card')).toHaveCount(count - 1);
    }
});
