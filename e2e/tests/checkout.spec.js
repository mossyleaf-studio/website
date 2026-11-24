import { expect, test } from '@playwright/test';
import { fillForm, MAILPIT, messages, openCheckout, pay, REFERENCE, stockOf } from './support/orders.js';
import { signIn } from './support/session.js';

test.beforeEach(async ({ page, request }) => {
    await request.delete(`${MAILPIT}/api/v1/messages`);
    await signIn(page);
});

test('a paid order says so, is mailed to the customer and the studio, takes the stock and empties the cart', async ({ page, request }) => {
    const before = await stockOf(page, 'STK-002');
    await openCheckout(page, [{ sku: 'STK-002', quantity: 2 }]);
    await fillForm(page);
    await pay(page);
    await page.getByTestId('fake-sumup-pay').click();

    await expect(page).toHaveURL(/\/beta\/en\/order\//);
    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'paid');
    await expect(page.getByTestId('order-test')).toBeVisible();
    expect(await page.getByTestId('order-test').evaluate((label) => getComputedStyle(label).color)).toBe('rgb(88, 62, 47)');
    const reference = (await page.getByTestId('order-reference').textContent()).match(REFERENCE)[0];

    await expect.poll(async () => (await messages(request)).length).toBe(2);
    const mails = await messages(request);
    const confirmation = mails.find((mail) => mail.To.some((address) => address.Address === 'camille@example.test'));
    const notice = mails.find((mail) => mail.To.some((address) => address.Address === 'studio@mossyleaf.test'));
    expect(confirmation.Subject).toContain('[TEST]');
    expect(confirmation.Subject).toContain(reference);
    expect(notice.Subject).toContain(reference);
    expect(notice.ReplyTo.map((address) => address.Address)).toEqual(['camille@example.test']);

    expect(await stockOf(page, 'STK-002')).toBe(before - 2);
    await page.goto('/beta/en/cart');
    await expect(page.getByTestId('cart-empty')).toBeVisible();
});

test('a declined payment says nothing was charged, keeps the cart and sends no mail', async ({ page, request }) => {
    const before = await stockOf(page, 'STK-002');
    await openCheckout(page, [{ sku: 'STK-002', quantity: 1 }]);
    await fillForm(page);
    await pay(page);
    await page.getByTestId('fake-sumup-decline').click();

    await expect(page.getByTestId('order-status')).toHaveAttribute('data-state', 'failed');
    await expect(page.getByTestId('order-state')).toContainText('did not go through');
    expect(await messages(request)).toHaveLength(0);
    expect(await stockOf(page, 'STK-002')).toBe(before);
    await page.getByTestId('order-cart').click();
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
});

test('a second buyer of the last unit is told the cart changed', async ({ page, browser }) => {
    const units = await stockOf(page, 'STK-003-B');
    await openCheckout(page, [{ sku: 'STK-003-B', quantity: units }]);
    await fillForm(page);

    const other = await browser.newContext();
    const rival = await other.newPage();
    await signIn(rival);
    await openCheckout(rival, [{ sku: 'STK-003-B', quantity: 1 }]);
    await fillForm(rival);
    await pay(rival);

    await page.getByRole('checkbox').check();
    await page.getByTestId('checkout-pay').click();
    await expect(page.getByTestId('checkout-outdated')).toBeVisible();
    await expect(page).toHaveURL(/\/beta\/en\/checkout$/);

    await rival.getByTestId('fake-sumup-decline').click();
    await expect(rival.getByTestId('order-status')).toHaveAttribute('data-state', 'failed');
    await other.close();
});
