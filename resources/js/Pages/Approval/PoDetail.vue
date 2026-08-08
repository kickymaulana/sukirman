<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

const props = defineProps<{ mr: any }>()
const baseUrl = (usePage().props as any).app_url || ''

const mr = props.mr

const covered = (it: any) => (it.item_po_lines || []).reduce((s: number, l: any) => s + Number(l.qty), 0)

const poStatus = computed(() => {
    const items = mr.items || []
    if (!items.length) return 'Belum'
    const allDone = items.every(i => covered(i) >= Number(i.qty))
    const anyPo = items.some(i => covered(i) > 0)
    return allDone ? 'Sudah' : (anyPo ? 'Sebagian' : 'Belum')
})
const poBadgeType = computed(() => poStatus.value === 'Sudah' ? 'success' : poStatus.value === 'Sebagian' ? 'warning' : 'default')

const goBack = () => window.history.back()
</script>

<template>
    <Head :title="'Detail PO: ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar title="🧾 Detail PO MR" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="head-card">
                <div class="head-left">
                    <h2 class="mr-num">{{ mr.mr_number }}</h2>
                    <p class="info">{{ mr.user?.name }} • Jenis: {{ mr.jenis }} • {{ mr.factory }} • {{ mr.created_at }}</p>
                </div>
                <var-chip :type="poBadgeType" size="small">{{ poStatus === 'Sudah' ? '✅ Sudah PO' : poStatus }}</var-chip>
            </div>

            <div class="note">Mode lihat (read-only) — klik kembali untuk keluar.</div>

            <div v-for="(it, i) in mr.items" :key="it.id" class="item-card">
                <div class="item-head">
                    <div>
                        <span class="item-name">{{ it.item_name }}</span>
                        <span class="item-meta">{{ it.item_code || '-' }} • {{ it.departemen?.nama || '-' }} • diminta {{ it.qty }} {{ it.unit }}</span>
                    </div>
                    <span class="item-covered">Di-PO: {{ covered(it) }} dari {{ it.qty }}</span>
                </div>

                <table class="tbl">
                    <thead><tr><th>Qty</th><th>Nomor PO</th></tr></thead>
                    <tbody>
                        <tr v-for="(l, li) in it.item_po_lines" :key="li">
                            <td>{{ l.qty }}</td>
                            <td class="mono">{{ l.nomor_po || '-' }}</td>
                        </tr>
                        <tr v-if="!it.item_po_lines?.length"><td colspan="2" class="minor">Belum ada baris PO.</td></tr>
                    </tbody>
                </table>
            </div>

            <p v-if="!mr.items?.length" class="minor">Tidak ada item.</p>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px;max-width:840px;margin:0 auto;width:100%; }
.head-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap; }
.mr-num { margin:0;font-family:monospace;font-weight:800;font-size:18px;color:#0f172a; }
.info { font-size:13px;color:#64748b;margin:4px 0 0; }
.note { font-size:12px;color:#64748b;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:10px 14px; }
.item-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px; }
.item-head { display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap; }
.item-name { font-size:15px;font-weight:700;color:#0f172a;display:block; }
.item-meta { font-size:12px;color:#64748b; }
.item-covered { font-size:12px;font-weight:700;color:#059669; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:8px;font-size:11px;text-transform:uppercase; }
.tbl td { padding:8px;border-top:1px solid #f1f5f9; }
.mono { font-family:monospace;font-weight:600; }
.minor { color:#94a3b8;font-style:italic; }
</style>