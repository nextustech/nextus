<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    email: props.email,
    token: props.token,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password');
}
</script>

<template>
    <main>
        <h1>Choose a new password</h1>
        <form @submit.prevent="submit">
            <input v-model="form.email" type="hidden" />
            <input v-model="form.token" type="hidden" />
            <label>Password<input v-model="form.password" type="password" required /></label>
            <label>Confirm password<input v-model="form.password_confirmation" type="password" required /></label>
            <p v-if="form.errors.email">{{ form.errors.email }}</p>
            <p v-if="form.errors.password">{{ form.errors.password }}</p>
            <button type="submit" :disabled="form.processing">Reset password</button>
        </form>
    </main>
</template>
