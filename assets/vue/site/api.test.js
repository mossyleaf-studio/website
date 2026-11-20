import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../composables/apiError.js';
import { postJson } from './api.js';

const answer = (status, body = null, type = 'application/problem+json') =>
    new Response(body === null ? null : JSON.stringify(body), { status, headers: body === null ? {} : { 'Content-Type': type } });

function fakeFetch(response) {
    const fetch = vi.fn(async () => response);
    vi.stubGlobal('fetch', fetch);
    return fetch;
}

afterEach(() => vi.unstubAllGlobals());

describe('postJson', () => {
    it('posts the body as JSON with the cookies of the site', async () => {
        const fetch = fakeFetch(answer(204));

        await postJson('/beta/api/shop/fr/contact', { name: 'Lou' });

        expect(fetch).toHaveBeenCalledWith('/beta/api/shop/fr/contact', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: '{"name":"Lou"}',
        });
    });

    it('answers null when there is no content', async () => {
        fakeFetch(answer(204));

        await expect(postJson('/api/shop/en/contact', {})).resolves.toBeNull();
    });

    it('answers the JSON the server sends back', async () => {
        fakeFetch(answer(200, { total: 1500 }, 'application/json'));

        await expect(postJson('/api/shop/en/quote', {})).resolves.toEqual({ total: 1500 });
    });

    it('throws the status, the code and the detail of a problem', async () => {
        fakeFetch(answer(429, { title: 'Too many requests', status: 429, detail: 'Try again later.', code: 'problem.too_many_requests' }));

        const error = await postJson('/api/shop/en/contact', {}).catch((caught) => caught);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({ status: 429, code: 'problem.too_many_requests', message: 'Try again later.', violations: [] });
    });

    it('throws the violations of a refused payload', async () => {
        const violations = [{ propertyPath: 'email', title: 'Enter your email address.' }];
        fakeFetch(answer(422, { title: 'Validation Failed', detail: 'email: Enter your email address.', violations }, 'application/json'));

        const error = await postJson('/api/shop/en/contact', {}).catch((caught) => caught);

        expect(error).toMatchObject({ status: 422, code: null, violations });
        expect(error.fieldErrors).toEqual({ email: 'Enter your email address.' });
    });

    it('throws the status alone when the answer is not JSON', async () => {
        fakeFetch(new Response('<h1>Bad gateway</h1>', { status: 502, headers: { 'Content-Type': 'text/html' } }));

        const error = await postJson('/api/shop/en/contact', {}).catch((caught) => caught);

        expect(error).toMatchObject({ status: 502, code: null, violations: [] });
    });

    it('lets a network failure through', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('Failed to fetch'); }));

        await expect(postJson('/api/shop/en/contact', {})).rejects.toThrow(TypeError);
    });
});
