<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

// Interface Data
interface SummaryCount {
  pending: number
  processing: number
  approved: number
  rejected: number
}

interface RecentRequest {
  id: number
  code: string
  title: string
  category: string
  date: string
  status: string
}

// Props dari DashboardController
const props = defineProps<{
  user?: {
    name: string
    email?: string
    role?: string
  }
  pending_count?: number | null
  summary?: SummaryCount
  recentRequests?: RecentRequest[]
}>()

// Bottom Navigation Active State (0: Beranda, 1: Riwayat, 2: Notifikasi, 3: Profil)
const activeTab = ref(0)

// Format data statistik dari props
const summaryData = computed(() => [
  { title: 'Menunggu', count: props.summary?.pending ?? 0, icon: 'time-out', color: '#f59e0b', bgColor: '#fef3c7' },
  { title: 'Diproses', count: props.summary?.processing ?? 0, icon: 'cog-outline', color: '#3b82f6', bgColor: '#dbeafe' },
  { title: 'Disetujui', count: props.summary?.approved ?? 0, icon: 'checkbox-marked-circle-outline', color: '#10b981', bgColor: '#d1fae5' },
  { title: 'Ditolak', count: props.summary?.rejected ?? 0, icon: 'close-circle-outline', color: '#ef4444', bgColor: '#fee2e2' },
])

// Mapping Badge Varlet berdasarkan workflow
const getStatusBadgeType = (status: string) => {
  switch (status) {
    case 'Fully Approved': return 'success'
    case 'Pending Manager': return 'warning'
    case 'Pending FM/GM':
    case 'Pending Direksi':
    case 'Verifikasi Gudang': return 'info'
    case 'Rejected': return 'danger'
    default: return 'default'
  }
}

const baseUrl = (usePage().props as any).app_url || ''
const routerInertia = router

const goApproval = () => {
  const r = props.user?.role?.toLowerCase()
  const routes: any = { manager: '/approval/manager', 'fm/gm': '/approval/fmgm', direksi: '/approval/direksi', gudang: '/approval/gudang', purchasing: '/approval/purchasing', mtc: '/approval/mtc', it: '/approval/it', hrd: '/approval/hrd' }
  const path = routes[r]
  if (path) routerInertia.get(baseUrl + path)
}
const notifList = ref((usePage().props as any).notifications || [])
const unreadCount = ref((usePage().props as any).unread_count || 0)

const handleLogout = () => {
  router.post(route('logout'))
}

const handleAddRequest = () => {
  router.get(route('material-requests.create'))
}

const approvalRoute = () => {
  const r = props.user?.role?.toLowerCase()
  if (r === 'manager') return '/approval/manager'
  if (r === 'fm/gm') return '/approval/fmgm'
  if (r === 'direksi') return '/approval/direksi'
  if (r === 'gudang') return '/approval/gudang'
  if (r === 'purchasing') return '/approval/purchasing'
  if (r === 'mtc') return '/approval/mtc'
  if (r === 'it') return '/approval/it'
  if (r === 'hrd') return '/approval/hrd'
  return '#'
}

const handleTabChange = (index: number) => {
  if (index === 1) {
    router.get(route('material-requests.index'))
  } else if (index === 2) {
    router.get(route('notifications.index'))
  } else if (index === 3) {
    router.get(route('profile.index'))
  }
}
</script>

