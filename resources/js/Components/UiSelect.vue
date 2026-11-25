<script setup>
defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <select
        class="ui-select"
        :value="modelValue"
        v-bind="$attrs"
        @change="emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>

<style scoped>
.ui-select {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    background: #fff;
    padding: 0.5rem 0.75rem;
}
</style>
