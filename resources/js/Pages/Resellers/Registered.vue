<script setup>
defineProps({
    application: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <main>
        <h1>Application received</h1>
        <p>Status: {{ application.status }}</p>
        <p>Review target: {{ application.sla_due_at }}</p>
    </main>
</template>
