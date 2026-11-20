<script setup>
import { computed } from 'vue';
import ShopLayout from './components/ShopLayout.vue';
import AboutPage from './pages/AboutPage.vue';
import HomePage from './pages/HomePage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';

const props = defineProps({
    layout: { type: Object, required: true },
    data: { type: Object, required: true },
});

const home = computed(() => props.layout.navigation.find((entry) => entry.key === 'home').url);
</script>

<template>
    <ShopLayout :layout="layout" :home="home">
        <HomePage v-if="data.page === 'home'" :data="data" />
        <AboutPage v-else-if="data.page === 'about'" :data="data" />
        <NotFoundPage v-else :home="home" />
    </ShopLayout>
</template>
