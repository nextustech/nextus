<script setup>
import { router, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiCard from '../../Components/UiCard.vue';
import UiButton from '../../Components/UiButton.vue';

const props = defineProps({
    pages: { type: Array, required: true },
});

const flash = usePage().props.flash?.status ?? null;

const typeLabels = {
    homepage: 'Home',
    product_listing: 'Product listing',
    product_detail: 'Product detail',
    pricing: 'Pricing',
    about: 'About',
    contact: 'Contact',
    blog_list: 'Blog list',
    blog_post: 'Blog post',
    faq: 'FAQ',
    testimonials: 'Testimonials',
    portfolio: 'Portfolio',
    legal: 'Legal',
    not_found: '404',
};

function createPage() {
    router.visit(route('pages.create'));
}

function editPage(page) {
    router.visit(route('pages.edit', page.id));
}

function publish(page) {
    router.post(route('pages.publish', page.id), {}, { preserveScroll: true });
}

function unpublish(page) {
    router.post(route('pages.unpublish', page.id), {}, { preserveScroll: true });
}

function destroy(page) {
    if (!window.confirm(`Delete "${page.title}"? This cannot be undone.`)) return;
    router.delete(route('pages.destroy', page.id), { preserveScroll: true });
}
</script>

<template>
    <AppShell>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Storefront pages</h1>
                <p class="text-sm text-gray-500">
                    Draft, preview and publish the pages that make up your storefront.
                </p>
            </div>
            <UiButton type="button" class="bg-blue-600 text-white hover:bg-blue-700" @click="createPage">
                New page
            </UiButton>
        </div>

        <div
            v-if="flash"
            class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            role="status"
        >
            {{ flash }}
        </div>

        <UiCard>
            <table class="w-full text-left text-sm">
                <thead class="border-b text-xs uppercase text-gray-500">
                    <tr>
                        <th class="py-2 pr-4">Title</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Slug</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Updated</th>
                        <th class="py-2 pr-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="page in pages"
                        :key="page.id"
                        class="border-b border-gray-100 last:border-0"
                        data-testid="page-row"
                    >
                        <td class="py-3 pr-4">
                            <button type="button" class="font-semibold text-blue-700 hover:underline" @click="editPage(page)">
                                {{ page.title }}
                            </button>
                            <span
                                v-if="page.system_key"
                                class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600"
                            >system</span>
                        </td>
                        <td class="py-3 pr-4 text-gray-600">{{ typeLabels[page.page_type] ?? page.page_type }}</td>
                        <td class="py-3 pr-4 font-mono text-xs text-gray-500">/{{ page.slug }}</td>
                        <td class="py-3 pr-4">
                            <span
                                class="rounded px-2 py-0.5 text-xs font-semibold"
                                :class="{
                                    'bg-green-100 text-green-800': page.status === 'published',
                                    'bg-amber-100 text-amber-800': page.status === 'draft',
                                    'bg-gray-100 text-gray-600': page.status === 'archived',
                                }"
                            >{{ page.status }}</span>
                            <span
                                v-if="page.has_unpublished_changes"
                                class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-xs text-orange-700"
                                title="Unpublished changes — visitors still see the published revision"
                            >unpublished</span>
                        </td>
                        <td class="py-3 pr-4 text-xs text-gray-500">{{ new Date(page.updated_at).toLocaleDateString() }}</td>
                        <td class="py-3 pr-4 text-right whitespace-nowrap">
                            <button type="button" class="mr-3 text-blue-700 hover:underline" @click="editPage(page)">Edit</button>
                            <button
                                v-if="page.status === 'published' && !page.system_key"
                                type="button"
                                class="mr-3 text-amber-700 hover:underline"
                                @click="unpublish(page)"
                            >Unpublish</button>
                            <button
                                v-else-if="page.status !== 'published'"
                                type="button"
                                class="mr-3 text-green-700 hover:underline"
                                @click="publish(page)"
                            >Publish</button>
                            <button
                                v-if="!page.system_key"
                                type="button"
                                class="text-red-700 hover:underline"
                                @click="destroy(page)"
                            >Delete</button>
                        </td>
                    </tr>
                    <tr v-if="pages.length === 0">
                        <td colspan="6" class="py-6 text-center text-gray-400">No pages yet.</td>
                    </tr>
                </tbody>
            </table>
        </UiCard>
    </AppShell>
</template>
