import { readFileSync } from 'node:fs';

export const EDITOR = {
    account: 'editor',
    claims: { email: 'editor@mossyleaf.test', name: 'Editor', groups: ['mossyleaf-studio'] },
};

export const OUTSIDER = {
    account: 'outsider',
    claims: { email: 'outsider@mossyleaf.test', name: 'Outsider', groups: ['mossydew'] },
};

export async function signInWithAccount(page, account) {
    await page.locator('input[name="username"]').fill(account.account);
    await page.locator('textarea[name="claims"]').fill(JSON.stringify(account.claims));
    await page.getByRole('button', { name: 'Sign-in' }).click();
}

export const EDITOR_STATE = new URL('../../.auth/editor.json', import.meta.url).pathname;

export async function signInThroughAccounts(page, account = EDITOR) {
    await page.goto('/admin/');
    await signInWithAccount(page, account);
    await page.waitForURL('/admin/orders');
}

export async function signIn(page) {
    await page.context().addCookies(JSON.parse(readFileSync(EDITOR_STATE, 'utf8')).cookies);
    await page.goto('/admin/');
    await page.waitForURL('/admin/orders');
}

export const unique = (label) => `${label} ${Date.now().toString(36)}${Math.random().toString(36).slice(2, 5)}`;

export const PNG = new URL('../fixtures/fern.png', import.meta.url).pathname;

export const LOGO = new URL('../fixtures/logo.png', import.meta.url).pathname;
