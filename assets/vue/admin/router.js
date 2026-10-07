import { createRouter, createWebHistory } from 'vue-router';
import ImagesPage from './pages/ImagesPage.vue';
import LinksPage from './pages/LinksPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import TextsPage from './pages/TextsPage.vue';

export const router = createRouter({
    history: createWebHistory('/admin/'),
    routes: [
        { path: '/', redirect: { name: 'texts' } },
        { path: '/texts', name: 'texts', component: TextsPage },
        { path: '/links', name: 'links', component: LinksPage },
        { path: '/images', name: 'images', component: ImagesPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
});
