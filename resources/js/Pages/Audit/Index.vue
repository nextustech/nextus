<script setup>
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';
import UiTable from '../../Components/UiTable.vue';
defineProps({
    logs: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Audit logs</h1>
            <form method="get" action="/audit-logs">
                <UiField label="Action"><input name="action" :value="filters.action || ''" /></UiField>
                <UiField label="From"><input name="from" type="date" :value="filters.from || ''" /></UiField>
                <UiField label="To"><input name="to" type="date" :value="filters.to || ''" /></UiField>
                <UiButton type="submit">Filter</UiButton>
            </form>
            <UiTable>
                <thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Subject</th><th>IP</th></tr></thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id">
                        <td>{{ log.created_at }}</td>
                        <td>{{ log.actor_type }} {{ log.actor_id || '' }}</td>
                        <td>{{ log.action }}</td>
                        <td>{{ log.subject_type }} {{ log.subject_id || '' }}</td>
                        <td>{{ log.ip_address }}</td>
                    </tr>
                </tbody>
            </UiTable>
        </UiCard>
    </AppShell>
</template>
