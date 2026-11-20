import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

const inDays = (days) => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);

test('a convention added in the admin shows on the preview and as the teaser of the home', async ({ page }) => {
    const name = unique('Fern fair');
    await signIn(page);
    await page.goto('/admin/conventions');
    await expect(page.getByTestId('conventions-upcoming')).toBeVisible();
    await expect(page.getByTestId('conventions-past')).toContainText('Past drawing fair');

    await page.getByTestId('convention-add').click();
    const form = page.getByTestId('convention-editor');
    await form.locator('input[name="name"]').fill(name);
    await form.locator('input[name="place"]').fill('Lyon');
    await form.locator('input[name="startsOn"]').fill(inDays(5));
    await form.locator('input[name="endsOn"]').fill(inDays(6));
    await form.locator('[name="note"]').fill('Come and say hello.');
    const [created] = await Promise.all([page.waitForResponse((response) => response.url().endsWith('/api/admin/conventions') && response.request().method() === 'POST' && response.ok()), form.getByRole('button', { name: 'Save' }).click()]);
    const { id } = await created.json();
    try {
        await expect(page.getByTestId('convention-row').filter({ hasText: name })).toBeVisible();

        await page.goto('/beta/en/conventions');
        await expect(page.getByRole('heading', { name })).toBeVisible();
        await expect(page.getByText('Past drawing fair')).toHaveCount(0);

        await page.goto('/beta/en/');
        await expect(page.locator('[data-next-convention]').getByRole('heading', { name })).toBeVisible();

        await page.goto('/admin/conventions');
        await page.getByTestId('convention-row').filter({ hasText: name }).getByRole('button', { name: `Delete ${name}` }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByTestId('convention-row').filter({ hasText: name })).toHaveCount(0);
    } finally {
        await page.request.delete(`/api/admin/conventions/${id}`);
    }
});
