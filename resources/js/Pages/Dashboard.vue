<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

// Interface & State tetap seperti punya kamu
interface SummaryCard {
  title: string
  count: number
  icon: string
  color: string
  bgColor: string
}

interface RecentRequest {
  id: number
  code: string
  title: string
  category: string
  date: string
  status: 'Pending' | 'Approved' | 'Rejected' | 'Processing'
}

defineProps<{
  user?: {
    name: string
    role?: string
  }
}>()

// Bottom Navigation Active State
const activeTab = ref(0)

const summaryData = ref<SummaryCard[]>([
  { title: 'Menunggu', count: 5, icon: 'time-out', color: '#f59e0b', bgColor: '#fef3c7' },
  { title: 'Diproses', count: 3, icon: 'cog-outline', color: '#3b82f6', bgColor: '#dbeafe' },
  { title: 'Disetujui', count: 12, icon: 'checkbox-marked-circle-outline', color: '#10b981', bgColor: '#d1fae5' },
  { title: 'Ditolak', count: 1, icon: 'close-circle-outline', color: '#ef4444', bgColor: '#fee2e2' },
])

const recentRequests = ref<RecentRequest[]>([
  { id: 1, code: 'REQ-2026-004', title: 'Pengadaan Tonner Printer HP 85A', category: 'ATK & IT', date: '21 Jul 2026', status: 'Pending' },
  { id: 2, code: 'REQ-2026-003', title: 'Kabel UTP Cat6 1 Roll (305m)', category: 'Infrastruktur', date: '19 Jul 2026', status: 'Processing' },
  { id: 3, code: 'REQ-2026-002', title: 'Sapu Lidi & Cairan Pembersih', category: 'Kebersihan', date: '15 Jul 2026', status: 'Approved' },
  { id: 4, code: 'REQ-2026-001', title: 'Kursi Kerja Ergonomis Staff', category: 'Inventaris', date: '10 Jul 2026', status: 'Rejected' },
])

const getStatusBadgeType = (status: RecentRequest['status']) => {
  switch (status) {
    case 'Approved': return 'success'
    case 'Pending': return 'warning'
    case 'Processing': return 'info'
    case 'Rejected': return 'danger'
    default: return 'default'
  }
}

const getStatusLabel = (status: RecentRequest['status']) => {
  switch (status) {
    case 'Approved': return 'Disetujui'
    case 'Pending': return 'Menunggu'
    case 'Processing': return 'Diproses'
    case 'Rejected': return 'Ditolak'
    default: return status
  }
}

const handleLogout = () => {
  router.post(route('logout'))
}

// Handler saat tombol FAB (+) di tengah diklik
const handleAddRequest = () => {
  // Contoh: Navigasi ke halaman form tambah usulan
  // router.get(route('request.create'))
  console.log('Buka form usulan baru')
}
</script>

<template>
  <Head title="Dashboard - SUKIRMAN" />

  <div class="android-layout">
    <!-- Header & Content tetap sama -->
    <header class="top-app-bar">
      <div class="user-greeting">
        <var-avatar src="https://varletjs.org/cat.jpg" size="mini" />
        <div class="user-info">
          <span class="greeting-subtitle">Halo, Selamat Datang 👋</span>
          <h2 class="user-name">{{ user?.name || 'Kicky Maulana' }}</h2>
        </div>
      </div>

      <div class="header-actions">
        <var-button round transparent @click="handleLogout">
          <var-icon name="logout" :size="22" color="#424242" />
        </var-button>
      </div>
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

      <!-- Kategori Scroll -->
      <div class="section-header">
        <h3 class="section-title">Kategori Kebutuhan</h3>
      </div>

      <div class="category-scroll">
        <div class="category-item">
          <div class="cat-icon bg-purple"><var-icon name="laptop" /></div>
          <span>IT & Elektronik</span>
        </div>
        <div class="category-item">
          <div class="cat-icon bg-blue"><var-icon name="file-document-outline" /></div>
          <span>ATK</span>
        </div>
        <div class="category-item">
          <div class="cat-icon bg-green"><var-icon name="account-group-outline" /></div>
          <span>Peralatan</span>
        </div>
        <div class="category-item">
          <div class="cat-icon bg-orange"><var-icon name="package-variant-closed" /></div>
          <span>Umum</span>
        </div>
      </div>

      <!-- Request List -->
      <div class="section-header space-between">
        <h3 class="section-title">Usulan Terakhir</h3>
        <Link href="#" class="see-all-link">Lihat Semua</Link>
      </div>

      <div class="request-list">
        <div v-for="item in recentRequests" :key="item.id" class="request-card">
          <div class="request-main">
            <div class="request-header">
              <span class="request-code">{{ item.code }}</span>
              <var-chip :type="getStatusBadgeType(item.status)" size="small" round>
                {{ getStatusLabel(item.status) }}
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

    <!-- 💡 IMPLEMENTASI NATIVE VARLET BOTTOM NAVIGATION DENGAN FAB -->
    <var-bottom-navigation
      v-model:active="activeTab"
      class="bottom-nav-fixed"
      @fab-click="handleAddRequest"
    >
      <var-bottom-navigation-item label="Beranda" icon="home-outline" />
      <var-bottom-navigation-item label="Riwayat" icon="history" />
      <var-bottom-navigation-item label="Notifikasi" icon="bell-outline" badge />
      <var-bottom-navigation-item label="Profil" icon="account-circle" />

      <!-- Slot Fab Resmi Varlet -->
      <template #fab>
        <var-icon name="plus" :size="28" />
      </template>
    </var-bottom-navigation>
  </div>
</template>

<style scoped>

/* Header & Main Styling */
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


/* 1. Kunci layout utama agar tingginya pas seukuran layar */
.android-layout {
  display: flex;
  flex-direction: column;
  height: 100vh; /* Gunakan height 100vh, bukan min-height */
  background-color: #f8fafc;
  font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1e293b;
  overflow: hidden; /* Mencegah seluruh layar ter-scroll secara global */
}

/* 2. Buat area konten utama (main) yang bisa di-scroll secara independen */
.android-content {
  flex: 1;
  overflow-y: auto; /* Scroll hanya terjadi di dalam area konten ini */
  padding: 16px 20px 100px 20px; /* Tambah padding bawah agar item paling bawah tidak tertutup nav */
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* 3. Paksa Bottom Navigation melayang diam di paling bawah viewport */
.bottom-nav-fixed {
  position: fixed !important;
  bottom: 0 !important;
  left: 0 !important;
  right: 0 !important;
  z-index: 999 !important; /* Pastikan selalu berada di atas konten lain */
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06) !important;
  border-top: 1px solid #f1f5f9;
}
</style>
