<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const baseUrl = (usePage().props as any).app_url || ''

interface RequestItem {
  id: number
  item_name: string
  qty: number
  unit: string
}

interface LogItem {
  role: string
  action: string
  user_name: string
  time: string
}

interface MaterialRequestData {
  id: number
  mr_number: string
  type: string
  factory: string
  status_pembelian: string
  status_workflow: string
  created_at: string
  items: RequestItem[]
  stage: number
  rejected: boolean
  revision: boolean
  logs: LogItem[]
}

const props = defineProps<{
  requests: {
    data: MaterialRequestData[]
    current_page: number
    last_page: number
    next_page_url: string | null
  }
  filters?: {
    search?: string
  }
}>()

// Search Bar State
const isSearching = ref(!!props.filters?.search)
const searchQuery = ref(props.filters?.search || '')

// Pull Refresh & List Load States
const isRefreshing = ref(false)
const loading = ref(false)
const finished = ref(props.requests.current_page >= props.requests.last_page)
const listData = ref<MaterialRequestData[]>([...props.requests.data])
const currentPage = ref(props.requests.current_page)

// Toggle Search Mode
const enableSearch = () => {
  isSearching.value = true
}

const disableSearch = () => {
  isSearching.value = false
  if (searchQuery.value !== '') {
    searchQuery.value = ''
    performSearch()
  }
}

// 💡 Handler Search hanya saat di-SUBMIT (Tekan Enter atau klik Tombol Cari)
const performSearch = () => {
  router.get(
    route('material-requests.index'),
    { search: searchQuery.value },
    {
      preserveState: true,
      replace: true,
      onSuccess: (page) => {
        const newRequests = page.props.requests as typeof props.requests
        listData.value = [...newRequests.data]
        currentPage.value = newRequests.current_page
        finished.value = newRequests.current_page >= newRequests.last_page
      },
    }
  )
}

// 💡 1. Handler Pull To Refresh dengan Delay Spinner yang Cukup
const handleRefresh = () => {
  isRefreshing.value = true

  const startTime = Date.now()

  router.get(
    route('material-requests.index'),
    { search: searchQuery.value },
    {
      preserveStatus: false,
      onSuccess: (page) => {
        const newRequests = page.props.requests as typeof props.requests
        listData.value = [...newRequests.data]
        currentPage.value = newRequests.current_page
        finished.value = newRequests.current_page >= newRequests.last_page

        // Pastikan spinner berputar minimal 800ms agar animasi terasa mulus
        const elapsedTime = Date.now() - startTime
        const remainingTime = Math.max(0, 800 - elapsedTime)

        setTimeout(() => {
          isRefreshing.value = false
        }, remainingTime)
      },
      onError: () => {
        isRefreshing.value = false
      }
    }
  )
}

// 2. Handler Infinite Load More
const handleLoadMore = async () => {
  if (finished.value || currentPage.value >= props.requests.last_page) {
    loading.value = false
    finished.value = true
    return
  }

  try {
    const nextPage = currentPage.value + 1
    const response = await axios.get(route('material-requests.index'), {
      params: {
        page: nextPage,
        search: searchQuery.value,
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      }
    })

    const paginatedData = response.data.requests || response.data
    if (paginatedData && paginatedData.data) {
      listData.value.push(...paginatedData.data)
      currentPage.value = paginatedData.current_page
      if (paginatedData.current_page >= paginatedData.last_page) {
        finished.value = true
      }
    } else {
      finished.value = true
    }
  } catch (err) {
    console.error('Error loading more:', err)
  } finally {
    loading.value = false
  }
}

const getWorkflowColor = (status: string) => {
  switch (status) {
    case 'Fully Approved': return 'success'
    case 'Pending Manager': return 'warning'
    case 'Pending FM/GM': return 'warning'
    case 'Pending Direksi': return 'info'
    case 'Verifikasi Gudang': return 'info'
    case 'Revision': return 'warning'
    case 'Rejected': return 'danger'
    default: return 'default'
  }
}

const pipelineSteps = ['Pengajuan', 'Manager', 'FM/GM', 'Direksi', 'Gudang', 'Selesai']

const openDetail = (id: number) => {
  window.location.href = baseUrl + '/material-requests/' + id
}

const openPrint = (id: number) => {
  window.location.href = baseUrl + '/material-requests/' + id + '/print'
}

const actionLabel = (action: string) => {
  const map: any = {
    forward: 'Diteruskan',
    acknowledge: 'Acknowledge',
    approve: 'Disetujui',
    reject: 'Ditolak',
    revision: 'Revisi',
    stock_available: 'Stok Tersedia',
    stock_unavailable: 'Stok Tidak Ada',
    gudang_edit: 'Diedit Gudang',
    admin_edit: 'Diedit Admin',
  }
  return map[action] || action
}

