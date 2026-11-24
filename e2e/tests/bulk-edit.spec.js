import { expect, test } from '@playwright/test';
import { fillForm, MAILPIT, messages, openCheckout, pay, placePaidOrder } from './support/orders.js';
import { signIn, unique } from './support/session.js';

const rowOf = (page, testId, attribute, value) => page.locator(`[data-test="${testId}"][${attribute}="${value}"]`);
const productRow = (page, reference) => rowOf(page, 'product-row', 'data-reference', reference);
const orderRow = (page, reference) => rowOf(page, 'order-row', 'data-reference', reference);
const checkbox = (row) => row.getByTestId('select-row').getByRole('checkbox');
const head = (page) => page.getByTestId('select-all').getByRole('checkbox');
const tick = (row) => row.getByTestId('select-row').click();
const bar = (page) => page.getByTestId('selection-bar');
const toast = (page, text) => page.getByTestId('toast').getByText(text);

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

async function productIds(page) {
    const products = await (await page.request.get('/api/admin/products')).json();
    return Object.fromEntries(products.map((product) => [product.reference, product]));
}

async function placeOrder(page, decline) {
    const known = new Set((await (await page.request.get('/api/admin/orders')).json()).map((order) => order.id));
    await openCheckout(page, [{ sku: 'STK-002', quantity: 1 }]);
    await fillForm(page);
    await pay(page);
    if (decline) await page.getByTestId('fake-sumup-decline').click();
    const orders = await (await page.request.get('/api/admin/orders')).json();
    return orders.find((order) => !known.has(order.id));
}

test('products: hiding and showing several at once, with the head checkbox, the search and a retired one skipped', async ({ page }) => {
    const products = await productIds(page);
    for (const reference of ['STK-002', 'STK-003']) {
        expect((await page.request.put(`/api/admin/products/${products[reference].id}/visibility`)).ok()).toBe(true);
    }
    await page.goto('/admin/products');
    await expect(bar(page)).toHaveCount(0);

    await tick(productRow(page, 'STK-002'));
    await tick(productRow(page, 'STK-003'));
    await expect(page.getByTestId('selection-count')).toHaveText('2 products selected');
    await page.keyboard.press('Escape');
    await expect(bar(page)).toHaveCount(0);
    await expect(checkbox(productRow(page, 'STK-002'))).toHaveAttribute('aria-checked', 'false');

    await tick(productRow(page, 'STK-002'));
    await tick(productRow(page, 'STK-003'));
    await page.getByTestId('bulk-hide').getByRole('button', { name: 'Hide 2 products' }).click();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await expect(toast(page, '2 products hidden from the shop.')).toBeVisible();
    await expect(bar(page)).toHaveCount(0);
    await expect(head(page)).toBeFocused();
    await expect(productRow(page, 'STK-002').getByTestId('product-visibility')).not.toBeChecked();
    await expect(productRow(page, 'STK-003').getByTestId('product-visibility')).not.toBeChecked();

    await page.goto('/beta/en/category/stickers');
    for (const reference of ['STK-002', 'STK-003']) {
        const title = products[reference].title || products[reference].name;
        await expect(page.getByTestId('product-card').filter({ hasText: title }).locator('[data-label]')).toHaveText('Hidden');
    }

    await page.goto('/admin/products');
    await tick(productRow(page, 'STK-002'));
    await tick(productRow(page, 'STK-003'));
    await tick(productRow(page, 'CRD-003'));
    await expect(page.getByTestId('selection-count')).toHaveText('3 products selected');
    await expect(page.getByTestId('bulk-show').getByRole('button')).toHaveText('Show 2 products');
    await page.getByTestId('bulk-show').getByRole('button').click();
    await expect(toast(page, '2 products shown in the shop. 1 skipped: retired by mossytrunk.')).toBeVisible();
    await expect(productRow(page, 'STK-002').getByTestId('product-visibility')).toBeChecked();
    await expect(productRow(page, 'STK-003').getByTestId('product-visibility')).toBeChecked();

    await page.goto('/beta/en/category/stickers');
    await expect(page.getByTestId('product-card').filter({ hasText: products['STK-002'].title || products['STK-002'].name }).locator('[data-label]')).toHaveCount(0);

    await page.goto('/admin/products');
    await page.getByTestId('select-all').click();
    const total = await page.getByTestId('product-row').count();
    await expect(page.getByTestId('selection-count')).toHaveText(`${total} products selected`);
    await page.getByTestId('product-search').fill('sticker');
    const shown = await page.getByTestId('product-row').count();
    expect(shown).toBeLessThan(total);
    await expect(page.getByTestId('selection-count')).toHaveText(`${shown} ${shown === 1 ? 'product' : 'products'} selected`);
    await expect(head(page)).toHaveAttribute('aria-checked', 'true');
    await expect(checkbox(productRow(page, 'CRD-001'))).toHaveCount(0);

    await page.getByTestId('product-search').fill('');
    await expect(page.getByTestId('selection-count')).toHaveText(`${shown} ${shown === 1 ? 'product' : 'products'} selected`);
    await expect(head(page)).toHaveAttribute('aria-checked', 'mixed');
    await page.getByTestId('selection-clear').click();
    await expect(bar(page)).toHaveCount(0);
});

