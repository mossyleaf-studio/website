import { createApp } from 'vue';
import '@fontsource/patua-one/latin-400.css';
import '@fontsource/alegreya-sans/latin-400.css';
import '@fontsource/alegreya-sans/latin-500.css';
import './styles/site/tokens.css';
import './styles/site/base.css';
import BetaPage from './vue/site/pages/BetaPage.vue';
import HomePage from './vue/site/pages/HomePage.vue';

const PAGES = { home: HomePage, beta: BetaPage };

const root = document.getElementById('site');
const site = JSON.parse(document.getElementById('site-content').textContent);

createApp(PAGES[root.dataset.page] ?? HomePage, { site }).mount(root);
