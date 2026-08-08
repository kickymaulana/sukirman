<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any }>()
const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr

interface Line { qty: string; nomor_po: string; tgl_po: string; expected_date: string; tgl_setuju: string }
interface Row {
    id: number; kode: string; nama: string; qty: number; unit: string; dept: string
    lines: Line[]
}

const items = ref<Row[]>((mr.items || []).map((it: any) => ({
    id: it.id,
    kode: it.item_code || '',
    nama: it.item_name,
    qty: it.qty,
    unit: it.unit,
    dept: it.departemen?.nama || '',
    lines: (it.item_po_lines || []).map((l: any) => ({
        qty: String(l.qty),
        nomor_po: l.nomor_po || '',
        tgl_po: l.tgl_po || '',
        expected_date: l.expected_date || '',
        tgl_setuju: l.tanggal_disetujui_direksi ? String(l.tanggal_disetujui_direksi).slice(0, 16) : '',
    })),
})))

const bulkPo = ref('')

const copyText = async (text: string) => {
    try { await navigator.clipboard.writeText(text); Snackbar.success('Tersalin') }
    catch { Snackbar.error('Gagal menyalin') }
}

const covered = (r: Row) => r.lines.reduce((s, l) => s + (Number(l.qty) || 0), 0)
const remaining = (r: Row) => r.qty - covered(r)

// Tambah baris PO (isi qty sesuai sisa otomatis, biar tidak salah)
const addLine = (r: Row) => {
    r.lines.push({ qty: String(r.qty - covered(r)), nomor_po: bulkPo.value.trim(), tgl_po: '', expected_date: '', tgl_setuju: '' })
}
const removeLine = (r: Row, i: number) => r.lines.splice(i, 1)

// Buat semua item jadi satu PO: otomatis buat baris qty penuh + nomor PO yang sama
const applyBulk = () => {
    if (!bulkPo.value.trim()) { Snackbar.warning('Isi nomor PO terlebih dahulu'); return }
    let added = 0
    items.value.forEach(r => {
        const sisa = r.qty - covered(r)
        if (sisa > 0) {
            r.lines.push({ qty: String(sisa), nomor_po: bulkPo.value.trim(), tgl_po: '', expected_date: '', tgl_setuju: '' })
            added++
        }
    })
    if (added === 0) { Snackbar.info('Semua item sudah terisi penuh') }
    else { Snackbar.success(`Dibuat ${added} baris PO`); bulkPo.value = '' }
}

// Status seluruh item
const anyOver = computed(() => items.value.some(r => remaining(r) < 0))
const poStatus = computed(() => {
    if (!items.value.length) return 'Belum'
    const allDone = items.value.every(r => covered(r) >= r.qty)
    const anyPo = items.value.some(r => covered(r) > 0)
    return allDone ? 'Sudah' : (anyPo ? 'Sebagian' : 'Belum')
})
const poBadgeType = computed(() => poStatus.value === 'Sudah' ? 'success' : poStatus.value === 'Sebagian' ? 'warning' : 'default')

const saving = ref(false)
const save = async () => {
    if (anyOver.value) { Snackbar.error('Ada item yang total qty PO melebihi qty permintaan'); return }
    const payload = items.value.map(r => ({
        id: r.id,
        lines: r.lines.filter(l => (Number(l.qty) || 0) > 0).map(l => ({
            qty: Number(l.qty),
            nomor_po: l.nomor_po.trim(),
            tgl_po: l.tgl_po || null,
            expected_date: l.expected_date || null,
            tanggal_disetujui_direksi: l.tgl_setuju || null,
        })),
    }))
    saving.value = true
    try {
        const res = await fetch(`${baseUrl}/approval/purchasing/${mr.id}/po`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ items: payload }),
        })
        const data = await res.json()
        saving.value = false
        if (res.ok) { Snackbar.success('Baris PO disimpan'); window.location.reload() }
        else { Snackbar.error(data.error || 'Gagal') }
    } catch { saving.value = false; Snackbar.error('Gagal') }
}

