import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

test('the terms changed in the admin show on the preview and in the footer, and an emptied page leaves the footer', async ({ page }) => {
    const heading = unique('Fresh terms');
    await signIn(page);
    await page.goto('/admin/legal');
    await page.getByTestId('legal-tab-terms').click();
    const form = page.getByTestId('legal-form');
    await form.locator('[name="text"]').fill(`## ${heading}\nFirst line.\n\n- one\n- two`);
    await Promise.all([page.waitForResponse((response) => response.url().endsWith('/api/admin/legal-pages/terms') && response.ok()), form.getByRole('button', { name: 'Save' }).click()]);

    await page.goto('/beta/en/legal/terms');
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
    await expect(page.locator('[data-blocks] ul li')).toHaveCount(2);
    await expect(page.locator('[data-legal]').getByRole('link', { name: 'Terms of sale' })).toBeVisible();

    const original = await (await page.request.get('/api/admin/legal-pages')).json();
    const shipping = original.find((item) => 'shipping-returns' === item.kind);
    try {
        await page.request.put('/api/admin/legal-pages/shipping-returns', { data: { text: '', textFr: '' } });
        await page.goto('/beta/en/legal/terms');
        await expect(page.locator('[data-legal]').getByRole('link', { name: 'Terms of sale' })).toBeVisible();
        await expect(page.locator('[data-legal]').getByRole('link', { name: 'Shipping and returns' })).toHaveCount(0);
    } finally {
        await page.request.put('/api/admin/legal-pages/shipping-returns', { data: { text: shipping.text, textFr: shipping.textFr } });
    }
});

test('the legal editor keeps unsaved text across tabs, tells what the shop shows and previews through the link the server gives', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/legal');
    const form = page.getByTestId('legal-form');
    const privacy = page.getByTestId('legal-tab-privacy');
    await privacy.click();
    await expect(privacy).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('legal-tab-terms')).toHaveAttribute('aria-pressed', 'false');
    await expect(form.locator('[name="text"]')).not.toHaveValue('');

    await form.locator('[name="text"]').fill('');
    await form.locator('[name="textFr"]').fill('Brouillon en cours.');
    await expect(page.getByTestId('legal-hidden')).toHaveCount(0);

    await page.getByTestId('legal-tab-terms').click();
    await expect(form.locator('[name="textFr"]')).not.toHaveValue('Brouillon en cours.');
    await privacy.click();
    await expect(form.locator('[name="text"]')).toHaveValue('');
    await expect(form.locator('[name="textFr"]')).toHaveValue('Brouillon en cours.');
    await expect(page.getByTestId('legal-preview')).toHaveAttribute('href', '/beta/en/legal/privacy');
});
