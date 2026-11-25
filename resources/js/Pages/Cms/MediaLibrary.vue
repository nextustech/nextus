<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';

const props = defineProps({
    media: { type: Array, required: true },
    quota: { type: Object, required: true },
});

const form = useForm({ file: null, alt_text: '' });
const flash = computed(() => usePage().props.flash?.status ?? null);
const quotaPercent = computed(() => Math.min(100, Math.round(props.quota.used_bytes / props.quota.limit_bytes * 100)));

function selectFile(event) {
    form.file = event.target.files?.[0] ?? null;
}

function upload() {
    form.post(route('media.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
}

function updateAlt(item) {
    router.patch(route('media.update', item.id), { alt_text: item.alt_text }, { preserveScroll: true });
}

function remove(item) {
    if (window.confirm(`Move "${item.original_name}" to trash?`)) {
        router.delete(route('media.destroy', item.id), { preserveScroll: true });
    }
}

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <AppShell>
        <div class="mb-4">
            <h1 class="text-2xl font-bold">Media library</h1>
            <p class="text-sm text-gray-600">Tenant-only uploads; maximum 25 MB per file and 2 GB total, including files in the 30-day trash window.</p>
        </div>
        <p v-if="flash" class="mb-4" role="status">{{ flash }}</p>
        <p v-if="quota.warning" class="mb-4 rounded bg-amber-50 p-3 text-amber-900" role="alert">
            Your media library has used at least 80% of its storage quota.
        </p>
        <UiCard class="mb-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <strong>Storage used: {{ formatBytes(quota.used_bytes) }} / {{ formatBytes(quota.limit_bytes) }}</strong>
                <span>{{ quotaPercent }}%</span>
            </div>
            <progress class="w-full" :value="quota.used_bytes" :max="quota.limit_bytes" aria-label="Media storage quota" />
            <form class="mt-4 grid grid-cols-1 items-end gap-3 md:grid-cols-3" @submit.prevent="upload">
                <UiField label="File (JPG, PNG, WebP, SVG, GIF, MP4, PDF)">
                    <input type="file" accept=".jpg,.jpeg,.png,.webp,.svg,.gif,.mp4,.pdf" required @change="selectFile">
                </UiField>
                <UiField label="Alt text (required for images)">
                    <input v-model="form.alt_text" class="ui-input" maxlength="255">
                </UiField>
                <UiButton type="submit" :disabled="form.processing || !form.file">
                    {{ form.processing ? 'Uploading…' : 'Upload file' }}
                </UiButton>
                <p v-if="form.errors.file" class="text-sm text-red-700 md:col-span-3">{{ form.errors.file }}</p>
                <p v-if="form.errors.alt_text" class="text-sm text-red-700 md:col-span-3">{{ form.errors.alt_text }}</p>
            </form>
        </UiCard>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <UiCard v-for="item in media" :key="item.id">
                <img
                    v-if="item.type === 'image'"
                    :src="item.url"
                    :alt="item.alt_text || ''"
                    class="mb-3 max-h-48 w-full rounded object-contain"
                >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <strong class="break-all">{{ item.original_name }}</strong>
                    <span class="shrink-0 text-xs text-gray-500">{{ formatBytes(item.size) }}</span>
                </div>
                <p class="text-xs text-gray-500">{{ item.mime_type }}<span v-if="item.width"> · {{ item.width }}×{{ item.height }}</span></p>
                <label v-if="item.type === 'image'" class="mt-3 block text-sm">
                    Alt text
                    <input v-model="item.alt_text" class="ui-input mt-1" maxlength="255" @change="updateAlt(item)">
                </label>
                <p v-if="item.references.length" class="mt-2 text-xs text-gray-600">
                    Used by: {{ item.references.join(', ') }}
                </p>
                <div class="mt-3 flex items-center justify-between">
                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-700">Open file</a>
                    <button type="button" class="text-sm text-red-700" @click="remove(item)">Move to trash</button>
                </div>
            </UiCard>
            <UiCard v-if="media.length === 0" class="text-gray-500">No uploaded media yet.</UiCard>
        </div>
    </AppShell>
</template>
