<script setup>
import { useForm } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';

defineProps({
    roots: { type: Array, required: true },
    subtree: { type: Array, default: () => [] },
    node: { type: Object, default: null },
});

const reparentForm = useForm({
    new_parent_public_id: '',
});

function reparent(node) {
    reparentForm.post(`/resellers/hierarchy/${node.public_id}/reparent`);
}
</script>

<template>
    <AppShell>
        <UiCard>
            <h1>Reseller hierarchy</h1>
            <ul>
                <li v-for="root in roots" :key="root.id">
                    {{ root.name }} · {{ root.effective_status }}
                </li>
            </ul>
            <div v-if="node">
                <h2>{{ node.name }} subtree</h2>
                <ul>
                    <li v-for="member in subtree" :key="member.id">
                        {{ '—'.repeat(member.depth) }} {{ member.name }} · depth {{ member.depth }}
                    </li>
                </ul>
                <form @submit.prevent="reparent(node)">
                    <label>New parent public ID
                        <input v-model="reparentForm.new_parent_public_id" required />
                    </label>
                    <button type="submit" :disabled="reparentForm.processing">Reparent</button>
                </form>
            </div>
        </UiCard>
    </AppShell>
</template>
