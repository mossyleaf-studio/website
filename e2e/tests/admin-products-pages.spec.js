import { expect, test } from '@playwright/test';
import { addProducts, removeAddedProducts } from './support/orders.js';
import { EDITOR_STATE, signIn } from './support/session.js';

test.beforeAll(async ({ browser }) => {
    const editor = await browser.newContext({ storageState: EDITOR_STATE });
    await removeAddedProducts(editor.request);
    await addProducts(editor.request, 37);
    await editor.close();
});

test.afterAll(async ({ browser }) => {
    const editor = await browser.newContext({ storageState: EDITOR_STATE });
    await removeAddedProducts(editor.request);
    await editor.close();
});

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/products');
});

const rows = (page) => page.getByTestId('product-row');
const pager = (page) => page.getByTestId('products-pagination');
const references = (page) => rows(page).evaluateAll((items) => items.map((item) => item.dataset.reference));
const pageButton = (page, number) => pager(page).getByRole('button', { name: `Page ${number}`, exact: true });

test('45 products make 3 pages of 20, 20 and 5, and the address follows', async ({ page }) => {
    await expect(rows(page)).toHaveCount(20);
    await expect(pager(page)).toContainText('1–20 of 45');
    await expect(page).not.toHaveURL(/page=/);

    await pageButton(page, 2).click();
    await expect(page).toHaveURL(/\/admin\/products\?page=2$/);
    await expect(pager(page)).toContainText('21–40 of 45');
    await expect(rows(page)).toHaveCount(20);

    await page.reload();
    await expect(page).toHaveURL(/page=2/);
    await expect(pager(page)).toContainText('21–40 of 45');

    await pager(page).getByRole('button', { name: 'Last page' }).click();
    await expect(page).toHaveURL(/page=3/);
    await expect(rows(page)).toHaveCount(5);
    await expect(pager(page)).toContainText('41–45 of 45');

    await pager(page).getByRole('button', { name: 'Previous page' }).click();
    await expect(page).toHaveURL(/page=2/);
    await pager(page).getByRole('button', { name: 'Next page' }).click();
    await expect(page).toHaveURL(/page=3/);

    await pager(page).getByRole('button', { name: 'First page' }).click();
    await expect(page).not.toHaveURL(/page=/);
    await expect(pager(page)).toContainText('1–20 of 45');
});

test('the arrows move across a page boundary and the page follows', async ({ page }) => {
    const before = await references(page);
    const last = before[19];
    const lastRow = rows(page).nth(19);
    await lastRow.getByRole('button', { name: /^Move .* down$/ }).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(rows(page).first()).toHaveAttribute('data-reference', last);

    await page.reload();
    await expect(page).toHaveURL(/page=2/);
    await expect(rows(page).first()).toHaveAttribute('data-reference', last);
    await page.goto('/admin/products');
    expect(await references(page)).not.toContain(last);
});

test('the first product of page 2 goes up onto page 1 and keeps the focus', async ({ page }) => {
    await pageButton(page, 2).click();
    await expect(page).toHaveURL(/page=2/);
    const first = await rows(page).first().getAttribute('data-reference');
    const up = rows(page).first().getByRole('button', { name: /^Move .* up$/ });
    await expect(up).toBeEnabled();
    await up.click();
    await expect(page).not.toHaveURL(/page=/);
    await expect(rows(page).nth(19)).toHaveAttribute('data-reference', first);
    await expect(rows(page).nth(19).getByRole('button', { name: /^Move .* up$/ })).toBeFocused();
});

test('a drag on page 2 saves the whole order', async ({ page }) => {
    const all = async () => {
        const seen = [];
        for (const number of [1, 2, 3]) {
            await page.goto(number > 1 ? `/admin/products?page=${number}` : '/admin/products');
            await expect(pager(page)).toBeVisible();
            seen.push(...await references(page));
        }
        return seen;
    };
    const before = await all();
    await page.goto('/admin/products?page=2');
    await expect(pager(page)).toContainText('21–40 of 45');
    const saved = page.waitForResponse((response) => response.url().endsWith('/api/admin/products/order') && response.ok());
    await rows(page).nth(3).locator('.products-page__grip').dragTo(rows(page).nth(0));
    await expect(rows(page).first()).toHaveAttribute('data-reference', before[23]);
    await saved;

    const after = await all();
    expect(after).toEqual([...before.slice(0, 20), before[23], ...before.slice(20, 23), ...before.slice(24)]);
    expect(new Set(after).size).toBe(45);
});
