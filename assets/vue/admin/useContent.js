import { ref } from 'vue';
import { useApi } from '../composables/useApi.js';

const TEXTS = '/api/admin/texts';
const LINKS = '/api/admin/links';
const ARTWORKS = '/api/admin/artworks';
const LOGO = '/api/admin/logo';
const PUBLICATION = '/api/admin/publication';

const api = useApi();

const texts = ref(null);
const links = ref([]);
const artworks = ref([]);

function replaceArtwork(saved) {
    artworks.value = artworks.value.map((artwork) => (artwork.id === saved.id ? saved : artwork));
    return saved;
}

export function useTexts() {
    return {
        texts,
        load: () => api.load(TEXTS, texts),
        save: async (section, body) => {
            texts.value = await api.put(`${TEXTS}/${section}`, body);
            return texts.value;
        },
        uploadLogo: async (file) => {
            const body = new FormData();
            body.append('image', file);
            texts.value = await api.post(LOGO, body);
        },
        removeLogo: async () => {
            texts.value = await api.del(LOGO);
        },
    };
}

export function useLinks() {
    return {
        links,
        load: () => api.load(LINKS, links),
        create: (body) => api.post(LINKS, body),
        edit: (id, body) => api.put(`${LINKS}/${id}`, body),
        remove: (id) => api.del(`${LINKS}/${id}`),
        reorder: async (ids) => {
            links.value = await api.put(`${LINKS}/order`, { ids });
        },
    };
}

export function useArtworks() {
    return {
        artworks,
        load: () => api.load(ARTWORKS, artworks),
        upload: (file) => {
            const body = new FormData();
            body.append('image', file);
            return api.post(ARTWORKS, body);
        },
        describe: async (id, alt) => replaceArtwork(await api.put(`${ARTWORKS}/${id}`, { alt })),
        feature: async (id) => {
            artworks.value = await api.put(`${ARTWORKS}/${id}/featured`);
        },
        unfeature: async (id) => replaceArtwork(await api.del(`${ARTWORKS}/${id}/featured`)),
        remove: (id) => api.del(`${ARTWORKS}/${id}`),
        reorder: async (ids) => {
            artworks.value = await api.put(`${ARTWORKS}/order`, { ids });
        },
    };
}

const publication = ref(null);

export function usePublication() {
    return {
        publication,
        load: () => api.load(PUBLICATION, publication),
        publish: async (page) => {
            publication.value = await api.post(PUBLICATION, { page });
        },
    };
}
