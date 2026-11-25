<script setup>
import { useForm } from '@inertiajs/vue3';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';

const props = defineProps({
    token: { type: String, required: true },
    businessName: { type: String, required: true },
    ownerEmail: { type: String, required: true },
    inviter: { type: String, default: null },
    authenticated: { type: Boolean, default: false },
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/sub-reseller-invitations/${props.token}/accept`);
}
</script>

<template>
    <div class="accept-invitation">
        <UiCard>
            <h1>Accept sub-reseller invitation</h1>
            <p v-if="inviter">{{ inviter }} invited you to run <strong>{{ businessName }}</strong>.</p>
            <p v-else>You were invited to run <strong>{{ businessName }}</strong>.</p>
            <p>This creates a new owner account in your own tenant ({{ ownerEmail }}).</p>
            <p v-if="authenticated" class="accept-invitation__notice" role="note">
                You are signed in to another tenant. Acceptance creates a separate account here instead
                of merging accounts.
            </p>

            <form @submit.prevent="submit()">
                <UiField label="Your name">
                    <input v-model="form.name" maxlength="100" required />
                </UiField>
                <UiField label="Password">
                    <input v-model="form.password" type="password" minlength="8" required />
                </UiField>
                <UiField label="Confirm password">
                    <input v-model="form.password_confirmation" type="password" minlength="8" required />
                </UiField>
                <p v-if="form.errors.name || form.errors.password" class="form-error" role="alert">
                    {{ form.errors.name || form.errors.password }}
                </p>
                <UiButton type="submit" :disabled="form.processing">Create my tenant</UiButton>
            </form>
        </UiCard>
    </div>
</template>
