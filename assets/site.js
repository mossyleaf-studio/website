import { createApp, h } from 'vue';
import '@fontsource/kalam/latin-700.css';
import '@fontsource-variable/quicksand/wght.css';
import './styles/site/tokens.css';
import './styles/site/base.css';
import DraftBanner from './vue/site/components/DraftBanner.vue';
import NotePage from './vue/site/pages/NotePage.vue';

const root = document.getElementById('site');
const site = JSON.parse(document.getElementById('site-content').textContent);
const { draft, shop, note, admin } = root.dataset;

createApp({
    render: () => [draft === undefined ? null : h(DraftBanner, { current: 'note', links: { shop, note, admin } }), h(NotePage, { site })],
}).mount(root);
