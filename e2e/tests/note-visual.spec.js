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
        await visitor.clock.setFixedTime(new Date('2026-06-15T12:00:00Z'));
        await visitor.goto('/');
        await expect(visitor.locator('#site')).toHaveAttribute('data-page', 'note');
        await expect(visitor.locator('.sketchbook__footer')).toContainText('© 2026');
        await expect.soft(visitor).toHaveScreenshot(`note-${size}.png`, { fullPage: true, threshold: 0.1, maxDiffPixels: 100 });
        await visitor.context().close();
    }
});

const INK = '88 62 47';
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
        const style = (selector) => getComputedStyle(document.querySelector(selector));
        return {
            band: rgb(style(scope.band).backgroundColor),
            wordmark: [rgb(style(scope.wordmark).color), style(scope.wordmark).fontFamily, style(scope.wordmark).fontWeight],
            body: [style('body').fontFamily, style('body').fontWeight],
            theme: document.querySelector('meta[name="theme-color"]').content,
            loaded: [...document.fonts].filter((face) => face.status === 'loaded').map((face) => face.family.replaceAll('"', '')),
        };
    }, scope);

const sheet = (seen) => {
    expect(seen.band.split(' ').every((channel, index) => Math.abs(channel - BAND.split(' ')[index]) <= 3), seen.band).toBe(true);
    expect(seen.theme).toBe('#cbd3a0');
    expect(seen.wordmark).toEqual([INK, expect.stringMatching(/^"?Quicksand Variable"?, "?Quicksand"?, sans-serif$/), '300']);
    expect(seen.body).toEqual([expect.stringMatching(/^"?Huninn"?, sans-serif$/), '400']);
};

test('the growing note wears the studio sheet, with and without JavaScript', async ({ page, browser }) => {
    await signIn(page);
    await showTheStartingNote(page.request);

    const visitor = await (await browser.newContext()).newPage();
    const hosts = new Set();
    const fonts = [];
    visitor.on('request', (request) => {
        hosts.add(new URL(request.url()).host);
        if (/\/huninn-/.test(request.url())) fonts.push(new URL(request.url()).pathname.split('/').pop());
    });
    await visitor.goto('/');
    const scripted = await look(visitor, { band: '.studio-header', wordmark: '.studio-header__wordmark' });
    sheet(scripted);
    expect(scripted.loaded).toEqual(expect.arrayContaining(['Huninn', 'Quicksand Variable']));
    expect(scripted.loaded).not.toContain('Kalam');
    expect([...hosts]).toEqual([new URL(visitor.url()).host]);
    expect(fonts[0]).toMatch(/^huninn-latin-400-normal/);
    expect(fonts.filter((font) => !/^huninn-latin(-ext)?-400-normal/.test(font))).toEqual([]);
    expect(await visitor.context().cookies()).toEqual([]);
    await visitor.context().close();

    const crawler = await (await browser.newContext({ javaScriptEnabled: false })).newPage();
    await crawler.goto('/');
    sheet(await look(crawler, { band: '.prerender__band', wordmark: '.prerender__wordmark' }));
    await crawler.context().close();
});
