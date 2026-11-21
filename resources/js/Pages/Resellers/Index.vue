<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiTable from '../../Components/UiTable.vue';

defineProps({
    applications: { type: Array, required: true },
    canApprove: { type: Boolean, default: false },
});

const page = usePage();
const expanded = ref(null);
const provisioning = ref({});

function toggle(application) {
    expanded.value = expanded.value === application.public_id ? null : application.public_id;
}

function approve(application) {
    const base = application.type === 'sub_reseller'
        ? `/resellers/sub-resellers/${application.public_id}/approve`
        : `/resellers/${application.public_id}/approve`;

    router.post(base, { review_notes: 'Reviewed by platform admin.' }, {
        onStart: () => {
            provisioning.value[application.public_id] = 'Validating → provisioning';
        },
        onFinish: () => {
            delete provisioning.value[application.public_id];
        },
    });
}
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Reseller onboarding queue</h1>
            <p v-if="page.props.flash?.status" role="status">{{ page.props.flash.status }}</p>
            <UiTable>
                <thead>
                    <tr>
                        <th>Business</th><th>Applicant</th><th>Source</th><th>KYC</th>
                        <th>Completeness</th><th>Risk flags</th><th>Status</th><th>Submitted</th>
                        <th>SLA due</th><th />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="application in applications" :key="application.public_id">
                        <tr>
                            <td>
                                <button type="button" class="sub-resellers__node" @click="toggle(application)">
                                    {{ application.name }}
                                </button>
                            </td>
                            <td>{{ application.email }}</td>
                            <td>
                                {{ application.type === 'sub_reseller'
                                    ? `Child of ${application.parent_name || 'unknown'}`
                                    : 'Direct application' }}
                            </td>
                            <td>{{ application.kyc_status }}</td>
                            <td>{{ application.completeness }}%</td>
                            <td>
                                <span v-if="!application.risk_flags.length">—</span>
                                <span v-else class="queue-flags">
                                    <span v-for="flag in application.risk_flags" :key="flag" class="queue-flag">
                                        {{ flag.replace('_', ' ') }}
                                    </span>
                                </span>
                            </td>
                            <td>
                                {{ application.review_status }}
                                <span v-if="provisioning[application.public_id]" class="sub-resellers__hint">
                                    ({{ provisioning[application.public_id] }})
                                </span>
                            </td>
                            <td>{{ application.submitted_age }}</td>
                            <td>{{ application.review_sla_due_at }}</td>
                            <td>
                                <UiButton
                                    v-if="canApprove && application.review_status === 'pending'"
                                    type="button"
                                    :disabled="Boolean(provisioning[application.public_id])"
                                    @click="approve(application)"
                                >
                                    Approve
                                </UiButton>
                            </td>
                        </tr>
                        <tr v-if="expanded === application.public_id" class="queue-detail">
                            <td colspan="10">
                                <strong>Application details</strong> —
                                Owner: {{ application.details.owner_name || 'Not provided' }};
                                city: {{ application.details.city || 'Not provided' }};
                                GSTIN: {{ application.details.gstin || 'Not provided' }};
                                PAN: {{ application.details.pan || 'Not provided' }};
                                address: {{ application.details.business_address || 'Not provided' }}.
                                <span v-if="application.details.note"> Note: {{ application.details.note }}.</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </UiTable>
        </UiCard>
    </AppShell>
</template>
