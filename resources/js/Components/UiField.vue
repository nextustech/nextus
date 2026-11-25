<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <label class="ui-field">
        <span>{{ label }}</span>
        <slot />
    </label>
</template>
