<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any }>()
const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr

const inputPo = ref(mr.input_po === 'Sudah' ? 'Sudah' : 'Belum')
const nomorPo = ref(mr.nomor_po || '')

const formatFull = (v: string | null) => {
    if (!v) return '-'
    return new Date(v).toLocaleString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const copyText = async (text: string) => {
    try { await navigator.clipboard.writeText(text); Snackbar.success('Tersalin') }
    catch { Snackbar.error('Gagal menyalin') }
}

const saving = ref(false)
const save = async () => {
    if (inputPo.value === 'Sudah' && !nomorPo.value.trim()) {
        Snackbar.warning('Isi Nomor PO terlebih dahulu')
        return
    }
    saving.value = true
    try {
        const res = await fetch(`${baseUrl}/approval/purchasing/${mr.id}/po`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ input_po: inputPo.value, nomor_po: nomorPo.value.trim() }),
        })
        saving.value = false
        if (res.ok) { Snackbar.success('Data PO disimpan'); window.location.reload() }
        else { Snackbar.error('Gagal') }
    } catch { saving.value = false; Snackbar.error('Gagal') }
}

const goBack = () => window.location.href = baseUrl + '/approval/purchasing'

const isDone = computed(() => inputPo.value === 'Sudah')
</script>

<template>
    <Head :title="'Input PO: ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar title="🧾 Input MR menjadi PO" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="head-card">
                <div class="head-left">
                    <h2 class="mr-num">{{ mr.mr_number }}</h2>
                    <p class="info">{{ mr.user?.name }} • Jenis: {{ mr.jenis }} • {{ mr.factory }} • {{ mr.created_at }}</p>
                </div>
                <var-chip :type="isDone ? 'success' : 'default'" size="small">
                    {{ isDone ? '✅ Sudah jadi PO' : 'Belum jadi PO' }}
                </var-chip>
            </div>

            <!-- Tanggal PO otomatis -->
            <div v-if="mr.input_po === 'Sudah'" class="po-date-card">
                <span class="field-lbl">Tanggal & Jam PO (otomatis)</span>
                <span class="po-date-val">{{ formatFull(mr.tanggal_po) }}</span>
            </div>

            <!-- Input Nomor PO -->
            <div class="po-card">
                <div class="po-form">
                    <label class="field-lbl">Nomor PO (salin dari Accurate)</label>
                    <div class="po-row">
                        <input v-model="nomorPo" type="text" placeholder="Contoh: PO-2026-000123" class="po-input" :disabled="saving" />
                        <button class="btn-copy" @click="copyText(nomorPo)">⧉ Salin</button>
                    </div>
                </div>
                <div class="po-actions">
                    <button v-if="!isDone" class="btn-done" :disabled="saving" @click="inputPo='Sudah'; save()">
                        ✅ Tandai MR Sudah Jadi PO
                    </button>
                    <template v-else>
                        <button class="btn-done" :disabled="saving" @click="save()">💾 Simpan Nomor PO</button>
                        <button class="btn-undo" :disabled="saving" @click="inputPo='Belum'; nomorPo=''; save()">↩ Batalkan PO</button>
                    </template>
                </div>
            </div>

            <!-- Daftar item -->
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(it, i) in mr.items" :key="it.id">
                            <td>{{ i + 1 }}</td>
                            <td class="mono">{{ it.item_code || '-' }}</td>
                            <td>{{ it.item_name }}</td>
                            <td>{{ it.departemen?.nama || '-' }}</td>
                            <td>{{ it.qty }}</td>
                            <td>{{ it.unit }}</td>
                            <td><button class="btn-copy" @click="copyText(`${it.item_code || ''} | ${it.item_name || ''}`.trim())">⧉ Salin</button></td>
                        </tr>
                        <tr v-if="!mr.items?.length"><td colspan="7" class="empty">Tidak ada item.</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px; }
.head-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap; }
.mr-num { margin:0;font-family:monospace;font-weight:800;font-size:18px;color:#0f172a; }
.info { font-size:13px;color:#64748b;margin:4px 0 0; }
.po-date-card { background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px; }
.po-date-val { font-size:16px;font-weight:800;color:#047857; }
.po-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px; }
.po-form { display:flex;flex-direction:column;gap:6px; }
.field-lbl { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase; }
.po-row { display:flex;gap:8px;align-items:center; }
.po-input { flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:14px;outline:none; }
.po-input:focus { border-color:#4f46e5; }
.po-actions { display:flex;gap:10px;flex-wrap:wrap; }
.btn-done { background:#10b981;color:#fff;border:none;padding:12px 18px;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer; }
.btn-done:disabled { opacity:0.5; }
.btn-undo { background:#fff;color:#64748b;border:1px solid #cbd5e1;padding:12px 16px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer; }
.btn-copy { background:#e0e7ff;color:#4338ca;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer; }
.table-wrap { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:12px;font-size:12px;text-transform:uppercase; }
.tbl td { padding:10px 12px;border-top:1px solid #f1f5f9;color:#1e293b; }
.tbl tr:hover td { background:#f8fafc; }
.mono { font-family:monospace;font-weight:700; }
.empty { text-align:center;color:#94a3b8;padding:40px; }
</style>