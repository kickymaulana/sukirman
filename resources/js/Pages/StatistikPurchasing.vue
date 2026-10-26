<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface Purchasing { id: number; name: string; nik: string | null; total: number }
interface Filters { period: 'all' | 'week' | 'month' | 'custom'; start_date: string; end_date: string }

const props = defineProps<{ purchasings: Purchasing[]; total_all: number; filters: Filters }>()
const baseUrl = (usePage().props as any).app_url || ''
const period = ref<Filters['period']>(props.filters.period)
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const applyFilter = () => {
    router.get(route('statistik-purchasing'), {
        period: period.value,
        ...(period.value === 'custom' ? { start_date: startDate.value, end_date: endDate.value } : {}),
    }, { preserveState: true, replace: true })
}
const openPurchasing = (id: number) => {
    window.location.href = `${baseUrl}/monitoring-items?purchasing_user_id=${id}`
}
const goBack = () => {
    if (document.referrer.startsWith(window.location.origin)) { window.history.back(); return }
    router.get(route('dashboard'))
}
</script>

<template>
    <Head title="Statistik Purchasing - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📊 Statistik Purchasing" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right><var-button round text aria-label="Ke beranda" @click="router.get(route('dashboard'))"><var-icon name="home-outline" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="head-bar">
                <div>
                    <h2 class="title">Item yang Di-PO</h2>
                    <p class="sub">{{ total_all }} item sudah memiliki PO berdasarkan tanggal PO.</p>
                </div>
            </div>
            <div class="filter-card">
                <label class="filter-label" for="period">Periode tanggal PO</label>
                <div class="filter-line">
                    <select id="period" v-model="period" class="filter-select">
                        <option value="all">Semua tanggal</option>
                        <option value="week">Minggu ini</option>
                        <option value="month">Bulan ini</option>
                        <option value="custom">Rentang tanggal</option>
                    </select>
                    <button class="apply-button" type="button" @click="applyFilter">Terapkan</button>
                </div>
                <div v-if="period === 'custom'" class="date-line">
                    <label>Dari <input v-model="startDate" type="date" class="date-input" /></label>
                    <label>Sampai <input v-model="endDate" type="date" class="date-input" /></label>
                </div>
            </div>
            <div class="list">
                <button v-for="(p, i) in purchasings" :key="p.id" class="row" @click="openPurchasing(p.id)">
                    <span class="rank">{{ i + 1 }}</span>
                    <span class="info">
                        <span class="name">{{ p.name }}</span>
                        <span class="nik">{{ p.nik || '-' }}</span>
                    </span>
                    <span class="total">{{ p.total }} item</span>
                    <var-icon name="chevron-right" :size="20" color="#94a3b8" />
                </button>
                <p v-if="!purchasings.length" class="empty">Belum ada item yang di-PO.</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px;max-width:840px;margin:0 auto;width:100%; }
.title { margin:0;font-size:20px;font-weight:800;color:#0f172a; }
.sub { margin:4px 0 0;font-size:13px;color:#64748b; }
.filter-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px; }
.filter-label { display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:8px; }
.filter-line { display:flex;gap:8px; }
.filter-select,.date-input { flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:9px;background:#fff;color:#0f172a;font:inherit; }
.apply-button { border:0;border-radius:8px;padding:9px 16px;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer; }
.date-line { display:flex;gap:12px;margin-top:10px;font-size:12px;color:#475569; }
.date-input { display:block;margin-top:4px; }
.list { display:flex;flex-direction:column;gap:8px; }
.row { display:flex;align-items:center;gap:14px;padding:12px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;cursor:pointer;font-family:inherit;width:100%;text-align:left; }
.row:hover { background:#eef2ff;border-color:#c7d2fe; }
.rank { width:28px;height:28px;border-radius:50%;background:#4f46e5;color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.info { flex:1;display:flex;flex-direction:column; }
.name { font-size:14px;font-weight:700;color:#0f172a; }
.nik { font-size:11px;color:#64748b;font-family:monospace; }
.total { color:#4f46e5;font-weight:800;font-size:14px;white-space:nowrap; }
.empty { color:#94a3b8;text-align:center;padding:40px; }
</style>
