<script setup>
import { computed } from 'vue';
import ShopLayout from './components/ShopLayout.vue';
import AboutPage from './pages/AboutPage.vue';
import HomePage from './pages/HomePage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import ProductPage from './pages/ProductPage.vue';
import ShopPage from './pages/ShopPage.vue';

const props = defineProps({
    layout: { type: Object, required: true },
    data: { type: Object, required: true },
});

const home = computed(() => props.layout.navigation.find((entry) => entry.key === 'home').url);
const shop = computed(() => props.layout.navigation.find((entry) => entry.key === 'shop').url);
</script>

<template>
    <ShopLayout :layout="layout" :home="home">
        <HomePage v-if="data.page === 'home'" :data="data" />
        <ShopPage v-else-if="data.page === 'listing'" :data="data" />
        <ProductPage v-else-if="data.page === 'product'" :data="data" :shop="shop" />
        <AboutPage v-else-if="data.page === 'about'" :data="data" />
        <NotFoundPage v-else :home="home" />
    </ShopLayout>
</template>