const goBack = () => window.location.href = baseUrl + '/approval/purchasing'
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
                <var-chip :type="poBadgeType" size="small">{{ poStatus === 'Sudah' ? '✅ Sudah PO' : poStatus }}</var-chip>
            </div>

            <!-- Buat semua item jadi satu PO (kasus 1 MR = 1 pemasok) -->
            <div class="bulk-card">
                <label class="field-label">Buat semua item jadi satu Nomor PO</label>
                <div class="bulk-row">
                    <input v-model="bulkPo" type="text" placeholder="Isi nomor PO untuk semua item..." class="bulk-input" />
                    <button class="btn-bulk" @click="applyBulk">Buat Semua Jadi Satu PO</button>
                </div>
            </div>

            <!-- Item & baris PO -->
            <div v-for="(r, ri) in items" :key="r.id" class="item-card">
                <div class="item-head">
                    <div>
                        <span class="item-name">{{ r.nama }}</span>
                        <span class="item-meta">{{ r.kode || '-' }} • {{ r.dept || '-' }} • diminta {{ r.qty }} {{ r.unit }}</span>
                    </div>
                    <div class="item-copy">
                        <button class="btn-copy" @click="copyText(r.kode)">Kode</button>
                        <button class="btn-copy" @click="copyText(r.nama)">Nama</button>
                    </div>
                </div>

                <div class="line-head">
                    <span>Baris PO</span>
                    <span class="remaining" :class="{ over: remaining(r) < 0 }">
                        {{ remaining(r) < 0 ? '⚠️ Melebihi!' : 'Sisa: ' + remaining(r) }}
                    </span>
                    <button class="btn-line" @click="addLine(r)">＋ Tambah Baris</button>
                </div>

                <table class="tbl">
                    <thead>
                        <tr><th>Qty</th><th>Nomor PO</th><th>Tgl PO</th><th>Expected</th><th>Tgl Disetujui Direksi</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(l, li) in r.lines" :key="li">
                            <td><input v-model="l.qty" type="number" min="0" class="qty-input" /></td>
                            <td><input v-model="l.nomor_po" type="text" placeholder="Nomor PO..." class="po-input" /></td>
                            <td><input v-model="l.tgl_po" type="date" class="po-input" /></td>
                            <td><input v-model="l.expected_date" type="date" class="po-input" /></td>
                            <td><input v-model="l.tgl_setuju" type="datetime-local" class="po-input" /></td>
                            <td><button class="btn-x" @click="removeLine(r, li)">✕</button></td>
                        </tr>
                        <tr v-if="!r.lines.length"><td colspan="6" class="minor">Belum ada baris PO. Klik "＋ Tambah Baris".</td></tr>
                    </tbody>
                </table>
            </div>

            <button class="btn-save" :disabled="saving" @click="save">💾 Simpan Baris PO Semua Item</button>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px; }
.head-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap; }
.mr-num { margin:0;font-family:monospace;font-weight:800;font-size:18px;color:#0f172a; }
.info { font-size:13px;color:#64748b;margin:4px 0 0; }
.bulk-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:8px; }
.field-label { font-size:12px;font-weight:700;color:#334155;text-transform:uppercase; }
.bulk-row { display:flex;gap:8px;align-items:center; }
.bulk-input { flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:14px;outline:none; }
.bulk-input:focus { border-color:#4f46e5; }
.btn-bulk { background:#4f46e5;color:#fff;border:none;padding:10px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap; }
.item-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px; }
.item-head { display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap; }
.item-name { font-size:15px;font-weight:700;color:#0f172a;display:block; }
.item-meta { font-size:12px;color:#64748b; }
.copy { display:flex;gap:6px; }
.btn-copy { background:#e0e7ff;color:#4338ca;border:none;padding:5px 10px;border-radius:6px;font-weight:700;font-size:12px;cursor:pointer; }
.line-head { display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:13px;font-weight:700;color:#334155; }
.remaining { margin-left:auto;color:#059669;font-size:12px;font-weight:600; }
.remaining.over { color:#dc2626; }
.btn-line { background:#10b981;color:#fff;border:none;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:8px;font-size:11px;text-transform:uppercase; }
.tbl td { padding:6px 8px;border-top:1px solid #f1f5f9; }
.qty-input { width:70px;border:1px solid #e2e8f0;border-radius:6px;padding:6px 8px;font-size:13px;outline:none; }
.qty-input:focus { border-color:#4f46e5; }
.po-input { width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:6px 8px;font-size:13px;outline:none; }
.po-input:focus { border-color:#4f46e5; }
.btn-x { background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;padding:4px 8px;font-weight:700;cursor:pointer; }
.minor { color:#94a3b8;font-style:italic; }
.btn-save { background:#10b981;color:#fff;border:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer; }
.btn-save:disabled { opacity:0.5; }
</style>