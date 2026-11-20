const FIXES = {
    identity: 'texts',
    shipping: 'settings',
    products: 'products',
    photos: 'products',
    terms: 'legal',
    legalNotice: 'legal',
    privacy: 'legal',
    shippingReturns: 'legal',
    testOrders: 'orders',
};

export const fixOf = (key) => ({ to: FIXES[key] ?? null });

export function stateOf(publication, formatDate = (iso) => iso) {
    if (!publication.open) return { key: 'closed', params: {} };
    return { key: 'open', params: { when: formatDate(publication.publishedAt), by: publication.publishedBy } };
}

export function publishingMessage(publication) {
    if (!publication.open) return 'opens';
    return publication.pendingChanges ? 'updates' : 'nothing';
}

export const shownItems = (readiness) => readiness?.items.filter((item) => item.state !== 'ok') ?? [];

export const readyCount = (readiness) => ({
    ready: readiness?.items.filter((item) => item.state === 'ok').length ?? 0,
    total: readiness?.items.length ?? 0,
});

export function canPublish(publication, readiness) {
    if (!readiness?.ready) return false;
    return !publication.open || publication.pendingChanges;
}

export const canShowNote = (publication) => publication.open || publication.pendingChanges;

export function noteMessage(publication) {
    if (publication.open) return 'closes';
    return publication.pendingChanges ? 'refreshesNote' : null;
}

export const shopHref = (publication, language) => (publication ? (publication.preview.shop[language] ?? publication.preview.shop.en) : null);

export const aboutHref = (publication, language) => (publication ? (publication.preview.about[language] ?? publication.preview.about.en) : null);

export const noteHref = (publication) => publication?.preview.note ?? null;
