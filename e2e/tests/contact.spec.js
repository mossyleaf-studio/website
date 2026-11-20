import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

const MAILPIT = process.env.MAILPIT_URL ?? 'http://mailpit:8025';
const STUDIO = 'studio@mossyleaf.test';

const messages = async (request) => (await (await request.get(`${MAILPIT}/api/v1/messages`)).json()).messages;

test.beforeEach(async ({ page, request }) => {
    await request.delete(`${MAILPIT}/api/v1/messages`);
    await signIn(page);
    await page.goto('/beta/fr/contact');
});

const fill = async (page, honeypot = '') => {
    await page.getByLabel('Votre nom').fill('Camille Martin');
    await page.getByLabel('Votre e-mail').fill('camille@example.test');
    await page.getByLabel('Votre message').fill('Bonjour,\nune question sur vos cartes.');
    if (honeypot) {
        await page.locator('#contact-website').evaluate((input, value) => {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, honeypot);
    }
};

test('a message sent from the preview reaches the studio mailbox with the visitor as Reply-To', async ({ page, request }) => {
    await fill(page);
    await page.getByTestId('contact-send').click();
    await expect(page.getByTestId('contact-sent')).toBeVisible();

    await expect.poll(async () => (await messages(request)).length).toBe(1);
    const mail = await (await request.get(`${MAILPIT}/api/v1/message/latest`)).json();
    expect(mail.To.map((address) => address.Address)).toEqual([STUDIO]);
    expect(mail.From.Address).toBe('shop@mossyleaf.test');
    expect(mail.ReplyTo.map((address) => address.Address)).toEqual(['camille@example.test']);
    expect(mail.Subject).toContain('Camille Martin');
    expect(mail.Text).toContain('une question sur vos cartes.');
});

test('an empty form shows the field errors and sends nothing', async ({ page, request }) => {
    await page.getByTestId('contact-send').click();
    await expect(page.getByTestId('field-error')).toHaveCount(3);
    await expect(page.getByTestId('contact-sent')).toHaveCount(0);
    expect(await messages(request)).toHaveLength(0);
});

test('a filled honeypot looks like a success and sends nothing', async ({ page, request }) => {
    await fill(page, 'https://spam.example');
    await page.getByTestId('contact-send').click();
    await expect(page.getByTestId('contact-sent')).toBeVisible();
    expect(await messages(request)).toHaveLength(0);
});
