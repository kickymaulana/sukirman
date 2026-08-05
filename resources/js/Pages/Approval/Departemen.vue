<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

interface MR { id: number; mr_number: string; factory: string; jenis: string; user: { name: string }; items: any[]; created_at: string }
const props = defineProps<{ requests: { data: MR[] }; deptRole: string }>()
const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const openDetail = (id: number) => { window.location.href = baseUrl + '/material-requests/' + id }

const decide = async (mr: MR, action: 'approve' | 'reject') => {
    const res = await fetch(`${baseUrl}/approval/${props.deptRole.toLowerCase()}/${mr.id}/decision`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action }),
    })
    if (res.ok || res.redirected) { Snackbar.success(action === 'approve' ? 'MR disetujui' : 'MR ditolak'); window.location.reload() }
    else { Snackbar.error('Gagal') }
}
</script>

<template>
    <Head :title="deptRole + ' - SUKIRMAN'" />
    <div class="layout">
        <var-app-bar :title="'Approval ' + deptRole" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="info-banner">MR jenis {{ deptRole }} yang menunggu persetujuan Anda.</div>
            <div v-if="!requests.data.length" class="empty">Tidak ada MR menunggu</div>
            <div v-for="mr in requests.data" :key="mr.id" class="card" @click="openDetail(mr.id)">
                <div class="card-h">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip type="warning" size="mini">Pending {{ deptRole }}</var-chip>
                </div>
                <p><strong>{{ mr.user?.name }}</strong> • {{ mr.factory }} • Jenis: {{ mr.jenis }}</p>
                <p class="date">{{ mr.created_at }} • {{ mr.items?.length || 0 }} item</p>
                <div class="actions" @click.stop>
                    <button class="btn yes" @click="decide(mr, 'approve')">✓ Setujui</button>
                    <button class="btn no" @click="decide(mr, 'reject')">✕ Tolak</button>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px; }
.info-banner { background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:12px 16px;font-size:13px;color:#3730a3;margin-bottom:12px; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9;margin-bottom:10px;cursor:pointer; }
.card-h { display:flex;justify-content:space-between;align-items:center;margin-bottom:6px; }
.mr-num { font-family:monospace;font-weight:800;font-size:13px; }
p { margin:2px 0;font-size:13px;color:#475569; }
.date { font-size:11px;color:#94a3b8; }
.actions { display:flex;gap:8px;margin-top:10px; }
.btn { flex:1;padding:10px;border-radius:10px;border:none;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit; }
.btn.yes { background:#10b981;color:#fff; }
.btn.no { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }
</style>