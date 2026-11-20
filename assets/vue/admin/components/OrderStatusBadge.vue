<script setup>
import { useI18n } from 'vue-i18n';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { statusTone } from '../orders.js';

const { t } = useI18n();

defineProps({
    status: { type: String, required: true },
});
</script>

<template>
    <StatusBadge :tone="statusTone(status)">{{ t(`admin.orders.status.${status}`) }}</StatusBadge>
</template>
