<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';
import UiButton from '../../Components/UiButton.vue';
import UiField from '../../Components/UiField.vue';
import UiInput from '../../Components/UiInput.vue';
import UiSelect from '../../Components/UiSelect.vue';

const props = defineProps({
    page: { type: Object, default: null },
    catalog: { type: Object, required: true },
    reservedSlugs: { type: Array, default: () => [] },
    revisions: { type: Array, default: () => [] },
    previewUrl: { type: String, default: null },
    media: { type: Array, default: () => [] },
});

const flash = usePage().props.flash?.status ?? null;
const warnings = usePage().props.flash?.warnings ?? [];

const form = reactive({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    page_type: props.page?.page_type ?? 'about',
    sections: JSON.parse(JSON.stringify(props.page?.sections ?? [])),
    seo: {
        meta_title: props.page?.seo?.meta_title ?? '',
        meta_description: props.page?.seo?.meta_description ?? '',
        og_image: props.page?.seo?.og_image ?? '',
        canonical: props.page?.seo?.canonical ?? '',
        robots: props.page?.seo?.robots ?? '',
    },
});

const showRevisions = ref(false);
const isPublished = computed(() => props.page?.status === 'published');
const hasUnpublished = computed(() => props.page?.has_unpublished_changes ?? false);

const typeLabels = computed(() => {
    const labels = {};
    for (const [key, def] of Object.entries(props.catalog)) labels[key] = def.label;
    return labels;
});

function addSection(type) {
    const def = props.catalog[type];
    const settings = {};
    for (const [field, schema] of Object.entries(def.fields)) {
        if (schema.type === 'list') settings[field] = [];
        else if (schema.type === 'product_list') settings[field] = [];
        else settings[field] = '';
    }
    form.sections.push({
        id: (crypto.randomUUID ? crypto.randomUUID() : String(Math.random())),
        type,
        settings,
    });
}

function removeSection(index) {
    form.sections.splice(index, 1);
}

function duplicateSection(index) {
    const clone = JSON.parse(JSON.stringify(form.sections[index]));
    clone.id = crypto.randomUUID ? crypto.randomUUID() : String(Math.random());
    form.sections.splice(index + 1, 0, clone);
}

function moveSection(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= form.sections.length) return;
    const [section] = form.sections.splice(index, 1);
    form.sections.splice(target, 0, section);
}

function addItem(section, field) {
    if (!Array.isArray(section.settings[field])) section.settings[field] = [];
    const item = {};
    for (const [key, schema] of Object.entries(props.catalog[section.type].fields[field].item)) {
        item[key] = schema.type === 'text' ? '' : '';
    }
    section.settings[field].push(item);
}

function removeItem(section, field, index) {
    section.settings[field].splice(index, 1);
}

function selectMedia(section, field, url) {
    section.settings[field] = url;
    const asset = props.media.find((item) => item.url === url);
    if (asset?.alt_text && Object.hasOwn(section.settings, 'alt')) {
        section.settings.alt = asset.alt_text;
    }
}

function save() {
    const payload = {
        title: form.title,
        slug: form.slug,
        sections: form.sections,
        seo: form.seo,
    };

    if (props.page) {
        router.patch(route('pages.update', props.page.id), payload, { preserveScroll: true });
    } else {
        router.post(route('pages.store'), { ...payload, page_type: form.page_type }, { preserveScroll: true });
    }
}

function publish() {
    router.post(route('pages.publish', props.page.id), {}, { preserveScroll: true });
}

function unpublish() {
    router.post(route('pages.unpublish', props.page.id), {}, { preserveScroll: true });
}

function restore(revisionId) {
    if (!window.confirm('Restore this revision into a new draft? The current draft will be replaced.')) return;
    router.post(route('pages.revisions.restore', [props.page.id, revisionId]), {}, { preserveScroll: true });
}

function fieldLabel(sectionType, field) {
    return props.catalog[sectionType]?.fields?.[field]?.label ?? field;
}

function fieldType(sectionType, field) {
    return props.catalog[sectionType]?.fields?.[field]?.type ?? 'text';
}
</script>

