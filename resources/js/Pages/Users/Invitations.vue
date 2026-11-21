<script setup>
import { useForm } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';

defineProps({
    invitations: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    email: '',
    roles: [],
});

function submit() {
    form.post('/invitations');
}
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Invitations</h1>
            <form @submit.prevent="submit">
                <UiField label="Email"><input v-model="form.email" type="email" required /></UiField>
                <UiField label="Role"><input v-model="form.roles[0]" required /></UiField>
                <UiButton type="submit" :disabled="form.processing">Invite</UiButton>
            </form>
            <ul>
                <li v-for="invitation in invitations" :key="invitation.id">
                    {{ invitation.email }} - {{ invitation.status }}
                </li>
            </ul>
        </UiCard>
    </AppShell>
</template>
