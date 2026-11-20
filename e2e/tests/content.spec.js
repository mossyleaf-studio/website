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
    await identity.getByLabel('Introduction (English)').fill(intro);
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
    await about.getByLabel('Title (English)').fill('   ');
    await about.getByRole('button', { name: 'Save' }).click();
    await expect(about.getByRole('alert')).toBeVisible();
});

test('a French text is typed next to the English one, and its absence is flagged', async ({ page }) => {
    const french = unique('Dessins récents');
    const gallery = page.getByTestId('section-gallery');
    const title = gallery.getByLabel('Title above the drawings (Français)');
    await expect(title).toHaveAttribute('lang', 'fr');
    await expect(gallery.getByLabel('Title above the drawings (English)')).toHaveAttribute('lang', 'en');
    await expect(gallery.getByTestId('missing-translation')).toHaveText('Empty: French pages show the English text.');

    await title.fill(french);
    await expect(gallery.getByTestId('missing-translation')).toHaveCount(0);
    await saved(page, 'gallery', gallery.getByRole('button', { name: 'Save' }).click());
    await page.reload();
    await expect(title).toHaveValue(french);

    await page.goto('/beta/');
    await expect(page.getByText(french)).toHaveCount(0);

    await page.goto('/admin/texts');
    await title.fill('');
    await saved(page, 'gallery', gallery.getByRole('button', { name: 'Save' }).click());
    await expect(gallery.getByTestId('missing-translation')).toBeVisible();
});

test('the announcement and the price note of the shop are written in both languages', async ({ page }) => {
    const notes = page.getByTestId('section-shop-notes');
    await expect(notes.getByTestId('missing-translation')).toHaveCount(0);
    await notes.getByLabel('Announcement bar (Français)').fill('Livraison offerte dès 40 €');
    await expect(notes.getByTestId('missing-translation')).toHaveText('Empty: English pages show the French text.');
    await notes.getByLabel('Announcement bar (English)').fill('Free shipping from 40 €');
    await saved(page, 'shop-notes', notes.getByRole('button', { name: 'Save' }).click());
    await page.reload();
    await expect(notes.getByLabel('Announcement bar (Français)')).toHaveValue('Livraison offerte dès 40 €');
    await expect(notes.getByLabel('Announcement bar (English)')).toHaveValue('Free shipping from 40 €');

    await notes.getByLabel('Announcement bar (Français)').fill('');
    await notes.getByLabel('Announcement bar (English)').fill('');
    await saved(page, 'shop-notes', notes.getByRole('button', { name: 'Save' }).click());
});

test('an empty English search title is not said to borrow the French one', async ({ page }) => {
    const identity = page.getByTestId('section-identity');
    await expect(identity.getByLabel('Title in search results (English)')).toHaveValue('');
    await identity.getByLabel('Title in search results (Français)').fill('Illustrations de petites bêtes');
    await expect(identity.getByLabel('Title in search results (Français)')).toHaveValue('Illustrations de petites bêtes');
    await expect(identity.getByTestId('missing-translation').filter({ hasText: 'English pages show the French text' })).toHaveCount(0);
});

test('any Google font is typed on the texts page and used by the draft', async ({ page }) => {
    const fonts = page.getByTestId('section-fonts');
    await fonts.getByLabel('Titles').fill('Caveat');
    await fonts.getByLabel('Text').fill('Patrick Hand');
    await expect(fonts.getByRole('figure').locator('p').first()).toHaveCSS('font-family', /Caveat/);
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/texts/fonts/body') && response.ok()),
        fonts.getByRole('button', { name: 'Save' }).click(),
    ]);
    await expect(page.getByTestId('toast').first()).toContainText('Saved.');

    await page.goto('/beta/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveCSS('font-family', /Caveat/);
    await expect(page.locator('body')).toHaveCSS('font-family', /Patrick Hand/);

    await page.goto('/admin/texts');
    await fonts.getByLabel('Titles').fill('Nope Sans');
    await fonts.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('toast').first()).toContainText('Google Fonts has no font called “Nope Sans”');

    await fonts.getByLabel('Titles').fill('');
    await fonts.getByLabel('Text').fill('');
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/admin/texts/fonts/body') && response.ok()),
        fonts.getByRole('button', { name: 'Save' }).click(),
    ]);
});

test('a link is added, moved to the top and shown on the full page', async ({ page }) => {
    const title = unique('Ko-fi');
    await page.getByRole('link', { name: 'Links' }).click();
    await page.getByRole('button', { name: 'Add a link' }).click();

    const editor = page.getByTestId('link-editor');
    await editor.getByLabel('Title (English)').fill(title);
    await editor.getByLabel('Title (Français)').fill('Offrir un café');
    await editor.getByLabel('Description (English)').fill('Buy the studio a coffee');
    await editor.getByLabel('Address').fill('https://ko-fi.com/mossyleaf');
    await editor.getByRole('radio', { name: 'Blue dots' }).click();
    await editor.getByRole('button', { name: 'Save' }).click();

    const row = page.getByTestId('link-row').filter({ hasText: title });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Edit' }).click();
    await expect(editor.getByLabel('Title (Français)')).toHaveValue('Offrir un café');
    await expect(editor.getByTestId('missing-translation')).toHaveText('Empty: French pages show the English text.');
    await editor.getByRole('button', { name: 'Cancel' }).click();
    while ((await page.getByTestId('link-row').first().textContent()).includes(title) === false) {
        await Promise.all([
            page.waitForResponse((response) => response.url().endsWith('/api/admin/links/order') && response.ok()),
            row.getByRole('button', { name: 'Move up' }).click(),
        ]);
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
    await editor.getByLabel('Title (English)').fill('Old blog');
    await editor.getByLabel('Description (English)').fill('Not secure');
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

test('an image is uploaded, featured without losing the description being typed, described, then deleted', async ({ page }) => {
    await page.goto('/admin/images');
    await page.locator('input[type="file"]').setInputFiles(PNG);
    const card = page.getByTestId('artwork-card').first();
    await expect(card).toBeVisible();

    await card.getByLabel(/Description.*English/).fill('A fern unrolling');
    await card.getByLabel(/Description.*Français/).fill('Une fougère qui se déroule');
    await card.getByRole('button', { name: 'Feature' }).click();
    await expect(card.getByText('Featured', { exact: true })).toBeVisible();
    await expect(card.getByLabel(/Description.*English/)).toHaveValue('A fern unrolling');
    await expect(card.getByLabel(/Description.*Français/)).toHaveValue('Une fougère qui se déroule');
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/admin/artworks/') && response.request().method() === 'PUT' && response.ok()),
        card.getByRole('button', { name: 'Save the description' }).click(),
    ]);
    await expect(card.getByLabel(/Description.*Français/)).toHaveValue('Une fougère qui se déroule');

    await page.goto('/');
    await expect(page.getByRole('img', { name: 'A fern unrolling' })).toBeVisible();

    await page.goto('/admin/images');
    await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByTestId('artwork-card')).toHaveCount(0);
});
