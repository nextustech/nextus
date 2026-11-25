<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
});

const variantClasses = {
    default: 'ui-badge--default',
    success: 'ui-badge--success',
    warning: 'ui-badge--warning',
    destructive: 'ui-badge--destructive',
};

const variantClass = computed(() => variantClasses[props.variant] ?? variantClasses.default);
</script>

<template>
    <span class="ui-badge" :class="variantClass">
        <slot />
    </span>
</template>

<style scoped>
.ui-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
}

.ui-badge--default {
    background: #e2e8f0;
    color: #334155;
}

.ui-badge--success {
    background: #dcfce7;
    color: #166534;
}

.ui-badge--warning {
    background: #fef3c7;
    color: #92400e;
}

.ui-badge--destructive {
    background: #fee2e2;
    color: #991b1b;
}
</style>