test('orders: shipping the ticked paid orders mails each customer, and test orders are deleted after a confirmation', async ({ page, request }) => {
    const first = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 1 }]);
    const second = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 1 }]);
    const pending = await placeOrder(page, false);
    const failedOne = await placeOrder(page, true);
    const failedTwo = await placeOrder(page, true);
    await request.delete(`${MAILPIT}/api/v1/messages`);

    await page.goto('/admin/orders');
    await tick(orderRow(page, first.reference));
    await tick(orderRow(page, second.reference));
    await expect(bar(page)).toBeVisible();
    await expect(page.getByTestId('selection-count')).toHaveText('2 orders selected');
    await page.getByTestId('bulk-ship').getByRole('button').click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('2 mails will leave');

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toHaveCount(0);
    expect(await messages(request)).toHaveLength(0);
    await expect(page.getByTestId('selection-count')).toHaveText('2 orders selected');

    await page.getByTestId('bulk-ship').getByRole('button').click();
    await dialog.getByRole('button', { name: 'Ship 2 orders' }).click();
    await expect(toast(page, '2 orders marked as shipped.')).toBeVisible();
    await expect(bar(page)).toHaveCount(0);
    await expect(orderRow(page, first.reference)).toHaveCount(0);

    await expect.poll(async () => (await messages(request)).length).toBe(2);
    const subjects = (await messages(request)).map((mail) => mail.Subject).join(' ');
    expect(subjects).toContain(first.reference);
    expect(subjects).toContain(second.reference);

    await page.getByTestId('order-tab-shipped').click();
    await expect(orderRow(page, first.reference)).toBeVisible();
    await expect(orderRow(page, second.reference)).toBeVisible();

    await page.getByTestId('order-tab-other').click();
    await tick(orderRow(page, pending.reference));
    await tick(orderRow(page, failedOne.reference));
    await tick(orderRow(page, failedTwo.reference));
    await expect(page.getByTestId('bulk-ship').getByRole('button')).toBeDisabled();
    await page.getByTestId('bulk-delete').getByRole('button').click();
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(orderRow(page, failedOne.reference)).toBeVisible();

    await expect(page.getByTestId('bulk-delete').getByRole('button')).toHaveText('Delete 2 test orders');
    await page.getByTestId('bulk-delete').getByRole('button').click();
    await dialog.getByRole('button', { name: 'Delete 2 test orders' }).click();
    await expect(toast(page, '2 test orders deleted. 1 skipped: still waiting for payment.')).toBeVisible();
    await expect(orderRow(page, failedOne.reference)).toHaveCount(0);
    await expect(orderRow(page, failedTwo.reference)).toHaveCount(0);
    await expect(orderRow(page, pending.reference)).toBeVisible();
    await expect(checkbox(orderRow(page, pending.reference))).toHaveAttribute('aria-checked', 'false');
    await expect(bar(page)).toHaveCount(0);
});

test('links: several are ticked and deleted after a confirmation, a cancelled one deletes nothing', async ({ page }) => {
    const titles = [unique('Bulk link'), unique('Bulk link'), unique('Bulk link')];
    for (const title of titles) {
        const created = await page.request.post('/api/admin/links', { data: { title, description: 'To delete', url: 'https://example.com/bulk', tape: 'leaf' } });
        expect(created.ok()).toBe(true);
    }
    await page.goto('/admin/links');
    const row = (title) => page.getByTestId('link-row').filter({ hasText: title });
    const before = await page.getByTestId('link-row').count();

    await tick(row(titles[0]));
    await tick(row(titles[1]));
    await expect(page.getByTestId('selection-count')).toHaveText('2 links selected');
    await expect(head(page)).toHaveAttribute('aria-checked', 'mixed');

    const dialog = page.getByRole('alertdialog');
    await page.getByTestId('bulk-delete').getByRole('button').click();
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.getByTestId('link-row')).toHaveCount(before);

    await page.getByTestId('bulk-delete').getByRole('button').click();
    await dialog.getByRole('button', { name: 'Delete 2 links' }).click();
    await expect(toast(page, '2 links deleted.')).toBeVisible();
    await expect(page.getByTestId('link-row')).toHaveCount(before - 2);
    await expect(row(titles[0])).toHaveCount(0);
    await expect(row(titles[2])).toBeVisible();
    await expect(bar(page)).toHaveCount(0);
    await expect(head(page)).toBeFocused();

    let release;
    const held = new Promise((done) => { release = done; });
    await page.route('**/api/admin/links/*', async (route) => {
        if (route.request().method() === 'DELETE') await held;
        await route.fallback();
    });
    await tick(row(titles[2]));
    await page.getByTestId('bulk-delete').getByRole('button').click();
    await dialog.getByRole('button', { name: 'Delete 1 link' }).click();
    await expect(page.getByTestId('selection-count')).toHaveText('Working… 0 of 1');
    await expect(row(titles[2]).getByRole('button', { name: 'Edit' })).toBeDisabled();
    await expect(row(titles[2]).getByRole('button', { name: 'Move up' })).toBeDisabled();
    await expect(page.getByTestId('selection-clear')).toBeDisabled();
    release();
    await expect(row(titles[2])).toHaveCount(0);
    await expect(bar(page)).toHaveCount(0);
});
