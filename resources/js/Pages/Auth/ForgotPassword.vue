<script setup>
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({ email: '' });

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <main>
        <h1>Reset password</h1>
        <p v-if="page.props.flash.status">{{ page.props.flash.status }}</p>
        <form @submit.prevent="submit">
            <label>Email<input v-model="form.email" type="email" required /></label>
            <p v-if="form.errors.email">{{ form.errors.email }}</p>
            <button type="submit" :disabled="form.processing">Send reset link</button>
        </form>
    </main>
</template>