const goBack = () => {
  router.get(route('dashboard'))
}

const goToCreate = () => {
  router.get(route('material-requests.create'))
}
</script>

<template>
  <Head title="Daftar Material Request - SUKIRMAN" />

  <div class="android-layout">
    <!-- App Bar Standar -->
    <var-app-bar v-if="!isSearching" title="Daftar Material Request" title-position="center">
      <template #left>
        <var-button round text @click="goBack">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>

      <template #right>
        <var-button round text @click="enableSearch">
          <var-icon name="magnify" :size="24" />
        </var-button>
      </template>
    </var-app-bar>

    <!-- App Bar Mode Pencarian -->
    <var-app-bar v-else class="search-app-bar">
      <template #left>
        <var-button round text @click="disableSearch">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>

      <!-- Input Pencarian: Di-submit hanya saat tekan Enter (@keydown.enter) -->
      <var-input
        v-model="searchQuery"
        placeholder="Ketik kata kunci lalu tekan Enter..."
        clearable
        focus
        class="search-input"
        @keydown.enter="performSearch"
        @clear="performSearch"
      />

      <template #right>
        <var-button round text @click="performSearch">
          <var-icon name="magnify" :size="24" />
        </var-button>
      </template>
    </var-app-bar>

    <!-- Content Area dengan PullRefresh & VarList -->
    <main class="android-content">
      <var-pull-refresh v-model="isRefreshing" @refresh="handleRefresh">
        <var-list
          v-model:loading="loading"
          :finished="finished"
          loading-text="Memuat data..."
          finished-text="Sudah menampilkan semua usulan"
          @load="handleLoadMore"
        >
          <!-- Empty State -->
          <div v-if="listData.length === 0 && !loading" class="empty-state">
            <var-icon name="text-box-remove-outline" :size="64" color="#cbd5e1" />
            <p>Belum ada pengajuan Material Request.</p>
          </div>

          <!-- List Cards -->
          <div v-else class="request-list">
            <div v-for="mr in listData" :key="mr.id" class="request-card" @click="openDetail(mr.id)">
              <div class="request-header">
                <span class="mr-number">{{ mr.mr_number }}</span>
                <var-chip :type="getWorkflowColor(mr.status_workflow)" size="small" round>
                  {{ mr.status_workflow }}
                </var-chip>
              </div>

              <div class="mr-meta">
                <span>Pabrik: <strong>{{ mr.factory }}</strong></span>
                <span>Jenis: <strong>{{ mr.type }}</strong></span>
                <span>Urgensi: <strong>{{ mr.status_pembelian }}</strong></span>
                <span>{{ mr.created_at }}</span>
              </div>

              <!-- Progress Alur Persetujuan -->
              <div class="progress-wrap">
                <div class="progress-step" v-for="(st, idx) in pipelineSteps" :key="st">
                  <div
                    class="progress-dot"
                    :class="{
                      done: mr.stage > idx && !mr.rejected,
                      current: mr.stage === idx && !mr.rejected,
                      rejected: mr.rejected && idx >= mr.stage,
                    }"
                  >
                    <var-icon v-if="mr.stage > idx && !mr.rejected" name="check" :size="10" color="#fff" />
                  </div>
                  <span class="progress-label" :class="{ active: mr.stage === idx || (mr.stage > idx && !mr.rejected) }">{{ st }}</span>
                </div>
                <div v-if="mr.rejected" class="rejected-banner">
                  <var-icon name="close-circle" :size="14" color="#ef4444" /> Ditolak
                </div>
                <div v-else-if="mr.revision" class="rejected-banner revision">
                  <var-icon name="pencil" :size="14" color="#f59e0b" /> Perlu Revisi — kirim ulang
                </div>
              </div>

              <var-divider />

              <div class="items-summary">
                <p class="summary-label">Daftar Barang ({{ mr.items.length }} item):</p>
                <ul class="item-bullets">
                  <li v-for="item in mr.items.slice(0, 3)" :key="item.id">
                    {{ item.item_name }} — <strong>{{ item.qty }} {{ item.unit }}</strong>
                  </li>
                </ul>
                <span v-if="mr.items.length > 3" class="more-items">+{{ mr.items.length - 3 }} item lainnya</span>
              </div>

              <!-- Riwayat singkat -->
              <div v-if="mr.logs && mr.logs.length" class="logs-box">
                <p class="logs-title">Riwayat:</p>
                <div v-for="(lg, li) in mr.logs" :key="li" class="log-line">
                  <span class="log-role">{{ lg.role }}</span>
                  <span class="log-action" :class="lg.action === 'reject' ? 'danger' : ''">{{ actionLabel(lg.action) }}</span>
                  <span class="log-user">{{ lg.user_name }}</span>
                  <span class="log-time">{{ lg.time }}</span>
                </div>
              </div>

              <!-- Tombol Edit untuk status Revision -->
              <button v-if="mr.status_workflow === 'Revision'" class="revision-btn" @click.stop="router.get(route('material-requests.revision-edit', mr.id))">
                ✏️ Perbaiki & Kirim Ulang
              </button>

              <!-- Aksi -->
              <div class="card-actions">
                <button class="action-btn detail" @click.stop="openDetail(mr.id)"><var-icon name="eye-outline" :size="16" style="margin-right:4px" /> Detail</button>
                <button class="action-btn print" @click.stop="openPrint(mr.id)"><var-icon name="printer-outline" :size="16" style="margin-right:4px" /> Cetak</button>
              </div>
            </div>
          </div>
        </var-list>
      </var-pull-refresh>
    </main>

    <!-- FAB Add -->
    <var-button
      type="primary"
      fab
      class="fab-add-btn"
      @click="goToCreate"
    >
      <var-icon name="plus" :size="28" />
    </var-button>
  </div>