<template>
    <AppShell>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ page ? page.title : 'New page' }}</h1>
                <p class="text-sm text-gray-500">
                    <template v-if="page">
                        /{{ page.slug }}
                        <span
                            class="ml-2 rounded px-2 py-0.5 text-xs font-semibold"
                            :class="page.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                        >{{ page.status }}</span>
                        <span
                            v-if="hasUnpublished"
                            class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-xs text-orange-700"
                        >unpublished changes</span>
                    </template>
                    <template v-else>Create a draft page — publish when the sections validate.</template>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <UiButton
                    v-if="previewUrl"
                    type="button"
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    @click="window.open(previewUrl, '_blank')"
                >
                    Preview draft
                </UiButton>
                <UiButton
                    v-if="page && revisions.length"
                    type="button"
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    @click="showRevisions = !showRevisions"
                >
                    Revisions ({{ revisions.length }})
                </UiButton>
                <UiButton v-if="page && isPublished" type="button" class="bg-amber-600 text-white hover:bg-amber-700" @click="unpublish">
                    Unpublish
                </UiButton>
                <UiButton type="button" class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" @click="save">
                    Save draft
                </UiButton>
                <UiButton v-if="page" type="button" class="bg-green-600 text-white hover:bg-green-700" @click="publish">
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
            v-for="warning in warnings"
            :key="warning"
            class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            role="alert"
        >
            {{ warning }}
        </div>

        <div v-if="showRevisions" class="mb-4">
            <UiCard>
                <h2 class="mb-3 text-lg font-semibold">Revision history</h2>
                <p class="mb-3 text-xs text-gray-500">
                    The last 10 revisions are kept. Restoring copies the revision into a new draft — the original is never modified.
                </p>
                <ul class="divide-y divide-gray-100">
                    <li v-for="revision in revisions" :key="revision.id" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <span class="font-semibold">#{{ revision.revision }}</span>
                            <span class="ml-2 text-gray-600">{{ revision.title }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ new Date(revision.created_at).toLocaleString() }}</span>
                        </div>
                        <button
                            type="button"
                            class="text-blue-700 hover:underline"
                            @click="restore(revision.id)"
                        >Restore</button>
                    </li>
                </ul>
            </UiCard>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <UiCard>
                    <h2 class="mb-3 text-lg font-semibold">Page details</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <UiField label="Title">
                            <UiInput v-model="form.title" />
                        </UiField>
                        <UiField label="Slug">
                            <UiInput v-model="form.slug" placeholder="my-page" />
                        </UiField>
                        <UiField v-if="!page" label="Page type">
                            <UiSelect v-model="form.page_type">
                                <option value="homepage">Home</option>
                                <option value="product_listing">Product listing</option>
                                <option value="pricing">Pricing</option>
                                <option value="about">About</option>
                                <option value="contact">Contact</option>
                                <option value="blog_list">Blog list</option>
                                <option value="blog_post">Blog post</option>
                                <option value="faq">FAQ</option>
                                <option value="testimonials">Testimonials</option>
                                <option value="portfolio">Portfolio</option>
                            </UiSelect>
                        </UiField>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        Reserved slugs: {{ reservedSlugs.join(', ') }}
                    </p>
                </UiCard>

                <UiCard>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Sections ({{ form.sections.length }})</h2>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="(def, type) in catalog"
                                :key="type"
                                type="button"
                                class="rounded border border-gray-200 px-2 py-1 text-xs hover:border-blue-400 hover:text-blue-700"
                                :title="def.label"
                                @click="addSection(type)"
                            >
                                + {{ def.label }}
                            </button>
                        </div>
                    </div>

                    <p v-if="form.sections.length === 0" class="py-6 text-center text-sm text-gray-400">
                        No sections yet — add one from the buttons above.
                    </p>

                    <div
                        v-for="(section, index) in form.sections"
                        :key="section.id"
                        class="mb-3 rounded border border-gray-200 p-3"
                        data-testid="section-block"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">
                                {{ index + 1 }}. {{ typeLabels[section.type] ?? section.type }}
                            </span>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="text-gray-500 hover:text-gray-800" title="Move up" @click="moveSection(index, -1)">↑</button>
                                <button type="button" class="text-gray-500 hover:text-gray-800" title="Move down" @click="moveSection(index, 1)">↓</button>
                                <button type="button" class="text-blue-700 hover:underline" @click="duplicateSection(index)">Duplicate</button>
                                <button type="button" class="text-red-700 hover:underline" @click="removeSection(index)">Delete</button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <template v-for="(schema, field) in catalog[section.type]?.fields ?? {}" :key="field">
                                <!-- Repeatable list fields -->
                                <div v-if="schema.type === 'list'" class="sm:col-span-2">
                                    <UiField :label="schema.label">
                                        <div
                                            v-for="(item, itemIndex) in section.settings[field] ?? []"
                                            :key="itemIndex"
                                            class="mb-2 rounded bg-gray-50 p-2"
                                        >
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <input
                                                    v-for="(itemSchema, itemField) in schema.item"
                                                    :key="itemField"
                                                    v-model="item[itemField]"
                                                    type="text"
                                                    class="ui-input text-sm"
                                                    :placeholder="itemSchema.label"
                                                >
                                            </div>
                                            <button type="button" class="mt-1 text-xs text-red-700 hover:underline" @click="removeItem(section, field, itemIndex)">
                                                Remove
                                            </button>
                                        </div>
                                        <button type="button" class="text-sm text-blue-700 hover:underline" @click="addItem(section, field)">
                                            + Add {{ schema.label.toLowerCase().replace(/s$/, '') }}
                                        </button>
                                    </UiField>
                                </div>

                                <!-- Product picker (pricing table) -->
                                <div v-else-if="schema.type === 'product_list'" class="sm:col-span-2">
                                    <UiField :label="schema.label + ' (comma-separated ids)'">
                                        <UiInput
                                            :model-value="(section.settings[field] ?? []).join(', ')"
                                            placeholder="1, 2, 3"
                                            @update:model-value="v => section.settings[field] = v.split(',').map(s => s.trim()).filter(Boolean)"
                                        />
                                    </UiField>
                                </div>

                                <UiField v-else-if="schema.type === 'media'" :label="schema.label + (schema.required ? ' *' : '')">
                                    <UiSelect
                                        :model-value="section.settings[field]"
                                        @update:model-value="value => selectMedia(section, field, value)"
                                    >
                                        <option value="">Select an uploaded image</option>
                                        <option v-for="asset in media" :key="asset.id" :value="asset.url">
                                            {{ asset.name }}{{ asset.alt_text ? ` — ${asset.alt_text}` : '' }}
                                        </option>
                                    </UiSelect>
                                    <p v-if="media.length === 0" class="mt-1 text-xs text-gray-500">
                                        Upload images in the Media library before adding them to a page.
                                    </p>
                                </UiField>

                                <!-- Simple fields -->
                                <UiField v-else :label="schema.label + (schema.required ? ' *' : '')">
                                    <textarea
                                        v-if="schema.type === 'html'"
                                        v-model="section.settings[field]"
                                        rows="5"
                                        class="ui-input font-mono text-xs"
                                    />
                                    <UiInput
                                        v-else
                                        v-model="section.settings[field]"
                                        :type="schema.type === 'url' ? 'url' : 'text'"
                                    />
                                </UiField>
                            </template>
                        </div>
                    </div>
                </UiCard>

                <UiCard>
                    <h2 class="mb-3 text-lg font-semibold">SEO</h2>
                    <div class="grid grid-cols-1 gap-4">
                        <UiField label="Meta title">
                            <UiInput v-model="form.seo.meta_title" maxlength="60" />
                        </UiField>
                        <UiField label="Meta description">
                            <textarea v-model="form.seo.meta_description" rows="2" maxlength="160" class="ui-input" />
                        </UiField>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <UiField label="OG image path">
                                <UiInput v-model="form.seo.og_image" placeholder="/media/og.png" />
                            </UiField>
                            <UiField label="Canonical URL">
                                <UiInput v-model="form.seo.canonical" placeholder="https://..." />
                            </UiField>
                            <UiField label="Robots">
                                <UiSelect v-model="form.seo.robots">
                                    <option value="">default (index, follow)</option>
                                    <option value="index, follow">index, follow</option>
                                    <option value="noindex, nofollow">noindex, nofollow</option>
                                </UiSelect>
                            </UiField>
                        </div>
                    </div>
                </UiCard>
            </div>

            <div class="space-y-4">
                <UiCard class="self-start">
                    <h2 class="mb-2 text-lg font-semibold">Section catalog</h2>
                    <p class="mb-3 text-xs text-gray-500">
                        Typed sections with server-validated field schemas. Drag handles and up/down buttons reorder sections.
                    </p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(def, type) in catalog" :key="type" class="flex justify-between">
                            <span>{{ def.label }}</span>
                            <span class="text-xs text-gray-400">{{ Object.keys(def.fields).length }} fields</span>
                        </li>
                    </ul>
                </UiCard>
            </div>
        </div>
    </AppShell>
</template>
