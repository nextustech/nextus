<script setup>
import { usePage } from '@inertiajs/vue3';
import AppShell from '../Layouts/AppShell.vue';
import UiCard from '../Components/UiCard.vue';
const page = usePage();
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Dashboard</h1>
            <p>Signed in as {{ page.props.auth.user.email }}</p>
        </UiCard>
    </AppShell>
</template>
