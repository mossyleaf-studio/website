import { ApiError } from '../composables/apiError.js';

export async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(payload?.detail ?? payload?.title ?? response.statusText, response.status, payload?.violations ?? [], payload?.code ?? null);
    }

    return payload;
}
