import { createApp } from 'vue';
import '@fontsource/gaegu/latin-700.css';
import '@fontsource/kalam/latin-300.css';
import '@fontsource/kalam/latin-400.css';
import './styles/site/tokens.css';
import './styles/site/base.css';
import BetaPage from './vue/site/pages/BetaPage.vue';
import HomePage from './vue/site/pages/HomePage.vue';

const PAGES = { home: HomePage, beta: BetaPage };

const root = document.getElementById('site');
const site = JSON.parse(document.getElementById('site-content').textContent);

createApp(PAGES[root.dataset.page] ?? HomePage, { site }).mount(root);
