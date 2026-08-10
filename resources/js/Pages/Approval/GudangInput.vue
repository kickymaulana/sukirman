<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any }>()
const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr

const copyText = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text)
        Snackbar.success('Tersalin: ' + text)
    } catch {
        Snackbar.error('Gagal menyalin')
    }
}

const toggling = ref<number | null>(null)
const toggleItem = async (it: any) => {
    const next = it.input_accurate === 'Sudah' ? 'Belum' : 'Sudah'
    toggling.value = it.id
    try {
        const res = await fetch(`${baseUrl}/gudang/item/${it.id}/accurate`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ value: next }),
        })
        toggling.value = null
        if (res.ok) { Snackbar.success(`Item ${next}`); window.location.reload() }
        else { Snackbar.error('Gagal') }
    } catch { toggling.value = null; Snackbar.error('Gagal') }
}

const markingMr = ref(false)
const markMr = async (value: 'Belum' | 'Sudah') => {
    markingMr.value = true
    try {
        const res = await fetch(`${baseUrl}/gudang/mr/${mr.id}/accurate`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ value }),
        })
        markingMr.value = false
        if (res.ok) { Snackbar.success(value === 'Sudah' ? 'MR ditandai Sudah diinput' : 'MR ditandai belum'); window.location.reload() }
        else { Snackbar.error('Gagal') }
    } catch { markingMr.value = false; Snackbar.error('Gagal') }
}

const goBack = () => window.location.href = baseUrl + '/approval/gudang'
</script>

<template>
    <Head :title="'Input Accurate: ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar title="🗂️ Input ke Permintaan Barang (Accurate)" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="head-card">
                <div class="head-left">
                    <h2 class="mr-num">{{ mr.mr_number }}</h2>
                    <p class="info">{{ mr.user?.name }} • Jenis: {{ mr.jenis }} • {{ mr.factory }} • {{ mr.created_at }}</p>
                </div>
                <var-chip :type="mr.input_accurate === 'Sudah' ? 'success' : 'default'" size="small">
                    {{ mr.input_accurate === 'Sudah' ? '✅ MR Sudah diinput' : 'MR Belum diinput' }}
                </var-chip>
            </div>

            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Departemen</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Salin</th>
                            <th>Sudah Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(it, i) in mr.items" :key="it.id">
                            <td>{{ i + 1 }}</td>
                            <td class="mono">{{ it.item_code || '-' }}</td>
                            <td>
                                <img v-if="it.foto" :src="`${baseUrl}/item-foto/${it.id}`" class="iimg" alt="foto" />
                                {{ it.item_name }}
                            </td>
                            <td>{{ it.departemen?.nama || '-' }}</td>
                            <td>{{ it.qty }}</td>
                            <td>{{ it.unit }}</td>
                            <td>
                                <div class="copy-group">
                                    <button class="btn-copy" @click="copyText(it.item_code || '')">Salin Kode</button>
                                    <button class="btn-copy" @click="copyText(it.item_name || '')">Salin Nama</button>
                                </div>
                            </td>
                            <td>
                                <button
                                    class="btn-toggle"
                                    :class="it.input_accurate === 'Sudah' ? 'on' : ''"
                                    :disabled="toggling === it.id"
                                    @click="toggleItem(it)"
                                >
                                    {{ it.input_accurate === 'Sudah' ? '✅ Sudah' : 'Belum' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!mr.items?.length">
                            <td colspan="8" class="empty">Tidak ada item.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="done-bar">
                <button class="btn-done" :disabled="markingMr" @click="markMr('Sudah')">
                    ✅ Tandai MR Sudah diinput ke Permintaan Barang
                </button>
                <button v-if="mr.input_accurate === 'Sudah'" class="btn-undo" :disabled="markingMr" @click="markMr('Belum')">
                    ↩ Batalkan Tanda
                </button>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px; }
.head-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap; }
.mr-num { margin:0;font-family:monospace;font-weight:800;font-size:18px;color:#0f172a; }
.muted, .muted-ok { font-size:13px;color:#64748b;margin:4px 0 0; }
.p-info { display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f1f5f9;margin-top:12px;padding-top:12px; }
.field-lbl { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px; }
.table-wrap { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:12px;font-size:12px;text-transform:uppercase; }
.tbl td { padding:10px 12px;border-top:1px solid #f1f5f9;color:#1e293b; }
.tbl tr:hover td { background:#f8fafc; }
.mono { font-family:monospace;font-weight:700; }
.iimg { display:block;width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;margin:0 auto 4px; }
.empty { text-align:center;color:#94a3b8;padding:40px; }
.btn-copy { background:#e0e7ff;color:#4338ca;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer; }
.btn-copy:hover { background:#c7d2fe; }
.copy-group { display:flex;gap:6px; }
.btn-toggle { background:#fff;color:#dc2626;border:1px solid #fecaca;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer; }
.btn-toggle.on { background:#10b981;color:#fff;border-color:#10b981; }
.btn-toggle:disabled { opacity:0.5; }
.done-bar { display:flex;gap:10px;flex-wrap:wrap; }
.btn-done { background:#10b981;color:#fff;border:none;padding:14px 20px;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer; }
.btn-done:disabled { opacity:0.5; }
.btn-undo { background:#fff;color:#64748b;border:1px solid #cbd5e1;padding:14px 18px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer; }
</style>