<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any }>()
const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr

const items = ref((mr.items || []).map((it: any) => ({
    id: it.id,
    nomor_po: it.nomor_po || '',
    nama: it.item_name,
    kode: it.item_code || '',
    qty: it.qty,
    unit: it.unit,
    departemen: it.departemen?.nama || '',
})))

const bulkPo = ref('')

const copyText = async (text: string) => {
    try { await navigator.clipboard.writeText(text); Snackbar.success('Tersalin') }
    catch { Snackbar.error('Gagal menyalin') }
}

// Status PO dihitung otomatis dari item
const poStatus = computed(() => {
    const total = items.value.length
    const withPo = items.value.filter(i => i.nomor_po.trim()).length
    if (total === 0) return 'Belum'
    if (withPo === total) return 'Sudah'
    return withPo > 0 ? 'Sebagian' : 'Belum'
})

const poBadgeType = computed(() => {
    if (poStatus.value === 'Sudah') return 'success'
    if (poStatus.value === 'Sebagian') return 'warning'
    return 'default'
})

// Terapkan satu nomor PO ke semua item
const applyBulk = () => {
    if (!bulkPo.value.trim()) { Snackbar.warning('Isi nomor PO terlebih dahulu'); return }
    items.value.forEach(i => { i.nomor_po = bulkPo.value.trim() })
    bulkPo.value = ''
    Snackbar.success('Nomor PO diterapkan ke semua item')
}

const saving = ref(false)
const save = async () => {
    saving.value = true
    try {
        const res = await fetch(`${baseUrl}/approval/purchasing/${mr.id}/po`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ items: items.value.map(i => ({ id: i.id, nomor_po: i.nomor_po.trim() })) }),
        })
        saving.value = false
        if (res.ok) { Snackbar.success('Nomor PO disimpan'); window.location.reload() }
        else { Snackbar.error('Gagal') }
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
                <var-chip :type="poBadge" size="small">{{ poStatus === 'Sudah' ? '✅ Sudah PO' : poStatus }}</var-chip>
            </div>

            <!-- Terapkan satu PO ke semua item (kasus 1 MR = 1 PO) -->
            <div class="bulk-card">
                <label class="field-lbl">Set semua item ke satu Nomor PO</label>
                <div class="bulk-row">
                    <input v-model="bulkPo" type="text" placeholder="Isi satu nomor PO untuk semua item..." class="bulk-input" />
                    <button class="btn-bulk" @click="applyBulk">Terapkan ke Semua Item</button>
                </div>
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
                            <th>Nomor PO</th>
                            <th>Salin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(it, i) in items" :key="it.id">
                            <td>{{ i + 1 }}</td>
                            <td class="mono">{{ it.kode || '-' }}</td>
                            <td>{{ it.nama }}</td>
                            <td>{{ it.departemen || '-' }}</td>
                            <td>{{ it.qty }}</td>
                            <td>{{ it.unit }}</td>
                            <td>
                                <input v-model="it.nomor_po" type="text" placeholder="Nomor PO..." class="po-input" />
                            </td>
                            <td>
                                <div class="copy-group">
                                    <button class="btn-copy" @click="copyText(it.kode)">Kode</button>
                                    <button class="btn-copy" @click="copyText(it.nama)">Nama</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.length"><td colspan="8" class="empty">Tidak ada item.</td></tr>
                    </tbody>
                </table>
            </div>

            <button class="btn-save" :disabled="saving" @click="save">💾 Simpan Nomor PO Semua Item</button>
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
.table-wrap { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:12px;font-size:12px;text-transform:uppercase; }
.tbl td { padding:10px 12px;border-top:1px solid #f1f5f9;color:#1e293b; }
.tbl tr:hover td { background:#f8fafc; }
.mono { font-family:monospace;font-weight:700; }
.po-input { width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:6px 8px;font-size:13px;outline:none; }
.po-input:focus { border-color:#4f46e5; }
.copy-group { display:flex;gap:6px; }
.btn-copy { background:#e0e7ff;color:#4338ca;border:none;padding:5px 10px;border-radius:6px;font-weight:700;font-size:12px;cursor:pointer; }
.empty { text-align:center;color:#94a3b8;padding:40px; }
.btn-save { background:#10b981;color:#fff;border:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer; }
.btn-save:disabled { opacity:0.5; }
</style>