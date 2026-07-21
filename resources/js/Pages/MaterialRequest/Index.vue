<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

interface RequestItem {
  id: number
  item_name: string
  qty: number
  unit: string
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
}

defineProps<{
  requests: {
    data: MaterialRequestData[]
  }
}>()

const getWorkflowColor = (status: string) => {
  switch (status) {
    case 'Fully Approved': return 'success'
    case 'Pending Manager': return 'warning'
    case 'Pending Direksi': return 'info'
    case 'Rejected': return 'danger'
    default: return 'default'
  }
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
    <!-- App Bar dengan Judul di Tengah -->
    <var-app-bar title="Daftar Material Request" title-position="center">
      <template #left>
        <var-button round text @click="goBack">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>

      <template #right>
        <var-button round text>
          <var-icon name="magnify" :size="24" />
        </var-button>
      </template>
    </var-app-bar>

    <main class="android-content">
      <div v-if="requests.data.length === 0" class="empty-state">
        <var-icon name="text-box-remove-outline" :size="64" color="#cbd5e1" />
        <p>Belum ada pengajuan Material Request.</p>
      </div>

      <div v-else class="request-list">
        <div v-for="mr in requests.data" :key="mr.id" class="request-card">
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
        </div>
      </div>
    </main>

    <!-- Floating Action Button (FAB) Kanan Bawah untuk Tambah Usulan -->
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

.android-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 80px 20px; /* Padding ekstra di bawah agar tidak tertutup FAB */
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

/* Floating Action Button di Kanan Bawah */
.fab-add-btn {
  position: fixed !important;
  right: 20px;
  bottom: 24px;
  z-index: 99;
  box-shadow: 0 4px 12px rgba(98, 0, 238, 0.3) !important;
}
</style>
