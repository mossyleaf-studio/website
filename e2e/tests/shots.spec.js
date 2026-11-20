import { expect, test } from '@playwright/test';
import { LOGO, PNG, signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const PUBLIC = { home: '/', beta: '/beta/' };
const SHOP = {
    'shop-home-fr': '/beta/fr/',
    'shop-home-en': '/beta/en/',
    'shop-about-fr': '/beta/fr/about',
    'shop-about-en': '/beta/en/about',
    'shop-not-found': '/beta/fr/no-such-page',
};
const ADMIN = { texts: '/admin/texts', links: '/admin/links', images: '/admin/images' };

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

test('screenshots of the site and the admin', async ({ page }) => {
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

    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        for (const [name, url] of Object.entries({ ...PUBLIC, ...SHOP, ...Object.fromEntries(Object.entries(ADMIN).map(([key, value]) => [`admin-${key}`, value])) })) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/${name}-${size}.png`, fullPage: true });
        }
        await page.goto('/admin/links');
        await page.getByTestId('link-row').first().getByRole('button', { name: 'Edit' }).click();
        await expect(page.getByTestId('link-editor')).toBeVisible();
        await page.screenshot({ path: `shots/admin-link-editor-${size}.png`, animations: 'disabled' });
    }

    const crawler = await (await page.context().browser().newContext({ javaScriptEnabled: false, viewport: VIEWPORTS.desktop })).newPage();
    await crawler.goto('/');
    await crawler.screenshot({ path: 'shots/home-without-javascript.png', fullPage: true });

    const editorWithoutScript = await (await page.context().browser().newContext({ javaScriptEnabled: false, viewport: VIEWPORTS.phone, storageState: await page.context().storageState() })).newPage();
    await editorWithoutScript.goto(SHOP['shop-home-fr']);
    await expect(editorWithoutScript.locator('#shop main h1')).toBeVisible();
    await editorWithoutScript.screenshot({ path: 'shots/shop-home-without-javascript.png', fullPage: true });
    await editorWithoutScript.context().close();

    await page.goto('/admin/images');
    for (let count = 3; count > 0; count -= 1) {
        await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByTestId('artwork-card')).toHaveCount(count - 1);
    }
});
