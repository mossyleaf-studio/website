import { ApiError } from '../composables/apiError.js';

async function answer(response) {
    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(payload?.detail ?? payload?.title ?? response.statusText, response.status, payload?.violations ?? [], payload?.code ?? null);
    }

    return payload;
}

export const postJson = async (url, body) =>
    answer(await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) }));

export const getJson = async (url) => answer(await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }));
