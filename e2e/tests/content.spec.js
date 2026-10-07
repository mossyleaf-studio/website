import { expect, test } from '@playwright/test';
import { PNG, signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

async function saved(page, section, click) {
    await Promise.all([page.waitForResponse((response) => response.url().endsWith(`/api/admin/texts/${section}`) && response.ok()), click]);
}

test('the introduction and the home note are edited from the texts page', async ({ page }) => {
    const intro = unique('Tiny frogs and ferns');
    const identity = page.getByTestId('section-identity');
    await identity.getByLabel('Introduction').fill(intro);
    await saved(page, 'identity', identity.getByRole('button', { name: 'Save' }).click());
    await expect(page.getByTestId('toast').first()).toContainText('Saved.');

    const note = page.getByTestId('section-home-note');
    await note.getByLabel('Text').fill('Come and see the [shop](https://www.etsy.com/shop/mossyleafstudio).');
    await saved(page, 'home-note', note.getByRole('button', { name: 'Save' }).click());

    await page.goto('/');
    await expect(page.getByText(intro)).toBeVisible();
    await expect(page.getByRole('link', { name: 'shop' })).toHaveAttribute('href', 'https://www.etsy.com/shop/mossyleafstudio');
});

test('an empty title is refused next to the field', async ({ page }) => {
    const about = page.getByTestId('section-about');
    await about.getByLabel('Title').fill('   ');
    await about.getByRole('button', { name: 'Save' }).click();
    await expect(about.getByRole('alert')).toBeVisible();
});

test('a link is added, moved to the top and shown on the full page', async ({ page }) => {
    const title = unique('Ko-fi');
    await page.getByRole('link', { name: 'Links' }).click();
    await page.getByRole('button', { name: 'Add a link' }).click();

    const editor = page.getByTestId('link-editor');
    await editor.getByLabel('Title').fill(title);
    await editor.getByLabel('Description').fill('Buy the studio a coffee');
    await editor.getByLabel('Address').fill('https://ko-fi.com/mossyleaf');
    await editor.getByRole('radio', { name: 'Blue dots' }).click();
    await editor.getByRole('button', { name: 'Save' }).click();

    const row = page.getByTestId('link-row').filter({ hasText: title });
    await expect(row).toBeVisible();
    while ((await page.getByTestId('link-row').first().textContent()).includes(title) === false) {
        await row.getByRole('button', { name: 'Move up' }).click();
        await page.waitForLoadState('networkidle');
    }

    await page.goto('/beta/');
    await expect(page.getByRole('navigation').getByRole('listitem').first()).toContainText(title);

    await page.goto('/admin/links');
    await page.getByTestId('link-row').filter({ hasText: title }).getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByTestId('link-row').filter({ hasText: title })).toHaveCount(0);
});

test('an http address is refused for a link', async ({ page }) => {
    await page.goto('/admin/links');
    await page.getByRole('button', { name: 'Add a link' }).click();
    const editor = page.getByTestId('link-editor');
    await editor.getByLabel('Title').fill('Old blog');
    await editor.getByLabel('Description').fill('Not secure');
    await editor.getByLabel('Address').fill('http://example.com');
    await editor.getByRole('button', { name: 'Save' }).click();
    await expect(editor.getByRole('alert')).toContainText('https');
});

test('a logo is added to the header, then removed', async ({ page }) => {
    const logo = page.getByTestId('section-logo');
    await logo.locator('input[type="file"]').setInputFiles(PNG);
    await expect(logo.getByRole('img', { name: 'Current logo' })).toBeVisible();

    await page.goto('/');
    await expect(page.locator('.studio-header__logo')).toBeVisible();

    await page.goto('/admin/texts');
    await logo.getByRole('button', { name: 'Remove the logo' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(logo.getByText('No logo yet')).toBeVisible();

    await page.goto('/');
    await expect(page.locator('.studio-header__logo')).toHaveCount(0);
});

test('an image is uploaded, described, featured, then deleted', async ({ page }) => {
    await page.goto('/admin/images');
    await page.locator('input[type="file"]').setInputFiles(PNG);
    const card = page.getByTestId('artwork-card').first();
    await expect(card).toBeVisible();

    await card.getByLabel(/Description/).fill('A fern unrolling');
    await card.getByRole('button', { name: 'Save the description' }).click();
    await card.getByRole('button', { name: 'Feature' }).click();
    await expect(card.getByText('Featured', { exact: true })).toBeVisible();

    await page.goto('/');
    await expect(page.getByRole('img', { name: 'A fern unrolling' })).toBeVisible();

    await page.goto('/admin/images');
    await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByTestId('artwork-card')).toHaveCount(0);
});
