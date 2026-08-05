<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface MR {
    id: number
    mr_number: string
    status_workflow: string
    factory: string
    type: string
    allocation: string
    created_at: string
    user?: { name: string; nik: string }
    manager?: { id: number; name: string } | null
    fm_gm?: { id: number; name: string } | null
    direksi?: { id: number; name: string } | null
    items?: any[]
}

const props = defineProps<{
    requests: { data: MR[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string; status?: string }
    stats: { total: number; hari_ini: number; pengaju: number; berjalan: number }
    statusCounts: any
    topUsers: { user: { name: string; nik: string } | null; total: number }[]
    managers: { id: number; name: string; nik: string }[]
    fmGms: { id: number; name: string; nik: string }[]
    direksis: { id: number; name: string; nik: string }[]
    canEdit: boolean
    allStatuses: string[]
}>()

const pp = usePage().props as any
const baseUrl = pp.app_url || ''

const searchVal = ref(props.filters?.search || '')
const statusVal = ref(props.filters?.status || '')

const statusBadge = (s: string) => {
    if (['Fully Approved'].includes(s)) return 'success'
    if (['Rejected'].includes(s)) return 'danger'
    if (['Pending Manager', 'Pending FM/GM', 'Pending Direksi'].includes(s)) return 'warning'
    if (['Verifikasi Gudang', 'Purchasing'].includes(s)) return 'info'
    return 'default'
}

const applyFilters = () => {
    router.get(baseUrl + '/admin/overview', {
        search: searchVal.value || undefined,
        status: statusVal.value || undefined,
    }, { preserveState: true })
}

// ===== Logika Tujuan Approval =====
const targetInfo = (mr: MR) => {
    switch (mr.status_workflow) {
        case 'Pending Manager': return { role: 'Manager', current: mr.manager }
        case 'Pending FM/GM': return { role: 'FM/GM', current: mr.fm_gm }
        case 'Pending Direksi': return { role: 'Direksi', current: mr.direksi }
        default: return null
    }
}

const openEdit = (id: number) => { window.location.href = baseUrl + '/admin/overview/' + id + '/edit' }

const statusStats = computed(() => [
    { label: 'Total MR', value: props.stats.total, color: '#4f46e5', bg: '#e0e7ff' },
    { label: 'MR Hari Ini', value: props.stats.hari_ini, color: '#0ea5e9', bg: '#e0f2fe' },
    { label: 'Pengaju', value: props.stats.pengaju, color: '#10b981', bg: '#d1fae5' },
    { label: 'MR Berjalan', value: props.stats.berjalan, color: '#f59e0b', bg: '#fef3c7' },
])

const openDetail = (id: number) => { window.location.href = baseUrl + '/material-requests/' + id }
</script>

<template>
    <Head title="Admin Overview - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📊 Dashboard Admin" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right>
                <var-button v-if="canEdit" round text @click="router.get(baseUrl + '/admin/users')"><var-icon name="account-group" :size="22" color="#64748b" /></var-button>
            </template>
        </var-app-bar>

        <main class="content">
            <!-- Stats -->
            <div class="stats-grid">
                <div v-for="s in statusStats" :key="s.label" class="stat-card">
                    <div class="stat-icon" :style="{ background: s.bg, color: s.color }"><var-icon name="chart-bar" :size="20" /></div>
                    <div>
                        <span class="stat-count">{{ s.value }}</span>
                        <span class="stat-label">{{ s.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Status breakdown -->
            <div class="card">
                <div class="lbl">Status MR</div>
                <div class="status-chips">
                    <span v-for="st in allStatuses" :key="st" class="status-chip" @click="statusVal = statusVal === st ? '' : st; applyFilters()" :class="{ active: statusVal === st }">
                        {{ st }} <strong>{{ statusCounts[st]?.total || 0 }}</strong>
                    </span>
                </div>
            </div>

            <!-- Top Pengaju -->
            <div class="card">
                <div class="lbl">Top Pengaju</div>
                <div class="top-list">
                    <div v-for="(t, i) in topUsers" :key="i" class="top-item">
                        <span class="top-rank">{{ i + 1 }}</span>
                        <span class="top-name">{{ t.user?.name || '?' }}</span>
                        <span class="top-total">{{ t.total }} MR</span>
                    </div>
                    <p v-if="!topUsers.length" class="empty-txt">Belum ada data.</p>
                </div>
            </div>

            <!-- Filter -->
            <div class="card">
                <div class="lbl">Cari MR / Pengaju</div>
                <div class="filter-row">
                    <var-input v-model="searchVal" placeholder="MR number / nama / NIK..." clearable @keyup.enter="applyFilters" style="flex:1" />
                    <var-button type="primary" @click="applyFilters"><var-icon name="magnify" :size="16" /></var-button>
                </div>
            </div>

            <!-- List MR -->
            <div class="info-bar">Menampilkan {{ requests.from }}–{{ requests.to }} dari {{ requests.total }} MR</div>

            <div v-if="!requests.data.length" class="empty">Tidak ada MR</div>

            <div v-for="mr in requests.data" :key="mr.id" class="mr-card">
                <div class="mr-head" @click="openDetail(mr.id)">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip :type="statusBadge(mr.status_workflow)" size="mini">{{ mr.status_workflow }}</var-chip>
                </div>
                <div class="mr-info" @click="openDetail(mr.id)">
                    <span><strong>{{ mr.user?.name || '?' }}</strong> ({{ mr.user?.nik || '-' }})</span>
                    <span class="muted">{{ mr.factory }} • {{ mr.type }} • {{ mr.allocation }} • {{ mr.items?.length || 0 }} item</span>
                    <span class="muted">{{ mr.created_at }}</span>
                </div>

                <!-- Tujuan & Edit -->
                <div class="target-row">
                    <span v-if="targetInfo(mr)" class="target-lbl">Tujuan {{ targetInfo(mr)?.role }}: <strong>{{ targetInfo(mr)?.current?.name || 'Belum ditentukan' }}</strong></span>
                    <span v-else class="target-lbl">Tujuan: —</span>
                    <var-button v-if="canEdit" size="small" type="primary" @click="openEdit(mr.id)" style="margin-left:auto">
                        <var-icon name="pencil" :size="16" style="margin-right:4px" /> Edit
                    </var-button>
                </div>
            </div>

            <!-- Pagination -->
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
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }

.stats-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:10px; }
.stat-card { background:#fff;border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px;border:1px solid #f1f5f9; }
.stat-icon { width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center; }
.stat-count { display:block;font-size:20px;font-weight:800;color:#0f172a;line-height:1.1; }
.stat-label { font-size:11px;color:#64748b; }

.card { background:#fff;border-radius:14px;padding:14px;border:1px solid #f1f5f9; }
.lbl { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px; }

.status-chips { display:flex;flex-wrap:wrap;gap:6px; }
.status-chip { font-size:11px;padding:5px 10px;border-radius:999px;background:#f1f5f9;color:#475569;cursor:pointer;border:1px solid transparent; }
.status-chip.active { background:#4f46e5;color:#fff; }
.status-chip strong { font-size:12px; }

.top-list { display:flex;flex-direction:column;gap:6px; }
.top-item { display:flex;align-items:center;gap:10px;font-size:13px; }
.top-rank { width:22px;height:22px;border-radius:50%;background:#e0e7ff;color:#4f46e5;font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center; }
.top-name { flex:1;color:#0f172a;font-weight:600; }
.top-total { color:#4f46e5;font-weight:700;font-size:12px; }
.empty-txt { color:#94a3b8;font-size:12px; }

.filter-row { display:flex;gap:8px;align-items:center; }
.info-bar { font-size:12px;color:#64748b; }
.empty { text-align:center;padding:40px;color:#94a3b8; }

.mr-card { background:#fff;border-radius:14px;padding:14px;border:1px solid #f1f5f9; }
.mr-head { display:flex;justify-content:space-between;align-items:center;cursor:pointer; }
.mr-num { font-family:monospace;font-weight:800;font-size:13px;color:#0f172a; }
.mr-info { display:flex;flex-direction:column;gap:2px;font-size:12px;margin:6px 0 10px;cursor:pointer; }
.mr-info .muted { color:#64748b; }
.target-row { display:flex;align-items:center;gap:8px;border-top:1px solid #f1f5f9;padding-top:10px; }
.target-lbl { font-size:11px;font-weight:700;color:#4f46e5;white-space:nowrap; }
.target-done { font-size:11px;color:#94a3b8;border-top:1px solid #f1f5f9;padding-top:10px; }

.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }
</style>