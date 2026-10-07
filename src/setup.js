import { createApp } from 'vue';
import '@fontsource/patua-one/latin-400.css';
import '@fontsource/alegreya-sans/latin-400.css';
import '@fontsource/alegreya-sans/latin-500.css';
import './styles/tokens.css';
import './styles/base.css';

export function mount(page) {
    createApp(page).mount('#app');
}
