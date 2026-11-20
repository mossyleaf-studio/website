<script setup>
import { computed, provide } from 'vue';
import ShopLayout from './components/ShopLayout.vue';
import AboutPage from './pages/AboutPage.vue';
import ConventionsPage from './pages/ConventionsPage.vue';
import ContactPage from './pages/ContactPage.vue';
import HomePage from './pages/HomePage.vue';
import LegalPage from './pages/LegalPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import ProductPage from './pages/ProductPage.vue';
import ShopPage from './pages/ShopPage.vue';

const props = defineProps({
    layout: { type: Object, required: true },
    data: { type: Object, required: true },
});

provide('navigation', props.layout.navigation);
const home = computed(() => props.layout.navigation.find((entry) => entry.key === 'home').url);
const shop = computed(() => props.layout.navigation.find((entry) => entry.key === 'shop').url);
</script>

<template>
    <ShopLayout :layout="layout" :home="home">
        <HomePage v-if="data.page === 'home'" :data="data" />
        <ShopPage v-else-if="data.page === 'listing'" :data="data" />
        <ProductPage v-else-if="data.page === 'product'" :data="data" :shop="shop" />
        <AboutPage v-else-if="data.page === 'about'" :data="data" />
        <ConventionsPage v-else-if="data.page === 'conventions'" :data="data" />
        <LegalPage v-else-if="data.page === 'legal'" :data="data" />
        <ContactPage v-else-if="data.page === 'contact'" :data="data" />
        <NotFoundPage v-else :home="home" />
    </ShopLayout>
</template>
