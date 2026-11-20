import { computed, onScopeDispose, readonly, shallowRef } from 'vue';
import { CART_KEY, PLACED_KEY, addLine, cartUnits, emptyCart, readCart, removeLine, setCountry, setQuantity, settle } from './cart.js';

function stored() {
    try {
        return readCart(window.localStorage.getItem(CART_KEY));
    } catch {
        return emptyCart();
    }
}

export function useCart() {
    const cart = shallowRef(stored());

    const write = (next) => {
        if (JSON.stringify(next) === JSON.stringify(cart.value)) return false;
        cart.value = next;
        try {
            window.localStorage.setItem(CART_KEY, JSON.stringify(next));
        } catch {
            return true;
        }
        return true;
    };

    const synchronise = (event) => {
        if (event.key === CART_KEY || event.key === null) cart.value = stored();
    };
    window.addEventListener('storage', synchronise);
    onScopeDispose(() => window.removeEventListener('storage', synchronise));

    return {
        cart: readonly(cart),
        units: computed(() => cartUnits(cart.value)),
        add: (line, limit) => write(addLine(cart.value, line, limit)),
        setQuantity: (sku, quantity) => write(setQuantity(cart.value, sku, quantity)),
        remove: (sku) => write(removeLine(cart.value, sku)),
        setCountry: (country) => write(setCountry(cart.value, country)),
        settle: (quote) => write(settle(cart.value, quote)),
        placed: (reference) => {
            try {
                window.localStorage.setItem(PLACED_KEY, reference);
            } catch {
                return false;
            }
            return true;
        },
        paid: (reference) => {
            try {
                if (window.localStorage.getItem(PLACED_KEY) !== reference) return false;
                window.localStorage.removeItem(PLACED_KEY);
            } catch {
                return false;
            }
            return write({ ...cart.value, lines: [] });
        },
    };
}
