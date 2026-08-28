<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

interface Item {
    id: number; item_code: string; item_name: string; specification: string|null; qty: number; unit: string; purpose: string|null
    direksi_decision: string|null; direksi_notes: string|null
}

const props = defineProps<{ mr: { id: number; mr_number: string; revision_notes: string|null; items: Item[] } }>()
const page = usePage()
const baseUrl = (page.props as any).app_url || ''
const csrf = (page.props as any).csrf_token || ''

const items = ref(props.mr.items.map(i => ({
    id: i.id, item_code: i.item_code || '', item_name: i.item_name, specification: i.specification || '', qty: i.qty, unit: i.unit, purpose: i.purpose || '',
    direksi_decision: i.direksi_decision, direksi_notes: i.direksi_notes,
})))

// Autocomplete barang
const suggestions = ref<Record<number, { kode_barang: string; nama_barang: string }[]>>({})
const showSuggestions = ref<Record<number, boolean>>({})
let searchTimer: any = null

const onNamaInput = (index: number, query: string) => {
    if (query.trim().length < 2) {
        suggestions.value[index] = []
        showSuggestions.value[index] = false
        return
    }
    clearTimeout(searchTimer)
    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`${baseUrl}/barangs/search-api?q=${encodeURIComponent(query)}`)
            const data = await res.json()
            suggestions.value[index] = data
            showSuggestions.value[index] = data.length > 0
        } catch { suggestions.value[index] = [] }
    }, 300)
}

const selectBarang = (index: number, b: { kode_barang: string; nama_barang: string }) => {
    items.value[index].item_code = b.kode_barang
    items.value[index].item_name = b.nama_barang
    suggestions.value[index] = []
    showSuggestions.value[index] = false
}

const hideSuggestions = (index: number) => {
    nextTick(() => { setTimeout(() => { showSuggestions.value[index] = false }, 150) })
}

const decisionLabel = (d: string | null) => d === 'setuju' ? '✅ Disetujui' : d === 'tolak' ? '❌ Ditolak' : d === 'ganti' ? '🔄 Diganti' : ''

const decisionColor = (d: string | null) => d === 'setuju' ? 'success' : d === 'tolak' ? 'danger' : d === 'ganti' ? 'warning' : 'default'

// Item yang ditolak → tidak dikirim, otomatis dihapus di backend
const submitItems = () => items.value.filter(i => i.direksi_decision !== 'tolak')

const addItem = () => {
    items.value.push({ id: 0, item_code: '', item_name: '', specification: '', qty: 1, unit: '', purpose: '', direksi_decision: null, direksi_notes: null })
}

const removeItem = (i: number) => {
    if (items.value.length <= 1) { Snackbar.warning('Minimal 1 barang'); return }
    Dialog({
        title: 'Hapus barang?', message: `Hapus "${items.value[i].item_name || 'barang ini'}"?`,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        onConfirm: () => items.value.splice(i, 1),
    })
}

const submit = async () => {
    const toSubmit = submitItems()
    if (toSubmit.some(i => !i.item_name || !i.unit)) { Snackbar.warning('Nama barang & satuan wajib diisi'); return }
    const res = await fetch(`${baseUrl}/material-requests/${props.mr.id}/resubmit`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ items: toSubmit }),
    })
    if (res.ok || res.redirected) { Snackbar.success('MR dikirim ulang'); window.location.href = baseUrl + '/material-requests' }
    else { Snackbar.error('Gagal') }
}
</script>

