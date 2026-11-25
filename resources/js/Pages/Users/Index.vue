<script setup>
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';
import UiTable from '../../Components/UiTable.vue';
defineProps({
    users: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Users</h1>
            <UiTable>
                <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>{{ user.name }}</td>
                        <td>{{ user.email }}</td>
                        <td>{{ user.status }}</td>
                    </tr>
                </tbody>
            </UiTable>
        </UiCard>
    </AppShell>
</template>
