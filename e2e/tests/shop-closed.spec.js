import { expect, test } from '@playwright/test';

for (const url of ['/fr/', '/en/about', '/fr/no-such-page']) {
    test(`an anonymous visitor of ${url} lands on the growing note`, async ({ page }) => {
        await page.goto(url);
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
        await expect(page.locator('html[data-layout="shop"]')).toHaveCount(0);
    });
}
