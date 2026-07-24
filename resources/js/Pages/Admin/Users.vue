<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'

interface UserItem { id: number; nik: string|null; name: string; email: string; is_approved: boolean; roles: { name: string }[] }
const props = defineProps<{ users: { data: UserItem[] }; allRoles: string[] }>()
const baseUrl = (usePage().props as any).app_url || ''

const openDetail = (id: number) => { window.location.href = baseUrl + '/admin/users/' + id }
</script>

<template>
    <Head title="Admin - Kelola User" />
    <div class="layout">
        <var-app-bar title="👥 Kelola User" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="banner">Klik user untuk detail & aktivasi</div>
            <div v-for="u in users.data" :key="u.id" class="card" @click="openDetail(u.id)">
                <div class="left">
                    <span class="name">{{ u.name }}</span>
                    <span class="nik">NIK: {{ u.nik || '—' }}</span>
                </div>
                <div class="right">
                    <var-chip :type="u.is_approved ? 'success' : 'warning'" size="mini">{{ u.is_approved ? 'Aktif' : 'Baru' }}</var-chip>
                    <span class="role-tag">{{ u.roles[0]?.name || '—' }}</span>
                    <var-icon name="chevron-right" :size="20" color="#94a3b8" />
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:10px; }
.banner { background:#fef3c7;border-radius:12px;padding:12px 16px;font-size:13px;color:#92400e; }
.card { display:flex;align-items:center;justify-content:space-between;background:#fff;border-radius:16px;padding:14px 16px;border:1px solid #f1f5f9;cursor:pointer; }
.card:hover { border-color:#c7d2fe; }
.left { display:flex;flex-direction:column;gap:2px; }
.name { font-weight:700;font-size:14px;color:#0f172a; }
.nik { font-size:11px;color:#4f46e5;font-family:monospace; }
.right { display:flex;align-items:center;gap:8px; }
.role-tag { font-size:11px;padding:2px 8px;border-radius:6px;background:#e0e7ff;color:#4f46e5;font-weight:600; }
</style>
