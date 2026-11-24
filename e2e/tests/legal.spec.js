import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

test('the terms changed in the admin show on the preview, and the editor keeps unsaved text across tabs and previews through the link the server gives', async ({ page }) => {
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

    await page.goto('/admin/legal');
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
