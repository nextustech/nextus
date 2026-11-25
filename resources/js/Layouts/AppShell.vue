<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const logout = useForm({});

function submitLogout() {
    logout.post('/logout');
}
</script>

<template>
    <div class="app-shell">
        <aside class="app-shell__sidebar" aria-label="Primary navigation">
            <Link href="/dashboard" class="app-shell__brand">Frenchies</Link>
            <button class="ui-button app-shell__logout" type="button" :disabled="logout.processing" @click="submitLogout">
                Log out
            </button>
            <nav class="app-shell__nav">
                <Link href="/dashboard" :aria-current="page.url === '/dashboard' ? 'page' : undefined">Dashboard</Link>
                <Link href="/users" :aria-current="page.url.startsWith('/users') ? 'page' : undefined">Users</Link>
                <Link href="/invitations" :aria-current="page.url.startsWith('/invitations') ? 'page' : undefined">Invitations</Link>
                <Link href="/resellers" :aria-current="page.url === '/resellers' ? 'page' : undefined">Onboarding</Link>
                <Link href="/resellers/sub-resellers" :aria-current="page.url.startsWith('/resellers/sub-resellers') ? 'page' : undefined">Sub-resellers</Link>
                <Link href="/resellers/hierarchy" :aria-current="page.url.startsWith('/resellers/hierarchy') ? 'page' : undefined">Hierarchy</Link>
                <Link href="/audit-logs" :aria-current="page.url.startsWith('/audit-logs') ? 'page' : undefined">Audit logs</Link>
                <Link href="/cms/pages" :aria-current="page.url.startsWith('/cms/pages') ? 'page' : undefined">Pages</Link>
                <Link href="/cms/menus" :aria-current="page.url.startsWith('/cms/menus') ? 'page' : undefined">Menus</Link>
                <Link href="/cms/media" :aria-current="page.url.startsWith('/cms/media') ? 'page' : undefined">Media library</Link>
                <Link href="/branding" :aria-current="page.url.startsWith('/branding') ? 'page' : undefined">Branding</Link>
            </nav>
        </aside>
        <div class="app-shell__main">
            <header class="app-shell__topbar">
                <strong>Frenchies console</strong>
            </header>
            <main class="app-shell__content">
                <slot />
            </main>
        </div>
    </div>
</template>
