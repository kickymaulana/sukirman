<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const baseUrl = (usePage().props as any).app_url || ''

interface MaterialRequestData {
  id: number
  mr_number: string
  status_workflow: string
  created_at: string
  user_name: string
  jenis: string
  direksi_name: string | null
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

const isSearching = ref(!!props.filters?.search)
const searchQuery = ref(props.filters?.search || '')

const isRefreshing = ref(false)
const loading = ref(false)
const finished = ref(props.requests.current_page >= props.requests.last_page)
const listData = ref<MaterialRequestData[]>([...props.requests.data])
const currentPage = ref(props.requests.current_page)

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

const openDetail = (id: number) => {
  window.location.href = baseUrl + '/material-requests/' + id
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

    <var-app-bar v-else class="search-app-bar">
      <template #left>
        <var-button round text @click="disableSearch">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>

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

    <main class="android-content">
      <var-pull-refresh v-model="isRefreshing" @refresh="handleRefresh">
        <var-list
          v-model:loading="loading"
          :finished="finished"
          loading-text="Memuat data..."
          finished-text="Sudah menampilkan semua usulan"
          @load="handleLoadMore"
        >
          <div v-if="listData.length === 0 && !loading" class="empty-state">
            <var-icon name="text-box-remove-outline" :size="64" color="#cbd5e1" />
            <p>Belum ada pengajuan Material Request.</p>
          </div>

          <div v-else class="request-list">
            <div v-for="mr in listData" :key="mr.id" class="request-card" @click="openDetail(mr.id)">
              <div class="request-header">
                <span class="mr-number">{{ mr.mr_number }}</span>
                <var-chip :type="getWorkflowColor(mr.status_workflow)" size="small" round>
                  {{ mr.status_workflow }}
                </var-chip>
              </div>

              <div class="mr-meta">
                <span>Pengaju: <strong>{{ mr.user_name }}</strong></span>
                <span>Jenis: <strong>{{ mr.jenis }}</strong></span>
                <span v-if="mr.direksi_name">Direksi: <strong>{{ mr.direksi_name }}</strong></span>
                <span>{{ mr.created_at }}</span>
              </div>
            </div>
          </div>
        </var-list>
      </var-pull-refresh>
    </main>

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
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: #64748b;
}

.fab-add-btn {
  position: fixed !important;
  right: 20px;
  bottom: 24px;
  z-index: 99;
  box-shadow: 0 4px 12px rgba(98, 0, 238, 0.3) !important;
}
</style>