<template>
  <Head title="Dashboard - SUKIRMAN" />

  <div class="android-layout">
    <!-- Header -->
    <header class="top-app-bar">
      <div class="user-greeting">
        <var-avatar src="https://varletjs.org/cat.jpg" size="small" round />
        <div class="user-info">
          <span class="greeting-subtitle">Halo, Selamat Datang 👋</span>
          <h2 class="user-name">{{ user?.name || 'Kicky Maulana' }}</h2>
        </div>
      </div>

      <div class="header-actions"></div>
    </header>

    <main class="android-content">
      <!-- Welcome Card -->
      <div class="welcome-card">
        <div class="welcome-text">
          <h3>Buat Usulan Barang Lebih Mudah!</h3>
          <p>Pantau status pengajuan kebutuhan barang harianmu di sini.</p>
        </div>
        <var-icon name="clipboard-text-outline" class="welcome-icon" />
      </div>

      <!-- MR Saya (untuk semua user) -->
      <div class="my-mr-card" @click="router.get(baseUrl + '/my-mrs')">
        <div class="my-mr-icon">📋</div>
        <div class="my-mr-text">
          <span class="my-mr-title">MR Saya</span>
          <span class="my-mr-count">Lihat semua MR yang terkait dengan Anda</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <!-- Stats Grid -->
      <div class="section-header">
        <h3 class="section-title">Status Pengajuan</h3>
      </div>

      <div class="stats-grid">
        <div v-for="(stat, index) in summaryData" :key="index" class="stat-card">
          <div class="stat-icon-wrapper" :style="{ backgroundColor: stat.bgColor }">
            <var-icon :name="stat.icon" :size="22" :color="stat.color" />
          </div>
          <div class="stat-info">
            <span class="stat-count">{{ stat.count }}</span>
            <span class="stat-title">{{ stat.title }}</span>
          </div>
        </div>
      </div>

      <!-- Overview MR (admin = edit, Purchasing/Gudang = lihat saja) -->
      <div v-if="user?.role && ['admin','Purchasing','Gudang'].includes(user.role)" class="overview-card" @click="router.get(baseUrl + '/admin/overview')">
        <div class="overview-icon">📊</div>
        <div class="overview-text">
          <span class="overview-title">{{ user.role === 'admin' ? 'Dashboard Admin' : 'Monitoring MR' }}</span>
          <span class="overview-count">{{ user.role === 'admin' ? 'Pantau MR & ubah tujuan approval' : 'Lihat statistik & alur MR (read-only)' }}</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <div v-if="user?.role === 'admin'" class="admin-card" @click="router.get(baseUrl + '/admin/users')">
        <div class="admin-icon">👥</div>
        <div class="admin-text">
          <span class="admin-title">Kelola User</span>
          <span class="admin-count">{{ pending_count ?? 0 }} user baru menunggu</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <!-- Approval Card (untuk approver) -->
      <div v-if="user?.role && ['Manager','FM/GM','Direksi','Gudang','Purchasing','MTC','IT','HRD'].includes(user.role)" class="approval-card" @click="goApproval()">
        <div class="approval-icon"><var-icon name="clipboard-check" :size="28" color="#4f46e5" /></div>
        <div class="approval-text">
          <span class="approval-title">Approval {{ user.role }}</span>
          <span class="approval-count">{{ pending_count ?? 0 }} MR menunggu</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <!-- Manajemen Barang (Gudang, Purchasing, admin) -->
      <div v-if="user?.role && ['Gudang','Purchasing','admin'].includes(user.role)" class="barang-card" @click="router.get(baseUrl + '/barangs')">
        <div class="barang-icon">📦</div>
        <div class="barang-text">
          <span class="barang-title">Manajemen Barang</span>
          <span class="barang-count">Kelola data barang</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <!-- Pengaturan (Gudang, Purchasing, admin) -->
      <div v-if="user?.role && ['Gudang','Purchasing','admin'].includes(user.role)" class="settings-card" @click="router.get(baseUrl + '/settings')">
        <div class="settings-icon">⚙️</div>
        <div class="settings-text">
          <span class="settings-title">Pengaturan</span>
          <span class="settings-count">Set ID Cabang Accurate</span>
        </div>
        <var-icon name="chevron-right" :size="24" color="#94a3b8" />
      </div>

      <!-- Request List -->
      <div class="section-header space-between">
        <h3 class="section-title">Usulan Terakhir</h3>
        <Link :href="route('material-requests.index')" class="see-all-link">Lihat Semua</Link>
      </div>

      <!-- State Jika Belum Ada Data -->
      <div v-if="!recentRequests || recentRequests.length === 0" class="empty-card">
        <var-icon name="text-box-remove-outline" :size="48" color="#cbd5e1" />
        <p>Belum ada pengajuan Material Request.</p>
      </div>

      <!-- List Request dari Database -->
      <div v-else class="request-list">
        <div v-for="item in recentRequests" :key="item.id" class="request-card">
          <div class="request-main">
            <div class="request-header">
              <span class="request-code">{{ item.code }}</span>
              <var-chip :type="getStatusBadgeType(item.status)" size="small" round>
                {{ item.status }}
              </var-chip>
            </div>
            <h4 class="request-item-title">{{ item.title }}</h4>
            <div class="request-footer">
              <span class="request-category">
                <var-icon name="folder-outline" :size="14" /> {{ item.category }}
              </span>
              <span class="request-date">{{ item.date }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Bottom Navigation -->
    <var-bottom-navigation
      v-model:active="activeTab"
      fixed
      placeholder
      @change="handleTabChange"
      @fab-click="handleAddRequest"
    >
      <var-bottom-navigation-item label="Beranda" icon="home-outline" />
      <var-bottom-navigation-item label="Riwayat" icon="history" />
      <var-bottom-navigation-item label="Notifikasi" icon="bell-outline" :badge="{ value: unreadCount, max: 99 }" />
      <var-bottom-navigation-item label="Profil" icon="account-circle" />

      <template #fab>
        <var-icon name="plus" :size="28" />
      </template>
    </var-bottom-navigation>
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
}

