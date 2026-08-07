<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface Pengaju { id: number; name: string; nik: string | null; total: number }

const props = defineProps<{ pengajus: Pengaju[]; total_all: number; filters?: { search?: string } }>()
const baseUrl = (usePage().props as any).app_url || ''

const searchVal = ref(props.filters?.search || '')

const search = () => {
    router.get(baseUrl + '/statistik-pengaju', { search: searchVal.value || undefined }, { preserveState: true })
}

const openPengaju = (name: string) => {
    window.location.href = baseUrl + '/approval/purchasing?search=' + encodeURIComponent(name)
}

const goBack = () => router.get(route('dashboard'))
</script>

<template>
    <Head title="Statistik Pengaju - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📊 Statistik Pengaju" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="head-bar">
                <div>
                    <h2 class="title">Daftar Pengaju</h2>
                    <p class="sub">Klik nama pengaju untuk melihat semua MR yang dia ajukan.{{ props.total_all || 0 }} pengaju</p>
                </div>
            </div>

            <div class="filter-row">
                <var-input v-model="searchVal" placeholder="Cari nama / NIK pengaju..." clearable @keyup.enter="search" style="flex:1;max-width:320px" />
                <var-button type="primary" @click="search"><var-icon name="magnify" :size="16" /></var-button>
            </div>

            <div class="list">
                <button v-for="(p, i) in pengajus" :key="p.id" class="row" @click="openPengaju(p.name)">
                    <span class="rank">{{ i + 1 }}</span>
                    <span class="info">
                        <span class="name">{{ p.name }}</span>
                        <span class="nik">{{ p.nik || '-' }}</span>
                    </span>
                    <span class="total">{{ p.total }} MR</span>
                    <var-icon name="chevron-right" :size="20" color="#94a3b8" />
                </button>
                <p v-if="!pengajus.length" class="empty">Tidak ada pengaju.</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px;max-width:840px;margin:0 auto;width:100%; }
.title { margin:0;font-size:20px;font-weight:800;color:#0f172a; }
.sub { margin:4px 0 0;font-size:13px;color:#64748b; }
.filter-row { display:flex;gap:10px;align-items:center; }
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