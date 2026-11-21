<script setup>
import { useForm } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';

defineProps({
    parent: { type: Object, default: null },
    requiresParentSelection: { type: Boolean, default: false },
    parentDepth: { type: Number, default: 0 },
    autoActivation: { type: Boolean, default: true },
    canOverrideLimit: { type: Boolean, default: false },
});

const form = useForm({
    business_name: '',
    owner_name: '',
    owner_email: '',
    city: '',
    gstin: '',
    note: '',
    parent_public_id: '',
    override_limit: false,
    override_reason: '',
});

function submit() {
    form.post('/resellers/sub-resellers');
}
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Invite a sub-reseller</h1>
            <p>
                The invitee becomes the owner of a <strong>separate tenant</strong>. Only the business
                details below are used; you keep no control over their tenant after creation.
            </p>
            <p v-if="!autoActivation">New children will wait for platform review before activation.</p>
            <p v-if="parentDepth + 1 > 6" class="form-warning" role="note">
                This child will be at level {{ parentDepth + 1 }}. Readability drops beyond six levels;
                the invitation is still allowed.
            </p>

            <form @submit.prevent="submit">
                <UiField v-if="requiresParentSelection" label="Parent reseller public ID">
                    <input v-model="form.parent_public_id" required />
                </UiField>
                <UiField label="Business name">
                    <input v-model="form.business_name" maxlength="100" required />
                </UiField>
                <UiField label="Owner name">
                    <input v-model="form.owner_name" maxlength="100" required />
                </UiField>
                <UiField label="Owner email">
                    <input v-model="form.owner_email" type="email" required />
                </UiField>
                <UiField label="City">
                    <input v-model="form.city" maxlength="100" required />
                </UiField>
                <UiField label="GSTIN (optional)">
                    <input v-model="form.gstin" maxlength="15" />
                </UiField>
                <UiField label="Note (optional)">
                    <textarea v-model="form.note" maxlength="500" rows="3" />
                </UiField>

                <template v-if="canOverrideLimit">
                    <UiField label="Override plan limit">
                        <label class="invite-checkbox">
                            <input v-model="form.override_limit" type="checkbox" />
                            Allow this child even if the parent is at capacity
                        </label>
                    </UiField>
                    <UiField v-if="form.override_limit" label="Override reason (audited)">
                        <textarea v-model="form.override_reason" minlength="20" maxlength="1000" rows="2" required />
                    </UiField>
                </template>

                <p v-if="form.errors.override_reason" class="form-error" role="alert">
                    {{ form.errors.override_reason }}
                </p>
                <p v-if="form.errors.sub_reseller" class="form-error" role="alert">
                    {{ form.errors.sub_reseller }}
                </p>
                <p v-if="form.errors.owner_email" class="form-error" role="alert">
                    {{ form.errors.owner_email }}
                </p>
                <p v-if="form.errors.business_name" class="form-error" role="alert">
                    {{ form.errors.business_name }}
                </p>

                <UiButton type="submit" :disabled="form.processing">Send invitation</UiButton>
            </form>
        </UiCard>
    </AppShell>
</template>
