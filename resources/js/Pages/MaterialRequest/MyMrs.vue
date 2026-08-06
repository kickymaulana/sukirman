<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface MR {
    id: number
    mr_number: string
    jenis: string
    factory: string
    status_workflow: string
    created_at: string
    pengaju: string
    peran_saya: string[]
}

const props = defineProps<{
    requests: { data: MR[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string }
}>()

const baseUrl = (usePage().props as any).app_url || ''
const searchVal = ref(props.filters?.search || '')

const statusBadge = (s: string) => {
    if (['Fully Approved'].includes(s)) return 'success'
    if (['Rejected'].includes(s)) return 'danger'
    if (['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD', 'Revision'].includes(s)) return 'warning'
    if (['Verifikasi Gudang', 'Purchasing'].includes(s)) return 'info'
    return 'default'
}

const search = () => {
    router.get(baseUrl + '/my-mrs', { search: searchVal.value || undefined }, { preserveState: true })
}

const openDetail = (id: number) => { window.location.href = baseUrl + '/material-requests/' + id }
const goBack = () => { router.get(route('dashboard')) }
</script>

<template>
    <Head title="MR Saya - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📋 MR Saya" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="info-banner">Daftar MR yang berkaitan dengan Anda (pengaju / target / pernah menindak). Klik untuk lihat detail.</div>

            <div class="search-row">
                <var-input v-model="searchVal" placeholder="MR number / nama / NIK..." clearable @keyup.enter="search" style="flex:1" />
                <var-button type="primary" @click="search"><var-icon name="magnify" :size="16" /></var-button>
            </div>

            <div v-if="!requests.data.length" class="empty">Tidak ada MR</div>

            <div v-for="mr in requests.data" :key="mr.id" class="card" @click="openDetail(mr.id)">
                <div class="card-h">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip :type="statusBadge(mr.status_workflow)" size="mini">{{ mr.status_workflow }}</var-chip>
                </div>
                <p><strong>{{ mr.pengaju }}</strong> • {{ mr.factory }} • Jenis: {{ mr.jenis }} • {{ mr.created_at }}</p>
                <div class="role-chips">
                    <span v-for="r in mr.peran_saya" :key="r" class="role-chip">{{ r }}</span>
                </div>
            </div>

            <div class="pagination">
                <a v-if="requests.prev_page_url" :href="requests.prev_page_url" class="page-btn">Sebelumnya</a>
                <span class="page-info">{{ requests.from }}–{{ requests.to }} dari {{ requests.total }}</span>
                <a v-if="requests.next_page_url" :href="requests.next_page_url" class="page-btn">Selanjutnya</a>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;font-family:Roboto,sans-serif;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:10px; }
.info-banner { background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:12px 16px;font-size:13px;color:#3730a3; }
.search-row { display:flex;gap:8px;align-items:center; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.card { background:#fff;border-radius:14px;padding:14px 16px;border:1px solid #f1f5f9;cursor:pointer; }
.card-h { display:flex;justify-content:space-between;align-items:center;margin-bottom:6px; }
.mr-num { font-family:monospace;font-weight:800;font-size:13px;color:#0f172a; }
p { margin:2px 0;font-size:12px;color:#475569; }
.role-chips { display:flex;flex-wrap:wrap;gap:6px;margin-top:6px; }
.role-chip { font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:#e0e7ff;color:#4338ca; }
.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }
</style>