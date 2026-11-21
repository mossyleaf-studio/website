import { expect, test } from '@playwright/test';
import { placePaidOrder } from './support/orders.js';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };
const PHONE = { width: 390, height: 844 };
const ALL_OK = { ready: true, items: ['identity', 'payment', 'mail', 'shipping', 'products', 'terms', 'legalNotice', 'privacy', 'shippingReturns', 'photos', 'mossytrunk', 'testOrders'].map((key) => ({ key, state: 'ok', count: 0 })) };

async function setZones(request, enabled) {
    for (const [code, body] of Object.entries(ZONES)) {
        const response = await request.put(`/api/admin/shipping-zones/${code}`, { data: { ...body, enabled } });
        expect(response.ok(), `zone ${code}`).toBe(true);
    }
}

test.afterEach(async ({ page }) => {
    await setZones(page.request, true);
    await page.request.post('/api/admin/publication', { data: { page: 'note' } });
});

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the sidebar shows the two groups and marks the current section, also on a detail page', async ({ page }) => {
    const menu = page.getByRole('navigation', { name: 'Admin sections' });
    await page.goto('/admin/texts');
    await expect(menu.getByText('Shop', { exact: true })).toBeVisible();
    await expect(menu.getByText('Site', { exact: true })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Texts' })).toHaveClass(/router-link-active/);
    await expect(menu.getByRole('link', { name: 'Orders' })).not.toHaveClass(/router-link-active/);

    const { url } = await placePaidOrder(page, [{ sku: 'STK-002', quantity: 1 }]);
    expect(url).toBeTruthy();
    await page.goto('/admin/orders');
    await page.getByTestId('order-tab-toShip').click();
    await page.getByTestId('order-row').first().getByRole('link').first().click();
    await expect(page.getByTestId('order-page')).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Orders' })).toHaveClass(/router-link-active/);
    await expect(menu.getByRole('link', { name: 'Orders' })).toHaveAttribute('aria-current', 'page');

    await page.goto('/admin/products');
    await page.getByTestId('product-edit').first().click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Products' })).toHaveClass(/router-link-active/);
    await expect(menu.getByRole('link', { name: 'Products' })).toHaveAttribute('aria-current', 'page');
});

test('the to-do column lists what is not ok with the blocker first, and its link leads to the fix', async ({ page }) => {
    await setZones(page.request, false);
    await page.goto('/admin/texts');

    const list = page.getByTestId('publish-readiness');
    const items = list.locator('[data-test^="readiness-"]');
    await expect(list.getByTestId('publish-ready-count')).toBeVisible();
    await expect(items.first()).toHaveAttribute('data-state', 'blocker');
    await expect(items.first()).toHaveAttribute('data-test', 'readiness-shipping');
    await expect(list.getByTestId('readiness-products')).toHaveCount(0);

    const bar = page.getByTestId('publish-bar');
    await expect(bar.getByTestId('publish-action')).toHaveAttribute('aria-disabled', 'true');
    await expect(list.getByTestId('publish-blocked')).toBeVisible();
    await expect(list).toHaveRole('complementary');
    expect(await page.locator('main h1, main [data-test="publish-readiness"]').evaluateAll((nodes) => nodes.map((node) => node.tagName))).toEqual(['H1', 'ASIDE']);

    await items.first().getByRole('link').click();
    await expect(page).toHaveURL(/\/admin\/settings$/);
});

test('once the shop is open and nothing is to check there is no column', async ({ page }) => {
    await page.goto('/admin/texts');
    await expect(page.getByTestId('publish-readiness')).toBeVisible();
    await page.route('**/api/admin/shop/readiness', (route) => route.fulfill({ json: ALL_OK }));

    const bar = page.getByTestId('publish-bar');
    await bar.getByRole('button', { name: 'Publish' }).click();
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/publication') && response.request().method() === 'POST' && response.ok()),
        page.getByRole('alertdialog').getByRole('button', { name: 'Publish' }).click(),
    ]);

    await expect(page.getByTestId('publish-state')).toHaveAttribute('data-open', 'true');
    await expect(page.getByTestId('publish-readiness')).toHaveCount(0);
});

test('at a phone width the list is folded under the publication row and opens on demand', async ({ page }) => {
    await page.setViewportSize(PHONE);
    await setZones(page.request, false);
    await page.goto('/admin/texts');

    const list = page.getByTestId('publish-readiness');
    const toggle = list.getByRole('button', { expanded: false });
    await expect(toggle).toBeVisible();
    await expect(list.getByTestId('publish-ready-count')).toBeVisible();
    await expect(list.getByTestId('publish-blocking')).toBeVisible();
    await expect(list.getByTestId('readiness-shipping')).toBeHidden();

    await toggle.click();
    await expect(list.getByRole('button', { expanded: true })).toBeVisible();
    await expect(list.getByTestId('readiness-shipping')).toBeVisible();
});