</template>

<style scoped>
.android-layout {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: #f8fafc;
  font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1e293b;
  overflow: hidden;
  position: relative;
}

.search-app-bar {
  background-color: #ffffff !important;
  color: #1e293b !important;
}

.search-input {
  width: 100%;
  --field-decorator-placeholder-color: #94a3b8;
}

.android-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 80px 20px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 60px;
  color: #94a3b8;
}

.request-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.request-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.request-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.mr-number {
  font-family: monospace;
  font-weight: 800;
  color: #0f172a;
  font-size: 13px;
}

.mr-meta {
  display: flex;
  gap: 12px;
  font-size: 11px;
  color: #64748b;
  margin-bottom: 8px;
}

.items-summary {
  font-size: 12px;
  color: #334155;
}

.summary-label {
  margin: 0 0 4px 0;
  font-weight: 600;
  color: #64748b;
}

.item-bullets {
  margin: 0;
  padding-left: 16px;
}

.more-items {
  font-size: 11px;
  color: var(--color-primary, #6200ee);
  font-weight: 600;
  display: inline-block;
  margin-top: 4px;
}

.fab-add-btn {
  position: fixed !important;
  right: 20px;
  bottom: 24px;
  z-index: 99;
  box-shadow: 0 4px 12px rgba(98, 0, 238, 0.3) !important;
}
.revision-btn {
  display:block;width:100%;margin-top:8px;padding:8px;border-radius:10px;
  background:#fef3c7;border:1px solid #f59e0b;color:#92400e;font-weight:700;font-size:13px;
  cursor:pointer;transition:background 0.2s;font-family:inherit;
}
.revision-btn:hover { background:#fde68a; }

.progress-wrap { margin: 4px 0 10px; padding: 10px 6px; background: #f8fafc; border-radius: 12px; border: 1px solid #eef2f7; }
.progress-step { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; width: 16.66%; }
.progress-dot { width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border: 2px solid #cbd5e1; }
.progress-dot.done { background: #10b981; border-color: #10b981; }
.progress-dot.current { background: #4f46e5; border-color: #4f46e5; box-shadow: 0 0 0 4px #e0e7ff; }
.progress-dot.rejected { background: #ef4444; border-color: #ef4444; }
.progress-label { font-size: 8.5px; color: #94a3b8; text-align: center; line-height: 1.1; }
.progress-label.active { color: #334155; font-weight: 700; }
.rejected-banner { display: flex; align-items: center; justify-content: center; gap: 4px; margin-top: 8px; font-size: 12px; font-weight: 700; color: #ef4444; background: #fef2f2; border-radius: 8px; padding: 6px; }
.rejected-banner.revision { color: #b45309; background: #fef3c7; }

.logs-box { margin-top: 8px; background: #f8fafc; border-radius: 10px; padding: 8px 10px; }
.logs-title { margin: 0 0 4px 0; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
.log-line { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #475569; padding: 2px 0; }
.log-role { font-weight: 700; color: #4f46e5; min-width: 58px; }
.log-action { color: #059669; font-weight: 600; }
.log-action.danger { color: #dc2626; }
.log-user { flex: 1; }
.log-time { color: #94a3b8; font-size: 10px; }

.card-actions { display: flex; gap: 8px; margin-top: 10px; }
.action-btn { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; font-family: inherit; }
.action-btn.detail { background: #4f46e5; color: #fff; }
.action-btn.print { background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; }
</style>
