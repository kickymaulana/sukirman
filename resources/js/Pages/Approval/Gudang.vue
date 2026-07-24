<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'

interface MR { id: number; mr_number: string; factory: string; user: { name: string }; items: any[] }
const props = defineProps<{ requests: { data: MR[] } }>()
const baseUrl = (usePage().props as any).app_url || ''

const openDetail = (id: number) => { window.location.href = baseUrl + '/material-requests/' + id }
</script>

<template>
    <Head title="Gudang - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="Verifikasi Gudang" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div v-if="!requests.data.length" class="empty">Tidak ada MR</div>
            <div v-for="mr in requests.data" :key="mr.id" class="card" @click="openDetail(mr.id)">
                <div class="card-h"><span class="mr-num">{{ mr.mr_number }}</span><var-chip type="success" size="mini">Verifikasi</var-chip></div>
                <p><strong>{{ mr.user?.name }}</strong> • {{ mr.factory }}</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9;margin-bottom:10px;cursor:pointer; }
.card-h { display:flex;justify-content:space-between;align-items:center;margin-bottom:6px; }
.mr-num { font-family:monospace;font-weight:800;font-size:13px; }
p { margin:2px 0;font-size:13px;color:#475569; }
</style>
