<script setup>
import { useForm } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    auditLogs: { type: Array, required: true },
});

const suspendForm = useForm({ reason: '' });
const terminateForm = useForm({ reason: '' });

function suspendTenant() {
    suspendForm.post(`/tenants/${props.tenant.public_id}/suspend`);
}

function resumeTenant() {
    useForm({}).post(`/tenants/${props.tenant.public_id}/resume`);
}

function terminateTenant() {
    terminateForm.post(`/tenants/${props.tenant.public_id}/terminate`);
}
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>{{ tenant.name }}</h1>
            <p>{{ tenant.slug }} · {{ tenant.type }} · {{ tenant.status }}</p>
            <form @submit.prevent="suspendTenant">
                <label>Suspension reason<textarea v-model="suspendForm.reason" minlength="20" required></textarea></label>
                <button type="submit" :disabled="suspendForm.processing">Suspend</button>
            </form>
            <button type="button" @click="resumeTenant">Resume</button>
            <form @submit.prevent="terminateTenant">
                <label>Termination reason<textarea v-model="terminateForm.reason" minlength="20" required></textarea></label>
                <button type="submit" :disabled="terminateForm.processing">Terminate</button>
            </form>
            <h2>Status history</h2>
            <ul>
                <li v-for="log in auditLogs" :key="log.id">{{ log.created_at }} · {{ log.action }}</li>
            </ul>
        </UiCard>
    </AppShell>
</template>
