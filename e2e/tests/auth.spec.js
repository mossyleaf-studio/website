import { expect, test } from '@playwright/test';
import { OUTSIDER, signIn, signInWithAccount } from './support/session.js';

test('the public pages need no account', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('mossyleaf.studio');
    await expect(page.getByRole('link', { name: 'Etsy' })).toHaveAttribute('href', 'https://www.etsy.com/shop/mossyleafstudio');

    await page.goto('/beta/');
    await expect(page.getByRole('link', { name: /Shop on Etsy/ })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'About the studio' })).toBeVisible();
});

test('an editor signs in with their mossyleaf account and signs out', async ({ page }) => {
    await signIn(page);
    await expect(page.getByRole('heading', { level: 1, name: 'Texts' })).toBeVisible();

    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL(/endsession|\/$/);
});

test('an account outside the mossyleaf-studio group is turned away', async ({ page }) => {
    await page.goto('/admin/');
    await signInWithAccount(page, OUTSIDER);
    await expect(page.getByRole('alert')).toContainText('does not have access');
    await expect(page.getByRole('link', { name: 'Back to the site' })).toBeVisible();
});
