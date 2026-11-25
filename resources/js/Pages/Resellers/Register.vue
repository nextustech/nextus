<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    legal_name: '',
    slug: '',
    email: '',
    phone: '',
    pan: '',
    gstin: '',
    gstin_unregistered_declaration: false,
    business_address: '',
    bank_proof: null,
    aup_accepted: false,
    dpdp_accepted: false,
});

function submit() {
    form.post('/resellers/register');
}
</script>

<template>
    <main class="reseller-registration">
        <h1>Register reseller</h1>
        <form @submit.prevent="submit" enctype="multipart/form-data">
            <label>Business name<input v-model="form.name" required /></label>
            <label>Legal name<input v-model="form.legal_name" /></label>
            <label>Subdomain<input v-model="form.slug" pattern="[a-z0-9-]{3,60}" required /></label>
            <label>Email<input v-model="form.email" type="email" required /></label>
            <label>Phone<input v-model="form.phone" required /></label>
            <label>PAN<input v-model="form.pan" maxlength="10" required /></label>
            <label>GSTIN<input v-model="form.gstin" maxlength="15" /></label>
            <label><input v-model="form.gstin_unregistered_declaration" type="checkbox" /> GSTIN unregistered declaration</label>
            <label>Business address<textarea v-model="form.business_address" required></textarea></label>
            <label>Bank proof<input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="form.bank_proof = $event.target.files[0]" required /></label>
            <label><input v-model="form.aup_accepted" type="checkbox" required /> I accept the acceptable use policy</label>
            <label><input v-model="form.dpdp_accepted" type="checkbox" required /> I accept the DPDP terms</label>
            <button type="submit" :disabled="form.processing">Submit application</button>
        </form>
    </main>
</template>
