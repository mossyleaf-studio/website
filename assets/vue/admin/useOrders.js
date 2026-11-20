import { ref } from 'vue';
import { useApi } from '../composables/useApi.js';

const ORDERS = '/api/admin/orders';

const api = useApi();

const orders = ref([]);

function replaceOrder(saved) {
    orders.value = orders.value.map((order) => (order.id === saved.id ? saved : order));
    return saved;
}

export function useOrders() {
    const loaded = ref(false);
    const failed = ref(false);

    function reload() {
        failed.value = false;
        return api.load(ORDERS, orders).then(() => { loaded.value = true; }, () => { failed.value = true; });
    }

    return {
        orders,
        loaded,
        failed,
        reload,
        ship: async (id, trackingNumber) => replaceOrder(await api.post(`${ORDERS}/${id}/shipment`, { trackingNumber })),
        cancel: async (id) => replaceOrder(await api.post(`${ORDERS}/${id}/cancellation`)),
        checkPayment: async (id) => replaceOrder(await api.post(`${ORDERS}/${id}/payment-check`)),
        remove: async (id) => {
            await api.del(`${ORDERS}/${id}`);
            orders.value = orders.value.filter((order) => order.id !== id);
        },
    };
}
