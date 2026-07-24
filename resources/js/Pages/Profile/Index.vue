<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { Dialog } from '@varlet/ui'

const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''

interface UserInfo {
    id: number; name: string; email: string; nik: string|null; roles: { name: string }[]
}
const props = defineProps<{ user: UserInfo }>()
const u = props.user

const confirmLogout = () => {
    Dialog({
        title: 'Keluar?', message: 'Anda akan logout dari aplikasi.',
        onConfirm: () => router.post(route('logout')),
    })
}

const back = () => { window.location.href = baseUrl + '/dashboard' }
</script>

<template>
    <Head title="Profil - SUKIRMAN" />
    <div class="screen">
        <div class="bg-shapes">
            <div class="shape s1"></div>
            <div class="shape s2"></div>
        </div>

        <div class="header-bar">
            <button class="back-btn" @click="back">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
            </button>
            <h1>Profil</h1>
            <div style="width:40px"></div>
        </div>

        <div class="content">
            <!-- Avatar Card -->
            <div class="avatar-card">
                <div class="avatar">{{ u.name?.charAt(0)?.toUpperCase() || '?' }}</div>
                <h2 class="fullname">{{ u.name }}</h2>
                <span class="role-badge">{{ u.roles?.[0]?.name || '—' }}</span>
            </div>

            <!-- Info Card -->
            <div class="info-card">
                <div class="info-row">
                    <span class="label">NIK</span>
                    <span class="value mono">{{ u.nik || '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Email</span>
                    <span class="value">{{ u.email }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Role</span>
                    <span class="value">{{ u.roles?.[0]?.name || '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value active">Aktif</span>
                </div>
            </div>

            <!-- Logout Button -->
            <button class="logout-btn" @click="confirmLogout">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/>
                </svg>
                Keluar
            </button>

            <p class="version">SUKIRMAN v2.0.0 • IT Department</p>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.screen {
    min-height: 100vh;
    background: #f8fafc;
    font-family: 'Inter', -apple-system, sans-serif;
    position: relative;
    overflow: hidden;
}

.bg-shapes { position: absolute; inset: 0; pointer-events: none; }
.shape {
    position: absolute; border-radius: 50%;
    filter: blur(60px);
}
.s1 { width: 300px; height: 300px; background: #4f46e5; top: -100px; right: -80px; opacity: 0.15; }
.s2 { width: 200px; height: 200px; background: #7c3aed; bottom: -50px; left: -50px; opacity: 0.1; }

.header-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; position: relative; z-index: 1;
}
.back-btn {
    width: 40px; height: 40px; border-radius: 12px; border: none;
    background: #ffffff; color: #4f46e5; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.header-bar h1 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }

.content {
    position: relative; z-index: 1;
    padding: 0 20px 40px;
    display: flex; flex-direction: column; align-items: center; gap: 20px;
}

.avatar-card {
    text-align: center; padding: 32px 20px 24px;
}
.avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff; font-size: 32px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    box-shadow: 0 8px 25px rgba(79,70,229,0.3);
}
.fullname { margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #0f172a; }
.role-badge {
    display: inline-block; padding: 4px 16px; border-radius: 100px;
    background: #e0e7ff; color: #4f46e5; font-size: 13px; font-weight: 600;
}

.info-card {
    width: 100%; max-width: 400px;
    background: #ffffff; border-radius: 20px; padding: 20px;
    border: 1px solid #f1f5f9; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid #f8fafc;
}
.info-row:last-child { border-bottom: none; }
.label { font-size: 13px; color: #94a3b8; font-weight: 500; }
.value { font-size: 14px; color: #0f172a; font-weight: 600; }
.mono { font-family: monospace; color: #4f46e5; }
.active { color: #10b981; }

.logout-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; max-width: 400px; padding: 16px 24px;
    background: #ffffff; border: 2px solid #fecaca; border-radius: 16px;
    color: #dc2626; font-size: 15px; font-weight: 700; cursor: pointer;
    transition: all 0.2s; font-family: inherit;
}
.logout-btn:hover { background: #fef2f2; border-color: #fca5a5; }

.version { font-size: 11px; color: #94a3b8; margin: 0; }
</style>
