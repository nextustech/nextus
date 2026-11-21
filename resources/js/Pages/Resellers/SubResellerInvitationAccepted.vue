<script setup>
import UiCard from '../../Components/UiCard.vue';

defineProps({
    businessName: { type: String, required: true },
    ownerEmail: { type: String, required: true },
    pendingReview: { type: Boolean, default: true },
});
</script>

<template>
    <div class="accept-invitation">
        <UiCard>
            <h1>Invitation accepted</h1>
            <p>
                The owner account for <strong>{{ businessName }}</strong> was created for
                {{ ownerEmail }} in its own tenant.
            </p>
            <p v-if="pendingReview" role="note">
                This sub-reseller is pending platform review. You will be able to sign in once
                provisioning is complete.
            </p>
            <p v-else>
                Your tenant is ready. Return to the invitation email or sign in on your tenant URL.
            </p>
        </UiCard>
    </div>
</template>
