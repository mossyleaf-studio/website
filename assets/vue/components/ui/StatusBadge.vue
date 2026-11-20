<script setup>
defineProps({
    tone: { type: String, default: 'neutral' },
});
</script>

<template>
    <span :class="['status-badge', `status-badge--${tone}`]"><slot /></span>
</template>

<style scoped>
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: 0.125rem var(--space-2);
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-surface);
    color: var(--color-muted);
    font-size: var(--font-size-xs);
    font-weight: 600;
    white-space: nowrap;
}

.status-badge--warning { border-color: var(--color-warning); background: var(--color-warning-soft); color: var(--color-ink); }
.status-badge--success { border-color: var(--color-success); background: var(--color-success-soft); color: var(--color-ink); }
.status-badge--danger { border-color: var(--color-danger); background: var(--color-danger-soft); color: var(--color-ink); }
</style>
