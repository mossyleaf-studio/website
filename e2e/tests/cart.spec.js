import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const total = (page) => page.getByTestId('summary-total');
const shipping = (page) => page.getByTestId('summary-shipping');

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/beta/en/shop');
    await page.evaluate(() => localStorage.removeItem('mossyleaf.cart'));
});

test('a product is added from its page and the header count follows', async ({ page }) => {
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
});

test('the cart prices the goods, the shipping and the total, and follows quantity, country and threshold', async ({ page }) => {
    await page.goto('/beta/en/shop/leaf-sticker-pack');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await page.goto('/beta/en/cart');
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByTestId('summary-goods')).toContainText('4.00');
    await expect(shipping(page)).toContainText('4.50');
    await expect(total(page)).toContainText('8.50');

    await page.getByRole('button', { name: 'One more' }).click();
    await expect(page.getByTestId('summary-goods')).toContainText('8.00');
    await expect(total(page)).toContainText('12.50');

    const country = page.getByTestId('cart-country');
    await country.selectOption('DE');
    await expect(shipping(page)).toContainText('9.00');
    await expect(total(page)).toContainText('17.00');
    await country.selectOption('JP');
    await expect(shipping(page)).toContainText('15.00');
    await expect(total(page)).toContainText('23.00');

    await country.selectOption('FR');
    await expect(page.getByTestId('missing-for-free')).toBeVisible();
    await page.getByRole('spinbutton', { name: 'Quantity' }).fill('10');
    await page.getByRole('spinbutton', { name: 'Quantity' }).press('Tab');
    await expect(page.getByTestId('summary-goods')).toContainText('40.00');
    await expect(page.getByTestId('missing-for-free')).toHaveCount(0);
    await expect(total(page)).toContainText('40.00');
    await expect(shipping(page)).toContainText('Free');
});

test('the cart survives a reload, flags an unknown product and ends empty', async ({ page }) => {
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
