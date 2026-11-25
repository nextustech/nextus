<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';
import UiButton from '../../Components/UiButton.vue';
import UiField from '../../Components/UiField.vue';
import UiInput from '../../Components/UiInput.vue';
import UiSelect from '../../Components/UiSelect.vue';

const props = defineProps({
    theme: { type: Object, required: true },
    gallery: { type: Object, required: true },
    fontPairs: { type: Object, required: true },
    radiusPresets: { type: Object, required: true },
    shadowPresets: { type: Object, required: true },
    liveTokens: { type: Object, required: true },
    stagedTokens: { type: Object, required: true },
    hasStagedChanges: { type: Boolean, default: false },
    contrastWarnings: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status ?? null);

const tokens = ref({ ...props.stagedTokens });
const previewMode = ref('staged');

const dirty = computed(
    () => JSON.stringify(tokens.value) !== JSON.stringify(props.stagedTokens),
);

const previewDoc = computed(() => {
    const href = previewMode.value === 'staged' ? '/branding/preview-css?staged=1' : '/branding/preview-css';
    return `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="${href}">
<style>
  body { font-family: var(--sf-font-body); margin: 0; padding: 24px; background: #fff; color: #111827; }
  .banner { background: var(--sf-brand-500); color: #fff; padding: 10px 16px; border-radius: var(--sf-radius); margin-bottom: 24px; }
  h1 { font-family: var(--sf-font-heading); color: var(--sf-brand-600); font-size: calc(2rem * var(--sf-font-scale)); margin: 0 0 8px; }
  p { color: var(--sf-brand-700); margin: 0 0 24px; }
  .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
  .btn { background: var(--sf-brand-600); color: #fff; padding: 10px 20px; border-radius: var(--sf-radius); box-shadow: var(--sf-shadow); border: 0; font-weight: 600; }
  .badge { background: var(--sf-accent); color: #fff; padding: 4px 10px; border-radius: var(--sf-radius); font-size: 12px; }
  .card { border: 1px solid #e5e7eb; border-radius: var(--sf-radius); box-shadow: var(--sf-shadow); padding: 16px; max-width: 320px; }
</style></head><body>
  <div class="banner">${tokens.value.banner_text || 'Announcement bar'}</div>
  <h1>Your storefront headline</h1>
  <p>Body copy rendered with the tenant font pair and radius/shadow presets.</p>
  <div class="row">
    <button class="btn">Primary CTA</button>
    <span class="badge">Accent badge</span>
  </div>
  <div class="card" style="margin-top: 24px"><strong>Card</strong><br>Radius + shadow presets apply here.</div>
</body></html>`;
});

function stage() {
    router.put(route('branding.update'), { tokens: tokens.value }, { preserveScroll: true });
}

function publish() {
    router.post(route('branding.publish'), {}, { preserveScroll: true });
}

function resetAll() {
    if (!window.confirm('Reset every customization token to template defaults?')) return;
    router.post(route('branding.reset'), {}, { preserveScroll: true });
}

function resetToken(key) {
    router.post(route('branding.reset'), { keys: [key] }, { preserveScroll: true });
}

function switchTemplate(key) {
    if (key === props.theme.template) return;
    router.post(route('branding.template'), { template: key }, { preserveScroll: true });
}
</script>

<template>
    <AppShell>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Branding &amp; themes</h1>
                <p class="text-sm text-gray-500">
                    Template: {{ theme.name }}
                    <span
                        v-if="hasStagedChanges"
                        class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800"
                    >Staged changes pending</span>
                    <span
                        v-else
                        class="ml-2 rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800"
                    >Live</span>
                </p>
            </div>
            <div class="flex gap-2">
                <UiButton type="button" class="bg-gray-200 text-gray-800 hover:bg-gray-300" @click="resetAll">
                    Reset all
                </UiButton>
                <UiButton type="button" class="bg-blue-600 text-white hover:bg-blue-700" :disabled="!dirty" @click="stage">
                    Stage changes
                </UiButton>
                <UiButton
                    type="button"
                    class="bg-green-600 text-white hover:bg-green-700"
                    :disabled="!hasStagedChanges"
                    @click="publish"
                >
                    Publish
                </UiButton>
            </div>
        </div>

        <div
            v-if="flash"
            class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            role="status"
        >
            {{ flash }}
        </div>

        <div
            v-for="warning in contrastWarnings"
            :key="warning"
            class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            role="alert"
        >
            {{ warning }}
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <UiCard>
                    <h2 class="mb-3 text-lg font-semibold">Starter templates</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <button
                            v-for="(template, key) in gallery"
                            :key="key"
                            type="button"
                            class="rounded border p-3 text-left text-sm transition"
                            :class="key === theme.template
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-400'"
                            @click="switchTemplate(key)"
                        >
                            <span class="block font-semibold">{{ template.name }}</span>
                            <span class="block text-xs text-gray-500">{{ template.description }}</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Switching a template stages its default tokens. Your pages and content are preserved.
                    </p>
                </UiCard>

                <UiCard>
                    <h2 class="mb-3 text-lg font-semibold">Brand tokens</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <UiField label="Primary color">
                            <div class="flex items-center gap-2">
                                <input v-model="tokens.color_primary" type="color" class="h-10 w-10 cursor-pointer border-0 p-0" aria-label="Primary color picker">
                                <UiInput v-model="tokens.color_primary" />
                            </div>
                        </UiField>
                        <UiField label="Secondary color">
                            <div class="flex items-center gap-2">
                                <input v-model="tokens.color_secondary" type="color" class="h-10 w-10 cursor-pointer border-0 p-0" aria-label="Secondary color picker">
                                <UiInput v-model="tokens.color_secondary" />
                            </div>
                        </UiField>
                        <UiField label="Accent color">
                            <div class="flex items-center gap-2">
                                <input v-model="tokens.color_accent" type="color" class="h-10 w-10 cursor-pointer border-0 p-0" aria-label="Accent color picker">
                                <UiInput v-model="tokens.color_accent" />
                            </div>
                        </UiField>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <UiField label="Font pair">
                            <UiSelect v-model="tokens.font_pair">
                                <option v-for="(pair, key) in fontPairs" :key="key" :value="key">{{ pair.label }}</option>
                            </UiSelect>
                        </UiField>
                        <UiField label="Radius preset">
                            <UiSelect v-model="tokens.radius_preset">
                                <option v-for="(px, key) in radiusPresets" :key="key" :value="key">{{ key }} ({{ px }})</option>
                            </UiSelect>
                        </UiField>
                        <UiField label="Shadow preset">
                            <UiSelect v-model="tokens.shadow_preset">
                                <option v-for="(_, key) in shadowPresets" :key="key" :value="key">{{ key }}</option>
                            </UiSelect>
                        </UiField>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4">
                        <UiField label="Announcement banner text">
                            <UiInput v-model="tokens.banner_text" placeholder="Optional banner shown above the header" />
                        </UiField>
                        <UiField label="Logo path (light)">
                            <UiInput v-model="tokens.logo_path" placeholder="/media/logo-light.svg" />
                        </UiField>
                        <UiField label="Logo path (dark)">
                            <UiInput v-model="tokens.logo_dark_path" placeholder="/media/logo-dark.svg" />
                        </UiField>
                        <UiField label="Favicon path">
                            <UiInput v-model="tokens.favicon_path" placeholder="/media/favicon.ico" />
                        </UiField>
                        <UiField label="Custom CSS (50 KB max — no @import or external urls)">
                            <textarea
                                v-model="tokens.custom_css"
                                rows="4"
                                class="ui-input font-mono text-xs"
                                placeholder=".promo { color: red; }"
                            />
                        </UiField>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <UiButton type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200" @click="tokens = { ...stagedTokens }">
                            Discard edits
                        </UiButton>
                        <UiButton type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200" @click="resetToken('color_primary')">
                            Reset primary
                        </UiButton>
                        <UiButton type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200" @click="resetToken('color_accent')">
                            Reset accent
                        </UiButton>
                        <UiButton type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200" @click="resetToken('font_pair')">
                            Reset font
                        </UiButton>
                        <UiButton type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200" @click="resetToken('custom_css')">
                            Reset custom CSS
                        </UiButton>
                    </div>
                </UiCard>
            </div>

            <UiCard class="self-start">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Live preview</h2>
                    <div class="flex gap-2 text-sm">
                        <label class="flex items-center gap-1">
                            <input v-model="previewMode" type="radio" value="staged" class="accent-blue-600">
                            Staged
                        </label>
                        <label class="flex items-center gap-1">
                            <input v-model="previewMode" type="radio" value="live" class="accent-blue-600">
                            Live
                        </label>
                    </div>
                </div>
                <iframe
                    :srcdoc="previewDoc"
                    title="Storefront theme preview"
                    class="h-[480px] w-full rounded border border-gray-200 bg-white"
                    sandbox="allow-same-origin"
                />
                <p class="mt-2 text-xs text-gray-500">
                    Staged preview is visible only in this editing session. Visitors keep seeing the live theme until you publish.
                </p>
            </UiCard>
        </div>
    </AppShell>
</template>
