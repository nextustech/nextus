<script setup>
defineProps({
    appName: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <main class="inertia-welcome">
        <h1>{{ appName }}</h1>
        <p>Phase 1 foundation is running.</p>
    </main>
</template>
