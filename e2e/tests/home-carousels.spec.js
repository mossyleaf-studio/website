import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const latest = (page) => page.locator('[data-latest]');
const row = (page) => latest(page).locator('[data-products]');
const scrollLeft = (page) => row(page).evaluate((list) => list.scrollLeft);
const moreAhead = (page) => row(page).evaluate((list) => list.scrollLeft <= list.scrollWidth - list.clientWidth - 1);
const inView = (page) =>
    row(page).evaluate((list) => {
        const frame = list.getBoundingClientRect();
        return [...list.children].filter((item) => {
            const box = item.getBoundingClientRect();
            return box.left >= frame.left - 1 && box.right <= frame.right + 1;
        }).length;
    });

const geometry = (page) =>
    page.evaluate(() => {
        const top = (element) => element.getBoundingClientRect().top;
        const picks = document.querySelector('[data-picks]');
        const latestSection = document.querySelector('[data-latest]');
        const card = latestSection.querySelector('[data-products] > li').getBoundingClientRect();
        return {
            afterHero: Math.round(top(picks) - picks.previousElementSibling.getBoundingClientRect().bottom),
            betweenRows: Math.round(top(latestSection) - top(picks)),
            cardTop: Math.round(card.top - top(latestSection)),
            cardLeft: Math.round(card.left),
            cardWidth: Math.round(card.width),
        };
    });

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('at 1440px Next scrolls the latest row to its end, then Previous comes back', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/beta/en/');
    const previous = latest(page).locator('[data-carousel-previous]');
    const next = latest(page).locator('[data-carousel-next]');
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();
    expect(await inView(page)).toBe(5);
    await expect(page.locator('[data-picks] [data-carousel-next]')).toHaveCount(0);
    for (let press = 0; press < 5 && (await moreAhead(page)); press++) {
        const before = await scrollLeft(page);
        await next.click();
        await expect.poll(() => scrollLeft(page)).toBeGreaterThan(before);
    }
    await expect(next).toBeDisabled();
    await expect(previous).toBeEnabled();
    expect(await scrollLeft(page)).toBeGreaterThan(0);
    await previous.click();
    await expect.poll(() => scrollLeft(page)).toBeLessThan(1);
    await expect(previous).toBeDisabled();
});

test('focusing the last card with the keyboard brings it into view', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/beta/en/');
    const cards = latest(page).locator('[data-test="product-card"]');
    await cards.first().focus();
    for (let press = 1; press < (await cards.count()); press++) await page.keyboard.press('Tab');
    await expect(cards.last()).toBeFocused();
    await expect.poll(() => scrollLeft(page)).toBeGreaterThan(0);
    const overflow = () =>
        row(page).evaluate((list) => {
            const edge = list.getBoundingClientRect().right - parseFloat(getComputedStyle(list).paddingRight);
            return list.lastElementChild.getBoundingClientRect().right - edge;
        });
    await expect.poll(overflow).toBeLessThanOrEqual(1);
});

test('at 1440px the rows sit at the same place with and without JavaScript', async ({ browser, page }) => {
    const viewport = { width: 1440, height: 900 };
    const context = await browser.newContext({ javaScriptEnabled: false, viewport, storageState: await page.context().storageState() });
    const visitor = await context.newPage();
    await visitor.goto('/beta/en/');
    const prerendered = await geometry(visitor);
    await context.close();
    await page.setViewportSize(viewport);
    await page.goto('/beta/en/');
    await expect(page.locator('[data-latest] [data-carousel]')).toBeVisible();
    expect(await geometry(page)).toEqual(prerendered);
});
