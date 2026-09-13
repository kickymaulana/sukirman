<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

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
  recentRequests?: RecentRequest[]
}>()

// Bottom Navigation Active State (0: Beranda, 1: Riwayat, 2: Notifikasi, 3: Profil)
const activeTab = ref(0)

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

const openDetail = (id: number) => {
  routerInertia.get(baseUrl + '/material-requests/' + id)
}

const goApproval = () => {
  const r = props.user?.role?.toLowerCase()
  const routes: any = { manager: '/approval/manager', 'fm/gm': '/approval/fmgm', direksi: '/approval/direksi', gudang: '/approval/gudang', purchasing: '/approval/purchasing', mtc: '/approval/mtc', it: '/approval/it', hrd: '/approval/hrd', admin: '/approval/purchasing' }
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

      <!-- Menu Cards -->
      <div class="menu-grid">
        <!-- MR Saya (untuk semua user) -->
        <div class="menu-card" @click="router.get(baseUrl + '/my-mrs')">
          <span class="menu-icon">📋</span>
          <span class="menu-title">MR Saya</span>
          <span class="menu-desc">Lihat semua MR yang terkait dengan Anda</span>
        </div>

        <!-- Dashboard Admin (khusus admin: edit MR & tujuan) -->
        <div v-if="user?.role === 'admin'" class="menu-card" @click="router.get(baseUrl + '/admin/overview')">
          <span class="menu-icon">⚙️</span>
          <span class="menu-title">Dashboard Admin</span>
          <span class="menu-desc">Pantau MR & ubah tujuan approval</span>
        </div>

        <!-- Statistik Pengaju (semua user) -->
        <div v-if="user" class="menu-card" @click="router.get(baseUrl + '/statistik-pengaju')">
          <span class="menu-icon">📊</span>
          <span class="menu-title">Statistik Pengaju</span>
          <span class="menu-desc">Lihat daftar pengaju & semua MR-nya</span>
        </div>

        <!-- Statistik Direksi (admin, Purchasing) -->
        <div v-if="user?.role && ['admin','Purchasing'].includes(user.role)" class="menu-card" @click="router.get(baseUrl + '/statistik-direksi')">
          <span class="menu-icon">👁️</span>
          <span class="menu-title">Statistik Direksi</span>
          <span class="menu-desc">Lihat MR Pending Direksi per direksi</span>
        </div>

        <div v-if="user?.role === 'admin'" class="menu-card" @click="router.get(baseUrl + '/admin/users')">
          <span class="menu-icon">👥</span>
          <span class="menu-title">Kelola User</span>
          <span class="menu-desc">{{ pending_count ?? 0 }} user baru menunggu</span>
        </div>

        <!-- Kelola Departemen (khusus admin) -->
        <div v-if="user?.role === 'admin'" class="menu-card" @click="router.get(baseUrl + '/admin/master/departemens')">
          <span class="menu-icon">🏷️</span>
          <span class="menu-title">Kelola Departemen</span>
          <span class="menu-desc">Tambah/ubah daftar departemen</span>
        </div>

        <!-- Approval Card (untuk approver) -->
        <div v-if="user?.role && ['Manager','FM/GM','Direksi','Gudang','Purchasing','MTC','IT','HRD','admin'].includes(user.role)" class="menu-card approval" @click="goApproval()">
          <span class="menu-icon">✅</span>
          <span class="menu-title">Approval {{ user.role }}</span>
          <span class="menu-desc">{{ pending_count ?? 0 }} MR menunggu</span>
        </div>

        <!-- Monitoring MR (Gudang, Purchasing, admin) -->
        <div v-if="user?.role && ['Gudang','Purchasing','admin'].includes(user.role)" class="menu-card" @click="router.get(baseUrl + '/monitoring-mr')">
          <span class="menu-icon">📋</span>
          <span class="menu-title">Monitoring MR</span>
          <span class="menu-desc">Lihat semua MR (non-Purchasing)</span>
        </div>

        <!-- Manajemen Barang (Gudang, Purchasing, admin) -->
        <div v-if="user?.role && ['Gudang','Purchasing','admin'].includes(user.role)" class="menu-card" @click="router.get(baseUrl + '/barangs')">
          <span class="menu-icon">📦</span>
          <span class="menu-title">Manajemen Barang</span>
          <span class="menu-desc">Kelola data barang</span>
        </div>

        <!-- Pengaturan (Gudang, Purchasing, admin) -->
        <div v-if="user?.role && ['Gudang','Purchasing','admin'].includes(user.role)" class="menu-card" @click="router.get(baseUrl + '/settings')">
          <span class="menu-icon">⚙️</span>
          <span class="menu-title">Pengaturan</span>
          <span class="menu-desc">Set ID Cabang Accurate</span>
        </div>
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
        <div v-for="item in recentRequests" :key="item.id" class="request-card" @click="openDetail(item.id)">
          <div class="request-header">
            <span class="request-code">{{ item.code }}</span>
            <var-chip :type="getStatusBadgeType(item.status)" size="small" round>
              {{ item.status }}
            </var-chip>
          </div>
          <div class="request-meta">
            <span>{{ item.title }}</span>
            <span>{{ item.date }}</span>
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
  cursor: pointer;
}
.request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.request-code { font-family: monospace; font-weight: 800; color: #0f172a; font-size: 13px; }
.request-meta { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #64748b; }

.menu-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.menu-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; background: #ffffff; border-radius: 18px; padding: 18px;
  border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);
  cursor: pointer; min-height: 118px; text-align: center;
}
.menu-card:active { transform: scale(0.97); }
.menu-card.approval { background: #eef2ff; border-color: #c7d2fe; }
.menu-icon { font-size: 26px; }
.menu-title { font-size: 13px; font-weight: 700; color: #1e293b; }
.menu-desc { font-size: 11px; color: #64748b; line-height: 1.3; }

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
