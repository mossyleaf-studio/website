import { expect } from '@playwright/test';

export const MAILPIT = process.env.MAILPIT_URL ?? 'http://mailpit:8025';
export const REFERENCE = /ML-[0-9A-Z]{4}-[0-9A-Z]{4}/;
const CART = 'mossyleaf.cart';

export const messages = async (request) => (await (await request.get(`${MAILPIT}/api/v1/messages`)).json()).messages;

export const stockOf = async (page, sku) => {
    const products = await (await page.request.get('/api/admin/products')).json();
    return products.flatMap((product) => product.variants).find((variant) => variant.sku === sku).stock;
};

export async function fillCart(page, lines) {
    await page.goto('/beta/en/shop');
    await page.evaluate(([key, value]) => localStorage.setItem(key, JSON.stringify({ lines: value, country: 'FR' })), [CART, lines]);
}

export async function fillForm(page) {
    await page.getByLabel('Email', { exact: true }).fill('camille@example.test');
    await page.getByLabel('Full name').fill('Camille Martin');
    await page.getByLabel('Address', { exact: true }).fill('12 rue des Mousses');
    await page.getByLabel('Postal code').fill('69001');
    await page.getByLabel('City').fill('Lyon');
}

export async function openCheckout(page, lines) {
    await fillCart(page, lines);
    await page.goto('/beta/en/checkout');
    await expect(page.getByTestId('checkout-pay')).toBeEnabled();
}

export async function pay(page) {
    await page.getByRole('checkbox').check();
    await page.getByTestId('checkout-pay').click();
    await page.waitForURL(/\/_fake\/sumup\//);
}

export async function placePaidOrder(page, lines) {
    await openCheckout(page, lines);
    await fillForm(page);
    await pay(page);
    await page.getByTestId('fake-sumup-pay').click();
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
    const reference = (await page.getByTestId('order-reference').textContent()).match(REFERENCE)[0];
    return { reference, url: page.url() };
}
