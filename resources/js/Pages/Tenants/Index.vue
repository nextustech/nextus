<script setup>
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';
import UiTable from '../../Components/UiTable.vue';

defineProps({
    tenants: { type: Array, required: true },
    filters: { type: Object, required: true },
});
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Tenants</h1>
            <form method="get" action="/tenants">
                <label>Status
                    <select name="status" :value="filters.status || ''">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </label>
                <button type="submit">Filter</button>
            </form>
            <UiTable>
                <thead><tr><th>Name</th><th>Slug</th><th>Type</th><th>Status</th></tr></thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id">
                        <td><a :href="`/tenants/${tenant.public_id}`">{{ tenant.name }}</a></td>
                        <td>{{ tenant.slug }}</td>
                        <td>{{ tenant.type }}</td>
                        <td>{{ tenant.status }}</td>
                    </tr>
                </tbody>
            </UiTable>
        </UiCard>
    </AppShell>
</template>
