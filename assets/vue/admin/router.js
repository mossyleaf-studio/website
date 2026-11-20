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
        {
            path: '/products',
            children: [
                { path: '', name: 'products', component: () => import('./pages/ProductsPage.vue') },
                { path: ':id', name: 'product', component: () => import('./pages/ProductPage.vue'), props: true },
            ],
        },
        { path: '/conventions', name: 'conventions', component: () => import('./pages/ConventionsPage.vue') },
        { path: '/legal', name: 'legal', component: () => import('./pages/LegalPage.vue') },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
});
