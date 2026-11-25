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
    <input
        class="ui-input"
        :value="modelValue"
        v-bind="$attrs"
        @input="emit('update:modelValue', $event.target.value)"
    />
</template>

<style scoped>
.ui-input {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
}
</style>
