import { createApp } from 'vue';
import '@fontsource/kalam/latin-400.css';
import '@fontsource/kalam/latin-700.css';
import './styles/site/tokens.css';
import './styles/site/shop.css';
import ShopApp from './vue/site/ShopApp.vue';
import { createSiteI18n } from './vue/site/i18n.js';

const { layout, data } = JSON.parse(document.getElementById('site-content').textContent);

createApp(ShopApp, { layout, data }).use(createSiteI18n(layout.locale)).mount('#shop');
