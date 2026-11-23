import '@hotwired/turbo';
import { createApp, effectScope } from 'vue';
import '@fontsource/kalam/latin-700.css';
import '@fontsource-variable/quicksand/wght.css';
import './styles/site/tokens.css';
import './styles/site/shop.css';
import './vue/site/progressBar.js';
import ShopApp from './vue/site/ShopApp.vue';
import { createSiteI18n } from './vue/site/i18n.js';
import { useCart } from './vue/site/useCart.js';

const ANNOUNCE_DELAY = 150;
const cart = effectScope(true).run(useCart);
let app = null;

function mount() {
    const content = document.getElementById('site-content');
    const root = document.getElementById('shop');
    if (!content || !root) return null;
    const { layout, data } = JSON.parse(content.textContent);
    document.documentElement.lang = layout.locale;
    app = createApp(ShopApp, { layout, data }).use(createSiteI18n(layout.locale)).provide('cart', cart);
    app.mount(root);
    return root;
}

function unmount(event) {
    const style = event.detail.newBody.ownerDocument.documentElement.getAttribute('style');
    if (style === null) document.documentElement.removeAttribute('style');
    else document.documentElement.setAttribute('style', style);
    app?.unmount();
    app = null;
}

function arrive(root) {
    root.setAttribute('tabindex', '-1');
    root.focus({ preventScroll: true });
    root.addEventListener('blur', () => root.removeAttribute('tabindex'), { once: true });
    const announcer = document.getElementById('shop-announcer');
    if (!announcer) return;
    announcer.textContent = '';
    setTimeout(() => {
        announcer.textContent = document.title;
    }, ANNOUNCE_DELAY);
}

mount();
document.addEventListener('turbo:before-render', unmount);
document.addEventListener('turbo:render', () => {
    const root = mount();
    if (root) arrive(root);
});
