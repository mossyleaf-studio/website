import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const IDENTITY = {
    studioName: 'mossyleaf.studio',
    intro: 'Illustrations of little critters among leaves and moss, drawn by hand.',
    metaDescription: 'Illustrations of little critters among leaves and moss, drawn by hand. Prints, stickers and illustrations by mossyleaf.studio.',
    searchTitle: '',
};
const HOME_NOTE = {
    title: 'The website is still growing',
    text: 'New pages are sprouting slowly. Meanwhile, the shop is open on [Etsy](https://www.etsy.com/shop/mossyleafstudio) and new drawings appear on [Instagram](https://www.instagram.com/mossyleaf.studio/).',
};

async function send(request, method, url, data) {
    const response = await request.fetch(url, { method, data });
    expect(response.ok(), `${method} ${url}`).toBe(true);
    return response.json();
}

async function showTheStartingNote(request) {
    await send(request, 'PUT', '/api/admin/texts/identity', IDENTITY);
    await send(request, 'PUT', '/api/admin/texts/home-note', HOME_NOTE);
    await send(request, 'PUT', '/api/admin/texts/fonts/heading', { family: '' });
    await send(request, 'PUT', '/api/admin/texts/fonts/body', { family: '' });
    await send(request, 'DELETE', '/api/admin/logo');
    for (const artwork of await send(request, 'GET', '/api/admin/artworks')) {
        if (artwork.featured) await send(request, 'DELETE', `/api/admin/artworks/${artwork.id}/featured`);
    }
    const publication = await send(request, 'GET', '/api/admin/publication');
    if (publication.page !== null) await send(request, 'POST', '/api/admin/publication', { page: 'note' });
}

test('the growing note looks the same on a phone and on a desktop', async ({ page, browser }) => {
    await signIn(page);
    await showTheStartingNote(page.request);

    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        const visitor = await (await browser.newContext({ viewport })).newPage();
        const hosts = new Set();
        visitor.on('request', (request) => hosts.add(new URL(request.url()).host));
        await visitor.clock.setFixedTime(new Date('2026-06-15T12:00:00Z'));
        await visitor.goto('/');
        await expect(visitor.locator('#site')).toHaveAttribute('data-page', 'note');
        await expect(visitor.locator('.sketchbook__footer')).toContainText('© 2026');
        await expect.soft(visitor).toHaveScreenshot(`note-${size}.png`, { fullPage: true, threshold: 0.1, maxDiffPixels: 100 });
        expect([...hosts]).toEqual([new URL(visitor.url()).host]);
        await visitor.context().close();
    }
});