<template>
    <Head :title="'Edit ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar :title="'Edit: ' + mr.mr_number" title-position="center">
            <template #left><var-button round text @click="router.get(route('material-requests.index'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div v-if="mr.revision_notes" class="revision-box">
                <strong>Catatan Revisi:</strong> {{ mr.revision_notes }}
            </div>

            <div class="guide-box">
                Item yang <strong>ditolak Direksi</strong> akan otomatis dihapus saat kirim ulang.
                Edit item yang disetujui/diganti, atau klik <strong>Tambah Barang</strong> sebagai pengganti, lalu klik <strong>Kirim Ulang MR</strong>.
            </div>

            <div v-for="(item, i) in items" :key="i" class="item-card" :class="{ 'rejected-item': item.direksi_decision === 'tolak' }">
                <div class="item-header">
                    <span class="item-title">Barang #{{ i + 1 }}</span>
                    <div class="item-header-right">
                        <var-chip v-if="item.direksi_decision" size="small" :type="decisionColor(item.direksi_decision)">{{ decisionLabel(item.direksi_decision) }}</var-chip>
                        <var-button v-if="item.direksi_decision !== 'tolak'" text round @click="removeItem(i)"><var-icon name="delete" :size="18" color="#ef4444" /></var-button>
                    </div>
                </div>

                <div v-if="item.direksi_notes" class="direksi-note">📝 {{ item.direksi_notes }}</div>

                <div v-if="item.direksi_decision === 'tolak'" class="rejected-note">Item ini ditolak Direksi dan akan dihapus. Klik "Tambah Barang" untuk menambah item pengganti jika perlu.</div>

                <div v-if="item.direksi_decision === 'tolak'" class="rejected-detail">
                    <div class="detail-row"><span class="detail-label">Nama Barang</span><span class="detail-value">{{ item.item_name || '-' }}</span></div>
                    <div class="detail-row"><span class="detail-label">Kode Barang</span><span class="detail-value">{{ item.item_code || '-' }}</span></div>
                    <div class="detail-row"><span class="detail-label">Spesifikasi</span><span class="detail-value">{{ item.specification || '-' }}</span></div>
                    <div class="detail-row"><span class="detail-label">Qty</span><span class="detail-value">{{ item.qty }} {{ item.unit }}</span></div>
                    <div class="detail-row"><span class="detail-label">Keperluan</span><span class="detail-value">{{ item.purpose || '-' }}</span></div>
                </div>

                <div v-else class="grid">
                    <div class="autocomplete-wrap full-width">
                        <var-input v-model="item.item_name" label="Nama Barang *" placeholder="Ketik nama barang (auto-saran)..." @update:modelValue="onNamaInput(i, $event)" @blur="hideSuggestions(i)" />
                        <div v-if="showSuggestions[i] && suggestions[i]?.length" class="suggestions-box">
                            <div v-for="(s, si) in suggestions[i]" :key="si" class="suggestion-item" @mousedown.prevent="selectBarang(i, s)">
                                <span class="sug-kode">{{ s.kode_barang }}</span>
                                <span class="sug-nama">{{ s.nama_barang }}</span>
                            </div>
                        </div>
                    </div>
                    <var-input v-model="item.item_code" label="Kode Barang" placeholder="Bisa diketik manual" />
                    <var-input v-model="item.specification" label="Spesifikasi" placeholder="Merk/type" />
                    <var-input v-model="item.qty" label="Qty *" type="number" :min="1" />
                    <var-input v-model="item.unit" label="Satuan *" placeholder="pcs, kg, liter" />
                    <var-input v-model="item.purpose" label="Keperluan" placeholder="Alasan kebutuhan" class="full-width" />
                </div>
            </div>

            <var-button block text @click="addItem"><var-icon name="plus" /> Tambah Barang</var-button>
            <var-button block type="primary" @click="submit" style="margin-top:12px">Kirim Ulang MR</var-button>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.revision-box { background:#fef3c7;border-radius:12px;padding:14px;font-size:13px;color:#92400e; }
.guide-box { background:#eef2ff;border-radius:12px;padding:14px;font-size:13px;color:#4f46e5;line-height:1.5; }
.item-card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.item-card.rejected-item { opacity:0.6;background:#fef2f2;border-color:#fecaca; }
.item-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;font-weight:600; }
.item-header-right { display:flex;align-items:center;gap:8px; }
.item-title { font-weight:600; }
.direksi-note { background:#eef2ff;border-radius:8px;padding:8px 12px;font-size:12px;color:#4f46e5;margin-bottom:8px; }
.rejected-note { font-size:12px;color:#dc2626;padding:8px; }
.rejected-detail { margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0; }
.grid { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.full-width { grid-column:span 2; }
.autocomplete-wrap { position:relative; }
.suggestions-box { position:absolute;top:100%;left:0;right:0;z-index:100;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.12);margin-top:4px; }
.suggestion-item { display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9; }
.suggestion-item:last-child { border-bottom:none; }
.suggestion-item:hover, .suggestion-item:active { background:#f0f5ff; }
.sug-kode { flex-shrink:0;font-size:12px;font-weight:700;color:#1d6bf0;background:#eef4ff;padding:2px 8px;border-radius:6px;max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.sug-nama { font-size:13px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.detail-row { display:flex;gap:10px;padding:3px 0;font-size:12px; }
.detail-label { width:100px;flex-shrink:0;color:#64748b;font-weight:600; }
.detail-value { color:#1e293b; }
</style>
