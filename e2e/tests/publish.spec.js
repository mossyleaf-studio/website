import { expect, test } from '@playwright/test';
import { signIn, unique } from './support/session.js';

async function publish(page, button) {
    await page.getByTestId('publish-bar').getByRole('button', { name: button }).click();
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/publication') && response.request().method() === 'POST' && response.ok()),
        page.getByRole('alertdialog').getByRole('button', { name: button }).click(),
    ]);
}

async function editIntro(page, intro) {
    const identity = page.getByTestId('section-identity');
    await identity.getByLabel('Introduction').fill(intro);
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/texts/identity') && response.ok()),
        identity.getByRole('button', { name: 'Save' }).click(),
    ]);
}

test('drafts stay private until they are published', async ({ page, browser }) => {
    const published = unique('Moss frogs');
    const draft = unique('Fern snails');
    const visitor = await (await browser.newContext()).newPage();

    await signIn(page);
    await editIntro(page, published);
    await expect(page.getByTestId('publish-bar')).toContainText('not visible to visitors yet');

    await publish(page, 'Publish');
    await expect(page.getByTestId('publish-bar')).toContainText('Full page published');
    await expect(page.getByTestId('publish-bar')).toContainText('Visitors see the same content as the draft.');

    await visitor.goto('/');
    await expect(visitor.getByText(published)).toBeVisible();
    await expect(visitor.getByRole('heading', { name: 'About the studio' })).toBeVisible();

    await editIntro(page, draft);
    await visitor.reload();
    await expect(visitor.getByText(published)).toBeVisible();
    await expect(visitor.getByText(draft)).toHaveCount(0);

    await page.goto('/beta/');
    await expect(page.getByRole('complementary', { name: 'Draft preview' })).toBeVisible();
    await expect(page.getByText(draft)).toBeVisible();

    await page.goto('/admin/texts');
    await publish(page, 'Show the growing note');
    await visitor.reload();
    await expect(visitor.getByRole('heading', { name: 'The website is still growing' })).toBeVisible();
    await expect(visitor.getByText(draft)).toBeVisible();
});