.top-app-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 20px 12px 20px;
  background-color: #ffffff;
  border-bottom: 1px solid #f1f5f9;
  position: sticky;
  top: 0;
  z-index: 10;
}

.user-greeting { display: flex; align-items: center; gap: 12px; }
.user-info { display: flex; flex-direction: column; }
.greeting-subtitle { font-size: 11px; color: #64748b; font-weight: 500; }
.user-name { font-size: 16px; font-weight: 700; margin: 0; color: #0f172a; }

.android-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 100px 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.welcome-card {
  background: linear-gradient(135deg, var(--color-primary, #6200ee), #8b5cf6);
  border-radius: 20px;
  padding: 20px;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 10px 20px -5px rgba(98, 0, 238, 0.3);
}

.welcome-text h3 { margin: 0 0 6px 0; font-size: 16px; font-weight: 700; }
.welcome-text p { margin: 0; font-size: 12px; opacity: 0.88; line-height: 1.4; max-width: 220px; }
.welcome-icon { font-size: 48px !important; opacity: 0.3; }

.section-header { display: flex; align-items: center; margin-top: 4px; }
.section-header.space-between { justify-content: space-between; }
.section-title { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0; }
.see-all-link { font-size: 12px; color: var(--color-primary, #6200ee); text-decoration: none; font-weight: 600; }

.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.stat-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.stat-icon-wrapper {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-info { display: flex; flex-direction: column; }
.stat-count { font-size: 18px; font-weight: 800; color: #0f172a; line-height: 1.2; }
.stat-title { font-size: 11px; color: #64748b; font-weight: 500; }

.category-scroll {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: none;
}
.category-scroll::-webkit-scrollbar { display: none; }
.category-item { display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 70px; }
.cat-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}
.category-item span { font-size: 11px; font-weight: 500; color: #475569; text-align: center; }

.bg-purple { background-color: #8b5cf6; }
.bg-blue { background-color: #3b82f6; }
.bg-green { background-color: #10b981; }
.bg-orange { background-color: #f59e0b; }

.empty-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  border: 1px dashed #cbd5e1;
  color: #94a3b8;
  font-size: 13px;
}

.request-list { display: flex; flex-direction: column; gap: 12px; }
.request-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.request-code { font-size: 11px; font-family: monospace; font-weight: 700; color: #64748b; background-color: #f1f5f9; padding: 2px 8px; border-radius: 6px; }
.request-item-title { margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #0f172a; }
.request-footer { display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #94a3b8; }
.request-category { display: flex; align-items: center; gap: 4px; }

.admin-card {
  display:flex;align-items:center;gap:14px;background:#fef3c7;border-radius:16px;padding:16px;
  border:2px solid #fde68a;cursor:pointer;margin-bottom:12px;
}
.admin-icon { font-size:32px; }
.admin-text { flex:1;display:flex;flex-direction:column; }
.admin-title { font-size:14px;font-weight:700;color:#92400e; }
.admin-count { font-size:12px;color:#b45309; }

.overview-card {
  display:flex;align-items:center;gap:14px;background:#f3f0ff;border-radius:16px;padding:16px;
  border:2px solid #ddd6fe;cursor:pointer;margin-bottom:12px;
}
.overview-icon { font-size:32px; }
.overview-text { flex:1;display:flex;flex-direction:column; }
.overview-title { font-size:14px;font-weight:700;color:#6d28d9; }
.overview-count { font-size:12px;color:#7c3aed; }

.my-mr-card {
  display:flex;align-items:center;gap:14px;background:#ecfeff;border-radius:16px;padding:16px;
  border:2px solid #a5f3fc;cursor:pointer;margin-bottom:12px;
}
.my-mr-icon { font-size:32px; }
.my-mr-text { flex:1;display:flex;flex-direction:column; }
.my-mr-title { font-size:14px;font-weight:700;color:#0e7490; }
.my-mr-count { font-size:12px;color:#06b6d4; }

.approval-card {
  display:flex;align-items:center;gap:14px;background:#eef2ff;border-radius:16px;padding:16px;
  border:2px solid #c7d2fe;cursor:pointer;margin-bottom:12px;
}
.approval-icon { width:44px;height:44px;border-radius:12px;background:#e0e7ff;display:flex;align-items:center;justify-content:center; }
.approval-text { flex:1;display:flex;flex-direction:column; }
.approval-title { font-size:14px;font-weight:700;color:#4f46e5; }
.approval-count { font-size:12px;color:#6366f1; }

.barang-card {
  display:flex;align-items:center;gap:14px;background:#ecfdf5;border-radius:16px;padding:16px;
  border:2px solid #a7f3d0;cursor:pointer;margin-bottom:12px;
}
.barang-icon { font-size:32px; }
.barang-text { flex:1;display:flex;flex-direction:column; }
.barang-title { font-size:14px;font-weight:700;color:#047857; }
.barang-count { font-size:12px;color:#059669; }

.settings-card {
  display:flex;align-items:center;gap:14px;background:#f0f9ff;border-radius:16px;padding:16px;
  border:2px solid #bae6fd;cursor:pointer;margin-bottom:12px;
}
.settings-icon { font-size:32px; }
.settings-text { flex:1;display:flex;flex-direction:column; }
.settings-title { font-size:14px;font-weight:700;color:#0369a1; }
.settings-count { font-size:12px;color:#0284c7; }

.notif-badge {
  position:absolute;top:-2px;right:-4px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;
  min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;
}
.notif-dropdown {
  position:absolute;top:44px;right:0;width:320px;max-height:400px;background:#fff;border-radius:16px;
  box-shadow:0 8px 30px rgba(0,0,0,0.12);z-index:999;overflow-y:auto;border:1px solid #f1f5f9;
}
.notif-header { padding:14px 16px;font-size:14px;font-weight:700;border-bottom:1px solid #f1f5f9; }
.notif-empty { padding:24px;text-align:center;color:#94a3b8;font-size:13px; }
.notif-item { padding:12px 16px;border-bottom:1px solid #f8fafc;cursor:pointer; }
.notif-item:hover { background:#f8fafc; }
.notif-msg { margin:0;font-size:13px;color:#0f172a; }
.notif-time { font-size:11px;color:#94a3b8; }
</style>
