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
    departemen: string | null
    items_count: number
}

const props = defineProps<{
    requests: { data: MR[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string; factory?: string; status?: string }
    allFactories: string[]
    allStatuses: string[]
}>()

const baseUrl = (usePage().props as any).app_url || ''
const searchVal = ref(props.filters?.search || '')
const factoryVal = ref(props.filters?.factory || '')
const statusVal = ref(props.filters?.status || '')

const statusBadge = (s: string) => {
    if (['Fully Approved'].includes(s)) return 'success'
    if (['Rejected'].includes(s)) return 'danger'
    if (['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD', 'Revision'].includes(s)) return 'warning'
    if (['Verifikasi Gudang', 'Purchasing'].includes(s)) return 'info'
    return 'default'
}

const applyFilters = () => {
    router.get(baseUrl + '/monitoring-mr', {
        search: searchVal.value || undefined,
        factory: factoryVal.value || undefined,
        status: statusVal.value || undefined,
    }, { preserveState: true })
}

const goBack = () => router.get(route('dashboard'))
</script>

<template>
    <Head title="Monitoring MR - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📋 Monitoring MR" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="filter-bar">
                <var-input v-model="searchVal" placeholder="Cari MR / nama / NIK..." clearable @keyup.enter="applyFilters" style="flex:1;max-width:320px" />
                <var-select v-model="factoryVal" placeholder="Semua Factory" style="width:200px" @change="applyFilters">
                    <var-option label="Semua Factory" value="" />
                    <var-option v-for="f in allFactories" :key="f" :label="f" :value="f" />
                </var-select>
                <var-select v-model="statusVal" placeholder="Semua Status" style="width:200px" @change="applyFilters">
                    <var-option label="Semua Status" value="" />
                    <var-option v-for="s in allStatuses" :key="s" :label="s" :value="s" />
                </var-select>
                <var-button type="primary" @click="applyFilters"><var-icon name="magnify" :size="16" /></var-button>
            </div>

            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>No. MR</th>
                            <th>Pengaju</th>
                            <th>Departemen</th>
                            <th>Factory</th>
                            <th>Jenis</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="mr in requests.data" :key="mr.id">
                            <td class="mono">
                                <a :href="baseUrl + '/material-requests/' + mr.id + '/print'" class="link-mr">{{ mr.mr_number }}</a>
                            </td>
                            <td>{{ mr.pengaju }}</td>
                            <td>{{ mr.departemen || '-' }}</td>
                            <td>{{ mr.factory }}</td>
                            <td>{{ mr.jenis }}</td>
                            <td class="muted">{{ mr.created_at }}</td>
                            <td><var-chip :type="statusBadge(mr.status_workflow)" size="mini">{{ mr.status_workflow }}</var-chip></td>
                            <td>
                                <a :href="baseUrl + '/material-requests/' + mr.id" class="btn-detail">Lihat</a>
                            </td>
                        </tr>
                        <tr v-if="!requests.data.length">
                            <td colspan="8" class="empty">Tidak ada MR</td>
                        </tr>
                    </tbody>
                </table>
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
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px; }
.filter-bar { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }
.table-wrap { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:12px;font-size:12px;text-transform:uppercase; }
.tbl td { padding:10px 12px;border-top:1px solid #f1f5f9;color:#1e293b; }
.tbl tr:hover td { background:#f8fafc; }
.mono { font-family:monospace;font-weight:700; }
.link-mr { color:#4f46e5;text-decoration:underline; }
.link-mr:hover { color:#6d28d9; }
.muted { color:#64748b; }
.empty { text-align:center;color:#94a3b8;padding:40px; }
.btn-detail { background:#e0e7ff;color:#4f46e5;padding:6px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;display:inline-block; }
.btn-detail:hover { background:#c7d2fe; }
.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }
</style>