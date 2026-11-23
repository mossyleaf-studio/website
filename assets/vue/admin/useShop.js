import { ref } from 'vue';
import { useApi } from '../composables/useApi.js';

const PRODUCTS = '/api/admin/products';
const CATEGORIES = '/api/admin/categories';
const CONVENTIONS = '/api/admin/conventions';
const LEGAL_PAGES = '/api/admin/legal-pages';
const SHIPPING_ZONES = '/api/admin/shipping-zones';

const api = useApi();

const products = ref([]);
const categories = ref([]);

function replaceProduct(saved) {
    products.value = products.value.map((product) => (product.id === saved.id ? saved : product));
    return saved;
}

export function useProducts() {
    return {
        products,
        load: () => api.load(PRODUCTS, products),
        describe: async (id, body) => replaceProduct(await api.put(`${PRODUCTS}/${id}`, body)),
        show: async (id) => replaceProduct(await api.put(`${PRODUCTS}/${id}/visibility`)),
        hide: async (id) => replaceProduct(await api.del(`${PRODUCTS}/${id}/visibility`)),
        pick: async (id) => replaceProduct(await api.put(`${PRODUCTS}/${id}/pick`)),
        unpick: async (id) => replaceProduct(await api.del(`${PRODUCTS}/${id}/pick`)),
        reorder: async (ids) => {
            products.value = await api.put(`${PRODUCTS}/order`, { ids });
        },
        uploadPhoto: async (id, file) => {
            const body = new FormData();
            body.append('image', file);
            return replaceProduct(await api.post(`${PRODUCTS}/${id}/photos`, body));
        },
        deletePhoto: async (id, photo) => {
            await api.del(`${PRODUCTS}/${id}/photos/${photo}`);
            products.value = products.value.map((product) => (product.id === id ? { ...product, photos: product.photos.filter((candidate) => candidate.id !== photo) } : product));
        },
        reorderPhotos: async (id, ids) => replaceProduct(await api.put(`${PRODUCTS}/${id}/photos/order`, { ids })),
    };
}

export function useCategories() {
    return {
        categories,
        load: () => api.load(CATEGORIES, categories),
        label: async (id, body) => {
            const saved = await api.put(`${CATEGORIES}/${id}`, body);
            categories.value = categories.value.map((category) => (category.id === saved.id ? saved : category));
            return saved;
        },
        reorder: async (ids) => {
            categories.value = await api.put(`${CATEGORIES}/order`, { ids });
        },
    };
}

const conventions = ref([]);

export function useConventions() {
    return {
        conventions,
        load: () => api.load(CONVENTIONS, conventions),
        create: (body) => api.post(CONVENTIONS, body),
        edit: (id, body) => api.put(`${CONVENTIONS}/${id}`, body),
        remove: (id) => api.del(`${CONVENTIONS}/${id}`),
    };
}

const legalPages = ref([]);

export function useLegalPages() {
    return {
        legalPages,
        load: () => api.load(LEGAL_PAGES, legalPages),
        save: async (kind, body) => {
            const saved = await api.put(`${LEGAL_PAGES}/${kind}`, body);
            legalPages.value = legalPages.value.map((page) => (page.kind === kind ? saved : page));
            return saved;
        },
    };
}

const shippingZones = ref([]);

export function useShippingZones() {
    return {
        shippingZones,
        load: () => api.load(SHIPPING_ZONES, shippingZones),
        save: async (code, body) => {
            const saved = await api.put(`${SHIPPING_ZONES}/${code}`, body);
            shippingZones.value = shippingZones.value.map((zone) => (zone.code === code ? saved : zone));
            return saved;
        },
    };
}
