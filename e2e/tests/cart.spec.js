import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };

const total = (page) => page.getByTestId('summary-total');
const shipping = (page) => page.getByTestId('summary-shipping');
const save = (page, code) => Promise.all([page.waitForResponse((response) => response.url().endsWith(`/shipping-zones/${code}`) && response.request().method() === 'PUT' && response.ok()), page.getByTestId(`zone-${code}-save`).click()]);

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/beta/en/shop');
    await page.evaluate(() => localStorage.removeItem('mossyleaf.cart'));
});

test('a product added from its page is priced in the cart, which follows quantity, country and threshold and survives a reload', async ({ page }) => {
    await page.goto('/beta/en/shop/leaf-sticker-pack');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByTestId('cart-added')).toBeVisible();
    await expect(page).toHaveURL(/\/shop\/leaf-sticker-pack$/);
    await expect(page.getByTestId('cart-count')).toHaveText('1');
    await page.getByRole('button', { name: 'One more' }).click();
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByTestId('cart-count')).toHaveText('3');
    await page.getByTestId('see-cart').click();
    await expect(page).toHaveURL(/\/beta\/en\/cart$/);
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-link')).toHaveAttribute('aria-current', 'page');
    await expect(page.getByTestId('summary-goods')).toContainText('12.00');
    await expect(shipping(page)).toContainText('4.50');
    await expect(total(page)).toContainText('16.50');

    await page.getByRole('button', { name: 'One more' }).click();
    await expect(page.getByTestId('summary-goods')).toContainText('16.00');
    await expect(total(page)).toContainText('20.50');

    const country = page.getByTestId('cart-country');
    await country.selectOption('DE');
    await expect(shipping(page)).toContainText('9.00');
    await expect(total(page)).toContainText('25.00');
    await country.selectOption('JP');
    await expect(shipping(page)).toContainText('15.00');
    await expect(total(page)).toContainText('31.00');

    await country.selectOption('FR');
    await expect(page.getByTestId('missing-for-free')).toBeVisible();
    await page.getByRole('spinbutton', { name: 'Quantity' }).fill('10');
    await page.getByRole('spinbutton', { name: 'Quantity' }).press('Tab');
    await expect(page.getByTestId('summary-goods')).toContainText('40.00');
    await expect(page.getByTestId('missing-for-free')).toHaveCount(0);
    await expect(total(page)).toContainText('40.00');
    await expect(shipping(page)).toContainText('Free');

    await page.reload();
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(total(page)).toContainText('40.00');

    await page.goto('/beta/en/shop');
    await page.evaluate(() => localStorage.setItem('mossyleaf.cart', JSON.stringify({ lines: [{ sku: 'STK-002', quantity: 1 }, { sku: 'GONE-001', quantity: 1 }], country: 'FR' })));
    await page.goto('/beta/en/cart');
    await expect(page.getByTestId('cart-issues')).toBeVisible();
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await page.reload();
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('cart-units')).toBeVisible();

    await page.getByTestId('remove-line').click();
    await expect(page.getByTestId('cart-empty')).toBeVisible();
    await expect(page.getByTestId('cart-count')).toHaveText('0');
});

test('a new zone price is live in the cart, and a zone turned off leaves the country list', async ({ page }) => {
    try {
        await page.goto('/admin/settings');
        await page.getByTestId('zone-france-price').fill('6.20');
        await save(page, 'france');

        await page.goto('/beta/en/shop/leaf-sticker-pack');
        await page.evaluate(() => localStorage.setItem('mossyleaf.cart', JSON.stringify({ lines: [{ sku: 'STK-002', quantity: 1 }], country: 'FR' })));
        await page.goto('/beta/en/cart');
        await expect(shipping(page)).toContainText('6.20');

        await page.goto('/admin/settings');
        await page.getByTestId('zone-world-enabled').click();
        await save(page, 'world');

        await page.goto('/beta/en/cart');
        await expect(page.getByTestId('cart-country').locator('option[value="DE"]')).toHaveCount(1);
        await expect(page.getByTestId('cart-country').locator('option[value="JP"]')).toHaveCount(0);
    } finally {
        for (const [code, body] of Object.entries(ZONES)) await page.request.put(`/api/admin/shipping-zones/${code}`, { data: body });
    }
});
