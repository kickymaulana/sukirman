<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

interface GItem {
    id: number; item_code: string; item_name: string; specification: string
    qty: number; unit: string; item_status: string; monthly_usage: number; stock_on_hand: number; purpose: string
}

const props = defineProps<{ mr: any }>()
const page = usePage()
const baseUrl = (page.props as any).app_url || ''

const itemStatusOptions = ['Normal', 'Urgent', 'New', 'Replace']

const items = ref<GItem[]>(props.mr.items.map((i: any) => ({
    id: i.id, item_code: i.item_code || '', item_name: i.item_name, specification: i.specification || '',
    qty: String(i.qty ?? 1), unit: i.unit, item_status: i.item_status || 'Normal', monthly_usage: String(i.monthly_usage ?? 0), stock_on_hand: String(i.stock_on_hand ?? 0), purpose: i.purpose || '',
})))

const form = useForm({ items: items.value as any[] })

const addItem = () => {
    items.value.push({ id: 0, item_code: '', item_name: '', specification: '', qty: '1', unit: '', item_status: 'Normal', monthly_usage: '0', stock_on_hand: '0', purpose: '' })
}

const removeItem = (i: number) => {
    if (items.value.length <= 1) { Snackbar.warning('Minimal 1 barang'); return }
    Dialog({
        title: 'Hapus barang?', message: `Hapus "${items.value[i].item_name || 'barang ini'}"?`,
        confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
        onConfirm: () => items.value.splice(i, 1),
    })
}

const save = () => {
    if (items.value.some(it => !it.item_name.trim() || !it.unit.trim())) { Snackbar.warning('Nama barang & satuan wajib diisi'); return }
    form.items = items.value
    form.post(`${baseUrl}/approval/gudang/${props.mr.id}/update`, {
        onSuccess: () => Snackbar.success('MR berhasil diedit'),
    })
}

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
</script>

<template>
    <Head :title="'Edit MR ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar :title="'Edit: ' + mr.mr_number" title-position="center">
            <template #left><var-button round text @click="router.get(route('approval.gudang'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>

        <main class="content">
            <div class="info-card">
                <span>Dibuat oleh <strong>{{ mr.user?.name }}</strong> • {{ mr.factory }} • {{ mr.type }}</span>
                <span class="hint">Perbaiki typo / bersihkan data item sebelum verifikasi stok.</span>
            </div>

            <div v-for="(item, i) in items" :key="i" class="item-card">
                <div class="item-header">
                    <span class="item-title">Barang #{{ i + 1 }}</span>
                    <var-button v-if="items.length > 1" text round @click="removeItem(i)"><var-icon name="delete" :size="18" color="#ef4444" /></var-button>
                </div>

                <div class="autocomplete-wrap">
                    <var-input v-model="item.item_name" label="Nama Barang *" placeholder="Ketik nama barang (auto-saran)..." :error-message="form.errors[`items.${i}.item_name`]" @update:modelValue="onNamaInput(i, $event)" @blur="hideSuggestions(i)" />
                    <div v-if="showSuggestions[i] && suggestions[i]?.length" class="suggestions-box">
                        <div v-for="(s, si) in suggestions[i]" :key="si" class="suggestion-item" @mousedown.prevent="selectBarang(i, s)">
                            <span class="sug-kode">{{ s.kode_barang }}</span>
                            <span class="sug-nama">{{ s.nama_barang }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid">
                    <var-input v-model="item.item_code" label="Kode Barang" placeholder="Bisa diketik manual" :error-message="form.errors[`items.${i}.item_code`]" />
                    <var-input v-model="item.specification" label="Spesifikasi" placeholder="Merk/type" />
                    <var-input v-model="item.qty" label="Qty *" type="number" :min="1" :error-message="form.errors[`items.${i}.qty`]" />
                    <var-input v-model="item.unit" label="Satuan *" placeholder="pcs, kg, liter" :error-message="form.errors[`items.${i}.unit`]" />
                    <var-select v-model="item.item_status" label="Status Item">
                        <var-option v-for="opt in itemStatusOptions" :key="opt" :label="opt" :value="opt" />
                    </var-select>
                    <var-input v-model="item.stock_on_hand" label="Stock On Hand" type="number" :min="0" />
                </div>

                <var-input v-model="item.purpose" label="Keperluan" placeholder="Alasan kebutuhan" class="full-width" style="margin-top:8px" />
            </div>

            <var-button block text @click="addItem"><var-icon name="plus" /> Tambah Barang</var-button>

            <var-button block type="primary" :loading="form.processing" @click="save" style="margin-top:12px">
                Simpan Perubahan
            </var-button>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.info-card { background:#eef4ff;border:1px solid #c7d7ff;border-radius:12px;padding:14px;font-size:13px;color:#1e40af;display:flex;flex-direction:column;gap:4px; }
.info-card .hint { font-size:12px;color:#64748b; }
.item-card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.item-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;font-weight:600; }
.grid { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.full-width { grid-column:span 2; }
.autocomplete-wrap { position:relative; margin-bottom:8px; }
.suggestions-box { position:absolute;top:100%;left:0;right:0;z-index:100;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.12);margin-top:4px; }
.suggestion-item { display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9; }
.suggestion-item:last-child { border-bottom:none; }
.suggestion-item:hover, .suggestion-item:active { background:#f0f5ff; }
.sug-kode { flex-shrink:0;font-size:12px;font-weight:700;color:#1d6bf0;background:#eef4ff;padding:2px 8px;border-radius:6px;max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.sug-nama { font-size:13px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
</style>
