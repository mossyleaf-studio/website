import { ref } from 'vue';
import { useApi } from '../composables/useApi.js';

const PRODUCTS = '/api/admin/products';
const CATEGORIES = '/api/admin/categories';

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
    };
}
