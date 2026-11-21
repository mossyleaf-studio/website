import { describe, expect, it } from 'vitest';
import { aboutHref, blockingCount, canPublish, canShowNote, fixOf, listShown, noteHref, noteMessage, publishingMessage, readyCount, shopHref, shownItems, stateOf } from './publication.js';

const item = (key, state = 'ok', count = null) => ({ key, state, count });
const readiness = (...items) => ({ ready: !items.some((one) => one.state === 'blocker'), items });
const publication = (overrides = {}) => ({
    page: 'note',
    open: false,
    publishedAt: null,
    publishedBy: null,
    pendingChanges: true,
    preview: { shop: { en: '/beta/en/', fr: '/beta/fr/' }, about: { en: '/beta/en/about', fr: '/beta/fr/about' }, note: '/beta/note/' },
    ...overrides,
});

describe('stateOf', () => {
    it('says the shop is closed and visitors see the note when nothing is open', () => {
        expect(stateOf(publication())).toEqual({ key: 'closed', params: {} });
        expect(stateOf(publication({ page: 'full', publishedAt: '2026-11-20T10:00:00+00:00' }))).toEqual({ key: 'closed', params: {} });
    });

    it('says the shop is open, when it was last published and by whom', () => {
        const open = publication({ open: true, page: 'full', publishedAt: '2026-11-20T10:00:00+00:00', publishedBy: 'Eloise' });
        expect(stateOf(open, (iso) => `on ${iso.slice(0, 10)}`)).toEqual({ key: 'open', params: { when: 'on 2026-11-20', by: 'Eloise' } });
    });
});

describe('publishingMessage', () => {
    it('tells what Publish will do', () => {
        expect(publishingMessage(publication())).toBe('opens');
        expect(publishingMessage(publication({ open: true, pendingChanges: true }))).toBe('updates');
        expect(publishingMessage(publication({ open: true, pendingChanges: false }))).toBe('nothing');
    });
});

describe('shownItems', () => {
    const all = readiness(item('identity'), item('payment', 'blocker'), item('photos', 'warning', 2), item('mail'));

    it('shows nothing before the list is known', () => {
        expect(shownItems(null)).toEqual([]);
    });

    it('shows only what is not ok, open or closed', () => {
        expect(shownItems(all).map((one) => one.key)).toEqual(['payment', 'photos']);
    });

    it('puts the blocking items first and keeps the order of the list otherwise', () => {
        const mixed = readiness(item('photos', 'warning', 2), item('identity', 'blocker'), item('mail'), item('mossytrunk', 'warning'), item('payment', 'blocker'));
        expect(shownItems(mixed).map((one) => one.key)).toEqual(['identity', 'payment', 'photos', 'mossytrunk']);
    });
});

describe('blockingCount', () => {
    it('counts the items that stop the shop from opening', () => {
        expect(blockingCount(readiness(item('identity', 'blocker'), item('photos', 'warning', 2), item('payment', 'blocker'), item('mail')))).toBe(2);
        expect(blockingCount(readiness(item('photos', 'warning', 2)))).toBe(0);
    });

    it('is zero before the list is known', () => {
        expect(blockingCount(null)).toBe(0);
    });
});

describe('listShown', () => {
    const allReady = readiness(item('identity'), item('mail'));
    const warned = readiness(item('identity'), item('photos', 'warning', 1));

    it('waits for the publication and the list', () => {
        expect(listShown(null, warned, false)).toBe(false);
        expect(listShown(publication(), null, false)).toBe(false);
    });

    it('is shown while the shop is closed, even when everything is ready', () => {
        expect(listShown(publication(), allReady, false)).toBe(true);
        expect(listShown(publication(), warned, false)).toBe(true);
    });

    it('is shown on an open shop only while an item is not ok', () => {
        const open = publication({ open: true, page: 'full' });
        expect(listShown(open, allReady, false)).toBe(false);
        expect(listShown(open, warned, false)).toBe(true);
    });

    it('says the list could not be read when it never came', () => {
        expect(listShown(publication(), null, true)).toBe(true);
        expect(listShown(publication({ open: true, page: 'full' }), null, true)).toBe(true);
        expect(listShown(publication({ open: true, page: 'full' }), allReady, true)).toBe(false);
    });
});

describe('readyCount', () => {
    it('counts the items that are ready out of all of them', () => {
        expect(readyCount(readiness(item('identity'), item('payment', 'blocker'), item('photos', 'warning', 2), item('mail')))).toEqual({ ready: 2, total: 4 });
    });

    it('is empty before the list is known', () => {
        expect(readyCount(null)).toEqual({ ready: 0, total: 0 });
    });
});

describe('noteMessage', () => {
    it('says the note button closes an open shop', () => {
        expect(noteMessage(publication({ open: true, pendingChanges: false }))).toBe('closes');
    });

    it('says the note button only refreshes the note while the shop is closed', () => {
        expect(noteMessage(publication({ open: false, pendingChanges: true }))).toBe('refreshesNote');
    });

    it('says nothing when the note button is not shown', () => {
        expect(noteMessage(publication({ open: false, pendingChanges: false }))).toBeNull();
    });
});

describe('canPublish', () => {
    it('waits for the readiness list', () => {
        expect(canPublish(publication(), null)).toBe(false);
    });

    it('is refused while a blocker stands', () => {
        expect(canPublish(publication(), readiness(item('payment', 'blocker')))).toBe(false);
    });

    it('is allowed with warnings only', () => {
        expect(canPublish(publication(), readiness(item('photos', 'warning', 1)))).toBe(true);
    });

    it('has nothing to do on an open shop without waiting changes', () => {
        const ready = readiness(item('identity'));
        expect(canPublish(publication({ open: true, pendingChanges: false }), ready)).toBe(false);
        expect(canPublish(publication({ open: true, pendingChanges: true }), ready)).toBe(true);
    });
});

describe('canShowNote', () => {
    it('closes an open shop, or refreshes the note when the texts changed', () => {
        expect(canShowNote(publication({ open: true, pendingChanges: false }))).toBe(true);
        expect(canShowNote(publication({ open: false, pendingChanges: true }))).toBe(true);
        expect(canShowNote(publication({ open: false, pendingChanges: false }))).toBe(false);
    });
});

describe('fixOf', () => {
    it('points at the admin page that fixes an item', () => {
        expect(fixOf('shipping')).toEqual({ to: 'settings' });
        expect(fixOf('privacy')).toEqual({ to: 'legal' });
        expect(fixOf('photos')).toEqual({ to: 'products' });
        expect(fixOf('testOrders')).toEqual({ to: 'orders' });
        expect(fixOf('identity')).toEqual({ to: 'texts' });
    });

    it('names no page for what only the server can set', () => {
        expect(fixOf('payment')).toEqual({ to: null });
        expect(fixOf('mail')).toEqual({ to: null });
        expect(fixOf('mossytrunk')).toEqual({ to: null });
    });
});

describe('preview addresses', () => {
    it('come from the publication', () => {
        expect(shopHref(publication(), 'fr')).toBe('/beta/fr/');
        expect(shopHref(publication(), 'xx')).toBe('/beta/en/');
        expect(noteHref(publication())).toBe('/beta/note/');
        expect(aboutHref(publication(), 'fr')).toBe('/beta/fr/about');
        expect(aboutHref(publication(), 'xx')).toBe('/beta/en/about');
    });

    it('are absent until the publication is loaded', () => {
        expect(shopHref(null, 'en')).toBeNull();
        expect(noteHref(null)).toBeNull();
        expect(aboutHref(null, 'en')).toBeNull();
    });
});
