<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <main>
        <h1>Sign in</h1>
        <form @submit.prevent="submit">
            <label>Email<input v-model="form.email" type="email" required /></label>
            <label>Password<input v-model="form.password" type="password" required /></label>
            <label><input v-model="form.remember" type="checkbox" /> Remember me</label>
            <p v-if="form.errors.email">{{ form.errors.email }}</p>
            <button type="submit" :disabled="form.processing">Sign in</button>
        </form>
    </main>
</template>
