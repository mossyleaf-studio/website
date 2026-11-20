import { expect, test } from '@playwright/test';
import { fillForm, MAILPIT, messages, openCheckout, pay, placePaidOrder, stockOf } from './support/orders.js';
import { signIn } from './support/session.js';

const row = (page, reference) => page.locator(`[data-test="order-row"][data-reference="${reference}"]`);

async function openOrder(page, reference, tab = 'toShip') {
    await page.goto('/admin/orders');
    await page.getByTestId(`order-tab-${tab}`).click();
    await row(page, reference).getByRole('link').first().click();
    await expect(page.getByTestId('order-page')).toBeVisible();
}

test.beforeEach(async ({ page, request }) => {
    await request.delete(`${MAILPIT}/api/v1/messages`);
    await signIn(page);
});

test('the admin opens on the orders', async ({ page }) => {
    await page.goto('/admin/');
    await expect(page).toHaveURL(/\/admin\/orders$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Orders' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Orders' }).first()).toBeVisible();
});

test('a paid order waits to be shipped, shipping it mails the tracking number and the public page follows', async ({ page, request }) => {
    const { reference, url } = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 1 }]);
    await request.delete(`${MAILPIT}/api/v1/messages`);

    await page.goto('/admin/orders');
    await expect(row(page, reference)).toBeVisible();
    await expect(row(page, reference)).toContainText('Camille Martin');
    await expect(row(page, reference).getByTestId('order-test')).toBeVisible();

    await row(page, reference).getByRole('link').first().click();
    await expect(page.getByTestId('order-status')).toContainText('Paid');
    await expect(page.getByTestId('order-address')).toContainText('12 rue des Mousses');
    await expect(page.getByTestId('order-address')).toContainText('69001 Lyon');
    await expect(page.getByTestId('order-contact')).toContainText('camille@example.test');
    await expect(page.getByTestId('order-line')).toHaveCount(1);

    await page.getByTestId('order-ship').click();
    await page.getByTestId('order-ship-tracking').fill('LA123456789FR');
    await page.getByTestId('order-ship-submit').click();
    await expect(page.getByTestId('order-status')).toContainText('Shipped');
    await expect(page.getByTestId('order-tracking')).toContainText('LA123456789FR');

    await expect.poll(async () => (await messages(request)).length).toBe(1);
    const [mail] = await messages(request);
    expect(mail.To.map((address) => address.Address)).toEqual(['camille@example.test']);
    expect(mail.Subject).toContain(reference);
    const body = await (await request.get(`${MAILPIT}/api/v1/message/${mail.ID}`)).json();
    expect(body.Text).toContain('LA123456789FR');

    await page.goto('/admin/orders');
    await page.getByTestId('order-tab-shipped').click();
    await expect(row(page, reference)).toBeVisible();
    await page.getByTestId('order-tab-toShip').click();
    await expect(row(page, reference)).toHaveCount(0);

    await page.goto(url);
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'shipped');
    await expect(page.getByTestId('order-tracking-number')).toHaveText('LA123456789FR');
});

test('cancelling a paid order puts its units back and the public page says cancelled', async ({ page }) => {
    const before = await stockOf(page, 'STK-002');
    const { reference, url } = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 2 }]);
    expect(await stockOf(page, 'STK-002')).toBe(before - 2);

    await openOrder(page, reference);
    await page.getByTestId('order-cancel').getByRole('button').click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Cancel the order' }).click();
    await expect(page.getByTestId('order-status')).toContainText('Cancelled');
    await expect(page.getByTestId('order-cancel')).toHaveCount(0);
    await expect(page.getByTestId('order-ship')).toHaveCount(0);

    expect(await stockOf(page, 'STK-002')).toBe(before);
    await page.goto(url);
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'cancelled');
});

test('opening the list settles an order whose webhook never came and whose customer never came back', async ({ page }) => {
    await openCheckout(page, [{ sku: 'STK-002', quantity: 1 }]);
    await fillForm(page);
    await pay(page);
    const checkout = new URL(page.url()).pathname;
    await page.getByTestId('fake-sumup-back').click();
    const reference = (await page.getByTestId('order-reference').textContent()).match(/ML-[0-9A-Z]{4}-[0-9A-Z]{4}/)[0];
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'pending');

    const settled = await page.request.post(`${checkout}?webhook=0`, { form: { outcome: 'pay' }, maxRedirects: 0 });
    expect(settled.status()).toBe(302);
    expect((await page.request.post(`/_fake/orders/${reference}/expire`)).status()).toBe(204);

    await page.goto('/admin/orders');
    await expect(row(page, reference)).toBeVisible();
    await expect(row(page, reference)).toContainText('Paid');
});

test('a test order is deleted and leaves the list', async ({ page }) => {
    const before = await stockOf(page, 'STK-002');
    const { reference } = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 1 }]);

    await openOrder(page, reference);
    await page.getByTestId('order-delete').getByRole('button').click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/orders$/);
    await expect(row(page, reference)).toHaveCount(0);
    await page.getByTestId('order-tab-other').click();
    await expect(row(page, reference)).toHaveCount(0);
    expect(await stockOf(page, 'STK-002')).toBe(before);
});
