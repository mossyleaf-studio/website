<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseCents, printCents } from '../../admin/amount.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    max: { type: Number, default: Number.MAX_SAFE_INTEGER },
});

const model = defineModel({ type: Number, default: null });
const invalid = defineModel('invalid', { type: Boolean, default: false });

const { locale } = useI18n();
const separator = () => (locale.value === 'fr' ? ',' : '.');
const text = ref(printCents(model.value, separator()));

watch(model, (cents) => {
    if (parseCents(text.value) !== cents) {
        text.value = printCents(cents, separator());
    }
});

function update(event) {
    text.value = event.target.value;
    const cents = parseCents(text.value, props.max);
    invalid.value = cents === false;
    if (cents !== false) {
        model.value = cents;
    }
}
</script>

<template>
    <input v-bind="$attrs" :value="text" type="text" inputmode="decimal" autocomplete="off" :aria-invalid="invalid ? 'true' : null" @input="update" />
</template>
