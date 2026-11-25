<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiTable from '../../Components/UiTable.vue';

defineProps({
    parent: { type: Object, default: null },
    descendants: { type: Array, required: true },
    pendingInvitations: { type: Array, required: true },
    canInvite: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
    autoActivation: { type: Boolean, default: true },
    maxSubResellers: { type: Number, default: null },
});

const page = usePage();
const viewMode = ref('tree');
const selected = ref(null);

function indent(child) {
    return viewMode.value === 'tree' ? Math.max(0, child.depth - 1) * 24 : 0;
}

function select(child) {
    selected.value = selected.value?.public_id === child.public_id ? null : child;
}

function resend(invitation) {
    router.post(`/resellers/sub-resellers/${invitation.public_id}/resend`);
}

function approve(child) {
    router.post(`/resellers/sub-resellers/${child.public_id}/approve`);
}
</script>

<template>
    <AppShell>
        <UiCard>
            <div class="sub-resellers__header">
                <div>
                    <h1>Sub-resellers</h1>
                    <p v-if="parent" class="sub-resellers__hint">
                        Network of {{ parent.name }}. You can see your subtree, but never change a child's
                        settings, catalog, staff or finances.
                    </p>
                </div>
                <Link v-if="canInvite" href="/resellers/sub-resellers/create" class="ui-button">
                    Invite sub-reseller
                </Link>
            </div>

            <p v-if="page.props.flash?.status" class="sub-resellers__status" role="status">
                {{ page.props.flash.status }}
            </p>

            <p class="sub-resellers__hint">
                {{ autoActivation
                    ? 'Healthy parents activate invited children automatically.'
                    : 'Auto-activation is off — new children wait for platform review.' }}
                <span v-if="maxSubResellers !== null"> Direct-child limit: {{ maxSubResellers }}.</span>
            </p>

            <details class="sub-resellers__explainer">
                <summary>How hierarchy works</summary>
                <p>
                    Hierarchy grants visibility and aggregate reporting only. A parent cannot suspend,
                    edit, or bill a child tenant. Commission splits appear here once reporting is connected.
                </p>
            </details>

            <div class="sub-resellers__toolbar">
                <h2>Your subtree</h2>
                <div class="sub-resellers__toggle" role="group" aria-label="Subtree view">
                    <button type="button" :aria-pressed="viewMode === 'tree'" @click="viewMode = 'tree'">Tree</button>
                    <button type="button" :aria-pressed="viewMode === 'list'" @click="viewMode = 'list'">List</button>
                </div>
            </div>

            <p v-if="!descendants.length" class="sub-resellers__empty">
                No sub-resellers yet — invite your first partner.
            </p>
            <UiTable v-else>
                <thead>
                    <tr>
                        <th>Business</th><th>Depth</th><th>Status</th><th>Review</th>
                        <th>Orders 30d</th><th>GMV 30d</th><th>Commission</th><th>Joined</th><th />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="child in descendants" :key="child.public_id">
                        <tr>
                            <td>
                                <button type="button" class="sub-resellers__node" :style="{ marginLeft: `${indent(child)}px` }" @click="select(child)">
                                    {{ viewMode === 'tree' ? `${'—'.repeat(Math.max(0, child.depth - 1))} ` : '' }}{{ child.name }}
                                </button>
                            </td>
                            <td>{{ child.depth }}</td>
                            <td>{{ child.status }}</td>
                            <td>{{ child.review_status }}</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td>{{ child.joined_at }}</td>
                            <td>
                                <UiButton
                                    v-if="canApprove && child.review_status === 'pending'"
                                    type="button"
                                    @click="approve(child)"
                                >
                                    Approve
                                </UiButton>
                            </td>
                        </tr>
                        <tr v-if="selected?.public_id === child.public_id" class="sub-resellers__detail">
                            <td colspan="9">
                                <strong>Read-only profile</strong> — Owner: {{ child.owner_name || 'Not provided' }}
                                ({{ child.owner_email || 'No email' }}), {{ child.city || 'City not provided' }}.
                                GSTIN: {{ child.gstin || 'Not provided' }}.
                                <span v-if="child.invite_note">Note: {{ child.invite_note }}</span>
                                <span v-else> No invitation note.</span>
                                Aggregate reporting is not connected yet.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </UiTable>

            <h2>Pending invitations</h2>
            <p v-if="!pendingInvitations.length" class="sub-resellers__hint">No invitations awaiting acceptance.</p>
            <ul v-else class="sub-resellers__invitations">
                <li v-for="invitation in pendingInvitations" :key="invitation.id">
                    {{ invitation.business_name || invitation.email }} — {{ invitation.email }}
                    <UiButton v-if="canInvite" type="button" @click="resend(invitation)">Resend</UiButton>
                </li>
            </ul>
        </UiCard>
    </AppShell>
</template>
