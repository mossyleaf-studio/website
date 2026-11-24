import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

test('the current menu entry is marked and Home leads to the shop home', async ({ page }) => {
    await signIn(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/beta/en/about');
    const navigation = page.locator('#shop .site-header__navigation');
    await expect(navigation.locator('[aria-current="page"]')).toHaveCount(1);
    await expect(navigation.getByRole('link', { name: 'About' })).toHaveAttribute('aria-current', 'page');
    await navigation.getByRole('link', { name: 'Home' }).click();
    await expect(page).toHaveURL(/\/beta\/en\/$/);
    await expect(navigation.getByRole('link', { name: 'Home' })).toHaveAttribute('aria-current', 'page');
    await expect(navigation.getByRole('link', { name: 'About' })).not.toHaveAttribute('aria-current', 'page');
});
