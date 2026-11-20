import { describe, expect, it } from 'vitest';
import { CONTACT_LIMITS, contactErrors, contactPayload, serverErrors } from './contactForm.js';

const valid = { name: 'Lou', email: 'lou@example.com', message: 'Hello', website: '' };

describe('contactErrors', () => {
    it('accepts a complete message', () => {
        expect(contactErrors(valid)).toEqual({});
    });

    it('asks for every field left blank, in the order of the form', () => {
        expect(Object.entries(contactErrors({ name: ' ', email: '', message: '\n\t', website: '' }))).toEqual([
            ['name', { key: 'required' }],
            ['email', { key: 'required' }],
            ['message', { key: 'required' }],
        ]);
    });

    it.each(['lou', 'lou@', '@example.com', 'lou@example', 'lou @example.com', 'lou@exa mple.com', 'lou..ann@example.com', '.lou@example.com', 'lou.@example.com', `${'a'.repeat(65)}@example.com`])('refuses the email %s', (email) => {
        expect(contactErrors({ ...valid, email })).toEqual({ email: { key: 'email' } });
    });

    it.each(['lou@example.com', 'Lou.Ann+shop@studio.example.fr', ' lou@example.com '])('takes the email %s', (email) => {
        expect(contactErrors({ ...valid, email })).toEqual({});
    });

    it('holds each field to the limits of the server', () => {
        expect(CONTACT_LIMITS).toEqual({ name: 100, email: 254, message: 4000 });
        expect(contactErrors({ ...valid, name: 'a'.repeat(100), message: 'b'.repeat(4000) })).toEqual({});
        expect(contactErrors({ ...valid, name: 'a'.repeat(101), email: `${'c'.repeat(243)}@example.com`, message: 'b'.repeat(4001) })).toEqual({
            name: { key: 'tooLong', max: 100 },
            email: { key: 'tooLong', max: 254 },
            message: { key: 'tooLong', max: 4000 },
        });
    });

    it('counts characters, not code units', () => {
        expect(contactErrors({ ...valid, name: '🌿'.repeat(100) })).toEqual({});
    });

    it('refuses a line break in the name or the email', () => {
        expect(contactErrors({ ...valid, name: 'Lou\nBcc: x@example.com', email: 'lou@example.com\r\nBcc: x@example.com', message: 'Line one\nLine two' })).toEqual({
            name: { key: 'lineBreak' },
            email: { key: 'lineBreak' },
        });
    });
});

describe('contactPayload', () => {
    it('sends the trimmed fields and the honeypot as typed', () => {
        expect(contactPayload({ name: ' Lou ', email: ' lou@example.com ', message: '\nHello\n', website: 'spam' })).toEqual({ name: 'Lou', email: 'lou@example.com', message: 'Hello', website: 'spam' });
    });
});

describe('serverErrors', () => {
    it('keeps the first violation of each field of the form', () => {
        expect(serverErrors([
            { propertyPath: 'email', title: 'Write this on a single line.' },
            { propertyPath: 'email', title: 'Enter a valid email address.' },
            { propertyPath: 'message', title: 'This text is too long.' },
            { propertyPath: 'website', title: 'Unexpected.' },
            { propertyPath: '', title: 'Whole payload.' },
        ])).toEqual({ email: { text: 'Write this on a single line.' }, message: { text: 'This text is too long.' } });
    });
});
