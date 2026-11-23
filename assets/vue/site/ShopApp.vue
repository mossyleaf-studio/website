<script setup>
import { computed, provide } from 'vue';
import ShopLayout from './components/ShopLayout.vue';
import AboutPage from './pages/AboutPage.vue';
import CartPage from './pages/CartPage.vue';
import CategoryPage from './pages/CategoryPage.vue';
import CheckoutPage from './pages/CheckoutPage.vue';
import ConventionsPage from './pages/ConventionsPage.vue';
import ContactPage from './pages/ContactPage.vue';
import HomePage from './pages/HomePage.vue';
import LegalPage from './pages/LegalPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import OrderPage from './pages/OrderPage.vue';
import ProductPage from './pages/ProductPage.vue';

const props = defineProps({
    layout: { type: Object, required: true },
    data: { type: Object, required: true },
});

provide('navigation', props.layout.navigation);
const home = computed(() => props.layout.navigation.find((entry) => entry.key === 'home').url);
</script>

<template>
    <ShopLayout :layout="layout" :home="home" :cart-current="data.page === 'cart'">
        <HomePage v-if="data.page === 'home'" :data="data" />
        <CategoryPage v-else-if="data.page === 'category'" :data="data" />
        <ProductPage v-else-if="data.page === 'product'" :data="data" :home="home" :cart-url="layout.cartUrl" />
        <CartPage v-else-if="data.page === 'cart'" :data="data" />
        <CheckoutPage v-else-if="data.page === 'checkout'" :data="data" />
        <OrderPage v-else-if="data.page === 'order'" :data="data" :cart-url="layout.cartUrl" />
        <AboutPage v-else-if="data.page === 'about'" :data="data" />
        <ConventionsPage v-else-if="data.page === 'conventions'" :data="data" />
        <LegalPage v-else-if="data.page === 'legal'" :data="data" />
        <ContactPage v-else-if="data.page === 'contact'" :data="data" />
        <NotFoundPage v-else :home="home" />
    </ShopLayout>
</template>
