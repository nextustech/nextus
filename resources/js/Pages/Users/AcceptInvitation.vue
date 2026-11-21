<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    authenticated: {
        type: Boolean,
        required: true,
    },
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(window.location.pathname);
}
</script>

<template>
    <main>
        <h1>Accept invitation</h1>
        <p>{{ props.email }}</p>
        <form v-if="!props.authenticated" @submit.prevent="submit">
            <label>Name<input v-model="form.name" required /></label>
            <label>Password<input v-model="form.password" type="password" required /></label>
            <label>Confirm password<input v-model="form.password_confirmation" type="password" required /></label>
            <button type="submit" :disabled="form.processing">Create account</button>
        </form>
        <form v-else @submit.prevent="submit">
            <button type="submit" :disabled="form.processing">Accept invitation</button>
        </form>
    </main>
</template>
