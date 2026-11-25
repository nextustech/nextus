<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import UiButton from '../../Components/UiButton.vue';
import UiCard from '../../Components/UiCard.vue';
import UiField from '../../Components/UiField.vue';
import UiSelect from '../../Components/UiSelect.vue';

const props = defineProps({
    menus: { type: Array, required: true },
    pages: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const flash = computed(() => usePage().props.flash?.status ?? null);
const form = useForm({
    name: 'primary',
    display_name: '',
    target_type: 'page',
    target: '',
    target_behavior: '_self',
    is_visible: true,
    parent_id: null,
});
let editingId = null;

const roots = computed(() => props.menus.filter((item) => item.name === form.name));
const targetOptions = computed(() => {
    if (form.target_type === 'page') return props.pages.map((page) => ({ value: page.slug, label: page.title }));
    if (form.target_type === 'category') return props.categories.map((category) => ({ value: category.slug, label: category.name }));
    return [];
});

function resetForm() {
    editingId = null;
    form.reset();
    form.clearErrors();
}

function edit(item) {
    editingId = item.id;
    form.name = item.name;
    form.display_name = item.display_name;
    form.target_type = item.target_type ?? (item.is_external ? 'url' : 'page');
    form.target = item.target ?? item.url ?? item.slug;
    form.target_behavior = item.target_behavior ?? '_self';
    form.is_visible = item.is_visible ?? true;
    form.parent_id = item.parent_id;
    form.clearErrors();
}

function save() {
    const data = form.data();
    if (editingId) {
        form.patch(route('menus.update', editingId), { preserveScroll: true, onSuccess: resetForm });
    } else {
        form.post(route('menus.store'), { preserveScroll: true, onSuccess: resetForm });
    }
}

function remove(item) {
    if (window.confirm(`Delete "${item.display_name}" from this menu?`)) {
        router.delete(route('menus.destroy', item.id), { preserveScroll: true });
    }
}

function reorder(area, items, index, offset) {
    const moved = [...items];
    const target = index + offset;
    if (target < 0 || target >= moved.length) return;
    [moved[index], moved[target]] = [moved[target], moved[index]];
    router.put(route('menus.reorder', area), { ids: moved.map((item) => item.id) }, { preserveScroll: true });
}
</script>

<template>
    <AppShell>
        <div class="mb-4">
            <h1 class="text-2xl font-bold">Navigation menus</h1>
            <p class="text-sm text-gray-600">Manage header and footer links shown on your storefront.</p>
        </div>
        <p v-if="flash" class="mb-4" role="status">{{ flash }}</p>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <UiCard class="lg:col-span-2">
                <div v-for="area in ['primary', 'footer']" :key="area" class="mb-6 last:mb-0">
                    <h2 class="mb-2 text-lg font-semibold">{{ area === 'primary' ? 'Header menu' : 'Footer menu' }}</h2>
                    <ol class="divide-y">
                        <li v-for="(item, index) in menus.filter((entry) => entry.name === area)" :key="item.id" class="py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <strong>{{ item.display_name }}</strong>
                                    <small class="ml-2 text-gray-500">{{ item.is_visible ? item.url : 'Hidden' }}</small>
                                </span>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="text-sm" :disabled="index === 0" @click="reorder(area, menus.filter((entry) => entry.name === area), index, -1)">Move up</button>
                                    <button type="button" class="text-sm" :disabled="index === menus.filter((entry) => entry.name === area).length - 1" @click="reorder(area, menus.filter((entry) => entry.name === area), index, 1)">Move down</button>
                                    <button type="button" class="text-blue-700" @click="edit(item)">Edit</button>
                                    <button type="button" class="text-red-700" @click="remove(item)">Delete</button>
                                </div>
                            </div>
                            <ul v-if="item.children?.length" class="ml-6 mt-2 border-l pl-4">
                                <li v-for="child in item.children" :key="child.id" class="flex justify-between py-1">
                                    <span>{{ child.display_name }} <small class="text-gray-500">{{ child.url }}</small></span>
                                    <span class="flex gap-2">
                                        <button type="button" class="text-blue-700" @click="edit(child)">Edit</button>
                                        <button type="button" class="text-red-700" @click="remove(child)">Delete</button>
                                    </span>
                                </li>
                            </ul>
                        </li>
                        <li v-if="!menus.some((entry) => entry.name === area)" class="py-3 text-gray-500">No links in this menu yet.</li>
                    </ol>
                </div>
            </UiCard>

            <UiCard>
                <h2 class="mb-3 text-lg font-semibold">{{ editingId ? 'Edit menu item' : 'Add menu item' }}</h2>
                <form class="space-y-3" @submit.prevent="save">
                    <UiField label="Menu area">
                        <UiSelect v-model="form.name">
                            <option value="primary">Header</option>
                            <option value="footer">Footer</option>
                        </UiSelect>
                    </UiField>
                    <UiField label="Label">
                        <input v-model="form.display_name" class="ui-input" maxlength="191" required>
                    </UiField>
                    <UiField label="Link type">
                        <UiSelect v-model="form.target_type">
                            <option value="page">Published page</option>
                            <option value="category">Product category</option>
                            <option value="url">External HTTPS URL</option>
                        </UiSelect>
                    </UiField>
                    <UiField v-if="form.target_type !== 'url'" label="Destination">
                        <UiSelect v-model="form.target">
                            <option value="" disabled>Select destination</option>
                            <option v-for="option in targetOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </UiSelect>
                    </UiField>
                    <UiField v-else label="HTTPS URL">
                        <input v-model="form.target" class="ui-input" type="url" placeholder="https://example.com" required>
                    </UiField>
                    <UiField label="Dropdown parent (optional)">
                        <UiSelect v-model="form.parent_id">
                            <option :value="null">Top-level item</option>
                            <option v-for="parent in roots.filter((item) => item.id !== editingId)" :key="parent.id" :value="parent.id">
                                {{ parent.display_name }}
                            </option>
                        </UiSelect>
                    </UiField>
                    <UiField label="Open link">
                        <UiSelect v-model="form.target_behavior">
                            <option value="_self">In same tab</option>
                            <option value="_blank">In new tab</option>
                        </UiSelect>
                    </UiField>
                    <label class="flex items-center gap-2">
                        <input v-model="form.is_visible" type="checkbox">
                        Visible on storefront
                    </label>
                    <p v-if="form.errors.target" class="text-sm text-red-700">{{ form.errors.target }}</p>
                    <p v-if="form.errors.parent_id" class="text-sm text-red-700">{{ form.errors.parent_id }}</p>
                    <div class="flex gap-2">
                        <UiButton type="submit" :disabled="form.processing">{{ editingId ? 'Save changes' : 'Add item' }}</UiButton>
                        <button v-if="editingId" type="button" class="text-sm" @click="resetForm">Cancel</button>
                    </div>
                </form>
            </UiCard>
        </div>
    </AppShell>
</template>
