import { expect, test } from '@playwright/test';
import { addProducts, removeAddedProducts } from './support/orders.js';
import { signIn } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await removeAddedProducts(page.request);
    await addProducts(page.request, 37);
    await page.goto('/admin/products');
});

test.afterEach(async ({ page }) => {
    await removeAddedProducts(page.request);
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

test('a page number out of range is clamped', async ({ page }) => {
    await page.goto('/admin/products?page=99');
    await expect(pager(page)).toContainText('41–45 of 45');
    await expect(page).toHaveURL(/\/admin\/products\?page=3$/);
});

test('the pager is reachable by keyboard and announces the current page', async ({ page }) => {
    await expect(pageButton(page, 1)).toHaveAttribute('aria-current', 'page');
    await pageButton(page, 2).focus();
    await expect(pageButton(page, 2)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/page=2/);
    await expect(pageButton(page, 2)).toHaveAttribute('aria-current', 'page');
});

test('coming back from a product editor lands on the same page', async ({ page }) => {
    await pageButton(page, 2).click();
    await expect(page).toHaveURL(/page=2/);
    await rows(page).first().getByTestId('product-edit').click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/products\/[^?]+\?page=2/);
    await page.goBack();
    await expect(page).toHaveURL(/page=2/);
    await expect(pager(page)).toContainText('21–40 of 45');

    await rows(page).first().getByTestId('product-edit').click();
    await expect(page.getByTestId('product-editor')).toBeVisible();
    await page.getByRole('link', { name: 'All products' }).click();
    await expect(page).toHaveURL(/\/admin\/products\?page=2$/);
    await expect(pager(page)).toContainText('21–40 of 45');
});

test('a search or a filter goes back to page 1 and hides the pager when 20 or fewer match', async ({ page }) => {
    await pageButton(page, 2).click();
    await expect(page).toHaveURL(/page=2/);
    await page.getByTestId('product-search').fill('Extra product 3');
    await expect(page).not.toHaveURL(/page=/);
    await expect(pager(page)).toHaveCount(0);
    await expect(rows(page).first()).toBeVisible();
    expect(await rows(page).count()).toBeLessThanOrEqual(20);
    await page.getByTestId('product-search').fill('');
    await expect(pager(page)).toBeVisible();

    await pageButton(page, 3).click();
    await expect(page).toHaveURL(/page=3/);
    await page.getByTestId('product-category-filter').click();
    await page.getByRole('option').last().click();
    await expect(page).not.toHaveURL(/page=/);
    await expect(pager(page)).toHaveCount(0);
    await expect(rows(page).first()).toBeVisible();
});

test('50 per page shows all 45 on one page without a pager of pages', async ({ page }) => {
    await pager(page).getByRole('combobox', { name: 'Per page' }).click();
    await page.getByRole('option', { name: '50', exact: true }).click();
    await expect(rows(page)).toHaveCount(45);
    await expect(pager(page)).toContainText('1–45 of 45');
    await expect(pageButton(page, 2)).toHaveCount(0);
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

test('the first product of the list cannot go up, the last cannot go down', async ({ page }) => {
    await expect(rows(page).first().getByRole('button', { name: /^Move .* up$/ })).toBeDisabled();
    await expect(rows(page).first().getByRole('button', { name: /^Move .* down$/ })).toBeEnabled();
    await pager(page).getByRole('button', { name: 'Last page' }).click();
    await expect(rows(page).last().getByRole('button', { name: /^Move .* down$/ })).toBeDisabled();
    await expect(rows(page).first().getByRole('button', { name: /^Move .* up$/ })).toBeEnabled();
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

test('the pager is locked while a bulk action runs', async ({ page }) => {
    let release;
    const held = new Promise((done) => { release = done; });
    await page.route('**/api/admin/products/*/visibility', async (route) => {
        await held;
        await route.fallback();
    });
    await pager(page).getByRole('button', { name: 'Last page' }).click();
    await rows(page).first().getByTestId('select-row').getByRole('checkbox').click();
    await page.getByTestId('bulk-hide').getByRole('button').click();
    await expect(page.getByTestId('selection-count')).toHaveText('Working… 0 of 1');
    await expect(pageButton(page, 1)).toBeDisabled();
    await expect(pager(page).getByRole('button', { name: 'Previous page' })).toBeDisabled();
    await expect(pager(page).getByRole('combobox', { name: 'Per page' })).toBeDisabled();
    release();
    await expect(page.getByTestId('selection-count')).toHaveCount(0);
    await expect(pageButton(page, 1)).toBeEnabled();
});

test('the head checkbox ticks the page, and changing page clears the selection', async ({ page }) => {
    await page.getByTestId('select-all').getByRole('checkbox').click();
    await expect(page.getByTestId('selection-count')).toHaveText('20 products selected');
    await pageButton(page, 2).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(page.getByTestId('selection-count')).toHaveCount(0);
});
