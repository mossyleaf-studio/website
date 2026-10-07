import { test } from '@playwright/test';
import { signIn } from './support/session.js';

const VIEWPORTS = { phone: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };
const PUBLIC = { home: '/', beta: '/beta/' };
const ADMIN = { texts: '/admin/texts', links: '/admin/links', images: '/admin/images' };

test('screenshots of the site and the admin', async ({ page }) => {
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        for (const [name, url] of Object.entries(PUBLIC)) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/${name}-${size}.png`, fullPage: true });
        }
    }

    await signIn(page);
    for (const [size, viewport] of Object.entries(VIEWPORTS)) {
        await page.setViewportSize(viewport);
        for (const [name, url] of Object.entries(ADMIN)) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `shots/admin-${name}-${size}.png`, fullPage: true });
        }
    }
});
