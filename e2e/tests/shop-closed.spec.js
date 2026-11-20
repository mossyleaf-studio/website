import { expect, test } from '@playwright/test';

for (const url of ['/fr/', '/en/about', '/fr/no-such-page', '/en/shop', '/fr/cart', '/fr/legal/terms']) {
    test(`an anonymous visitor of ${url} lands on the growing note`, async ({ page }) => {
        await page.goto(url);
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
        await expect(page.locator('html[data-layout="shop"]')).toHaveCount(0);
    });
}

test('the public shop API and the sitemap stay closed too', async ({ request }) => {
    expect((await request.post('/api/shop/en/quote', { data: { lines: [], country: 'FR' } })).status()).toBe(404);
    const sitemap = await (await request.get('/sitemap.xml')).text();
    expect(sitemap).not.toContain('/en/shop');
    expect(sitemap).not.toContain('xhtml:link');
    const robots = await (await request.get('/robots.txt')).text();
    expect(robots).not.toContain('/order/');
});
