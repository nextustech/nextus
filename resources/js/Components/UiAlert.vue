<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'info',
    },
    message: {
        type: String,
        required: true,
    },
});

const variantClasses = {
    info: 'ui-alert--info',
    success: 'ui-alert--success',
    warning: 'ui-alert--warning',
    error: 'ui-alert--error',
};

const variantClass = computed(() => variantClasses[props.type] ?? variantClasses.info);
</script>

<template>
    <p class="ui-alert" :class="variantClass" role="status">
        {{ message }}
    </p>
</template>

<style scoped>
.ui-alert {
    border: 1px solid;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
}

.ui-alert--info {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
}

.ui-alert--success {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.ui-alert--warning {
    border-color: #fde68a;
    background: #fffbeb;
    color: #92400e;
}

.ui-alert--error {
    border-color: #fecaca;
    background: #fef2f2;
    color: #991b1b;
}
</style>
