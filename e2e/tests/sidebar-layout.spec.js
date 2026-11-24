import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const ZONES = { france: { price: 450, freeFrom: 4000, enabled: true }, eu: { price: 900, freeFrom: null, enabled: true }, world: { price: 1500, freeFrom: null, enabled: true } };
const PHONE = { width: 390, height: 844 };

async function setZones(request, enabled) {
    for (const [code, body] of Object.entries(ZONES)) {
        const response = await request.put(`/api/admin/shipping-zones/${code}`, { data: { ...body, enabled } });
        expect(response.ok(), `zone ${code}`).toBe(true);
    }
}

test.afterEach(async ({ page }) => {
    await setZones(page.request, true);
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

    await page.goto('/admin/products');
    await page.getByTestId('product-edit').first().click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Products' })).toHaveClass(/router-link-active/);
    await expect(menu.getByRole('link', { name: 'Products' })).toHaveAttribute('aria-current', 'page');
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
