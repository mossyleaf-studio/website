import { expect, test } from '@playwright/test';
import { LOGO, PNG, signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const PUBLIC = { home: '/', beta: '/beta/' };
const ADMIN = { texts: '/admin/texts', links: '/admin/links', images: '/admin/images' };

test('screenshots of the site and the admin', async ({ page }) => {
    await signIn(page);
    await page.getByTestId('section-logo').locator('input[type="file"]').setInputFiles(LOGO);
    await expect(page.getByTestId('section-logo').getByRole('img')).toBeVisible();
    await page.goto('/admin/images');
    await page.locator('input[type="file"]').setInputFiles([PNG, PNG, PNG]);
    await expect(page.getByTestId('artwork-card')).toHaveCount(3);
    await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Feature' }).click();
    await expect(page.getByTestId('artwork-card').first().getByText('Featured', { exact: true })).toBeVisible();

    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        for (const [name, url] of Object.entries({ ...PUBLIC, ...Object.fromEntries(Object.entries(ADMIN).map(([key, value]) => [`admin-${key}`, value])) })) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/${name}-${size}.png`, fullPage: true });
        }
    }

    await page.goto('/admin/images');
    for (let count = 3; count > 0; count -= 1) {
        await page.getByTestId('artwork-card').first().getByRole('button', { name: 'Delete' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByTestId('artwork-card')).toHaveCount(count - 1);
    }
});
