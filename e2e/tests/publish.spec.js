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
    await identity.getByLabel('Introduction (English)').fill(intro);
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/texts/identity') && response.ok()),
        identity.getByRole('button', { name: 'Save' }).click(),
    ]);
}

test('drafts stay private until they are published, and the shop closes back on the note', async ({ page, browser }) => {
    const published = unique('Moss frogs');
    const draft = unique('Fern snails');
    const visitor = await (await browser.newContext()).newPage();
    const bar = page.getByTestId('publish-bar');

    await signIn(page);
    try {
        await page.goto('/admin/texts');
        await editIntro(page, published);
        await expect(bar).toContainText('not visible to visitors yet');

        await publish(page, 'Publish');
        await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'true');
        await expect(bar).toContainText('The shop is open. Last published');
        await expect(bar).toContainText('Visitors see the same content as the draft.');

        await visitor.goto('/');
        await expect(visitor).toHaveURL(/\/en\/$/);
        await expect(visitor.getByText(published).first()).toBeVisible();

        await editIntro(page, draft);
        await visitor.reload();
        await expect(visitor.getByText(published).first()).toBeVisible();
        await expect(visitor.getByText(draft)).toHaveCount(0);

        await page.goto('/beta/en/');
        await expect(page.getByRole('complementary', { name: 'Draft preview' })).toBeVisible();
        await expect(page.getByText(draft).first()).toBeVisible();

        await page.goto('/admin/texts');
        await publish(page, 'Show the growing note');
        await expect(bar.getByTestId('publish-state')).toHaveAttribute('data-open', 'false');
        await visitor.goto('/');
        await expect(visitor).toHaveURL(/\/$/);
        await expect(visitor.getByRole('heading', { name: 'The website is still growing' })).toBeVisible();
        await expect(visitor.getByText(draft)).toBeVisible();
    } finally {
        await page.request.post('/api/admin/publication', { data: { page: 'note' } });
        await visitor.context().close();
    }
});
