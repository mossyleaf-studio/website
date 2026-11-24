import { test as setup } from '@playwright/test';
import { EDITOR_STATE, signInThroughAccounts } from './support/session.js';

setup('the editor signs in once for the whole run', async ({ page }) => {
    await signInThroughAccounts(page);
    await page.context().storageState({ path: EDITOR_STATE });
});
