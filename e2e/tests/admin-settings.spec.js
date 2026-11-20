import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test.afterEach(async ({ page }) => {
    for (const [code, body] of Object.entries(ZONES)) {
        await page.request.put(`/api/admin/shipping-zones/${code}`, { data: body });
    }
});

const save = (page, code) => Promise.all([page.waitForResponse((response) => response.url().endsWith(`/shipping-zones/${code}`) && response.request().method() === 'PUT' && response.ok()), page.getByTestId(`zone-${code}-save`).click()]);

test('a new price is live in the cart, and a zone turned off leaves the country list', async ({ page }) => {
    await page.goto('/admin/settings');
    await page.getByTestId('zone-france-price').fill('6.20');
    await save(page, 'france');

    await page.goto('/beta/en/shop/leaf-sticker-pack');
    await page.evaluate(() => localStorage.setItem('mossyleaf.cart', JSON.stringify({ lines: [{ sku: 'STK-002', quantity: 1 }], country: 'FR' })));
    await page.goto('/beta/en/cart');
    await expect(page.getByTestId('summary-shipping')).toContainText('6.20');

    await page.goto('/admin/settings');
    await page.getByTestId('zone-world-enabled').click();
    await save(page, 'world');

    await page.goto('/beta/en/cart');
    await expect(page.getByTestId('cart-country').locator('option[value="DE"]')).toHaveCount(1);
    await expect(page.getByTestId('cart-country').locator('option[value="JP"]')).toHaveCount(0);
});
