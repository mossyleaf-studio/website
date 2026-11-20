import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

test('the language switch keeps the page and the preview prefix', async ({ page }) => {
    await signIn(page);
    await page.goto('/beta/fr/about');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'English' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/about$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    await page.locator('nav[data-languages]').first().getByRole('link', { name: 'Français' }).click();
    await expect(page).toHaveURL(/\/beta\/fr\/about$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
});

test('the menu button opens and closes the navigation below 56rem', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/beta/en/');
    const menu = page.locator('#shop .site-header__menu');
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#site-navigation').getByRole('link', { name: 'About' })).toBeVisible();
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#site-navigation').getByRole('link', { name: 'About' })).toBeHidden();
});

test('the draft banner speaks the language of the shop and the note keeps its own', async ({ page }) => {
    await signIn(page);
    await page.goto('/beta/fr/about');
    const banner = page.getByRole('complementary', { name: 'Aperçu du brouillon' });
    await expect(banner.getByRole('link', { name: 'Boutique' })).toHaveAttribute('href', '/beta/fr/');
    await expect(banner.getByRole('link', { name: 'Boutique' })).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#shop nav[data-languages]')).toHaveCount(1);

    await page.goto('/beta/note/');
    const note = page.getByRole('complementary', { name: 'Draft preview' });
    await expect(note.getByRole('link')).toHaveText(['Shop', 'Growing note', 'Back to the admin']);
    await expect(note.getByRole('link', { name: 'Shop' })).toHaveAttribute('href', '/beta/en/');
    await expect(note.getByRole('link', { name: 'Growing note' })).toHaveAttribute('aria-current', 'page');
    await expect(note.getByRole('link', { name: 'Back to the admin' })).toHaveAttribute('href', '/admin');

    await page.goto('/beta/');
    await expect(page).toHaveURL(/\/beta\/en\/$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
});
