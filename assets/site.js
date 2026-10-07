import { createApp, h } from 'vue';
import '@fontsource/gaegu/latin-700.css';
import '@fontsource/kalam/latin-300.css';
import '@fontsource/kalam/latin-400.css';
import './styles/site/tokens.css';
import './styles/site/base.css';
import DraftBanner from './vue/site/components/DraftBanner.vue';
import BetaPage from './vue/site/pages/BetaPage.vue';
import HomePage from './vue/site/pages/HomePage.vue';

const PAGES = { note: HomePage, full: BetaPage };

const root = document.getElementById('site');
const site = JSON.parse(document.getElementById('site-content').textContent);
const page = PAGES[root.dataset.page] ? root.dataset.page : 'note';
const draft = 'draft' in root.dataset;

createApp({
    render: () => [draft ? h(DraftBanner, { page }) : null, h(PAGES[page], { site })],
}).mount(root);
