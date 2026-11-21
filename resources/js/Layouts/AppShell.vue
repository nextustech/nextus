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
            <nav class="app-shell__nav">
                <Link href="/dashboard" :aria-current="page.url === '/dashboard' ? 'page' : undefined">Dashboard</Link>
                <Link href="/users" :aria-current="page.url.startsWith('/users') ? 'page' : undefined">Users</Link>
                <Link href="/invitations" :aria-current="page.url.startsWith('/invitations') ? 'page' : undefined">Invitations</Link>
                <Link href="/resellers" :aria-current="page.url === '/resellers' ? 'page' : undefined">Onboarding</Link>
                <Link href="/resellers/sub-resellers" :aria-current="page.url.startsWith('/resellers/sub-resellers') ? 'page' : undefined">Sub-resellers</Link>
                <Link href="/resellers/hierarchy" :aria-current="page.url.startsWith('/resellers/hierarchy') ? 'page' : undefined">Hierarchy</Link>
                <Link href="/audit-logs" :aria-current="page.url.startsWith('/audit-logs') ? 'page' : undefined">Audit logs</Link>
            </nav>
        </aside>
        <div class="app-shell__main">
            <header class="app-shell__topbar">
                <strong>Frenchies console</strong>
                <button class="ui-button" type="button" :disabled="logout.processing" @click="submitLogout">
                    Logout
                </button>
            </header>
            <main class="app-shell__content">
                <slot />
            </main>
        </div>
    </div>
</template>
