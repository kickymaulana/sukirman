<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

interface Item { id: number; item_name: string; specification: string|null; qty: number; unit: string; purpose: string|null }

const props = defineProps<{ mr: { id: number; mr_number: string; revision_notes: string|null; items: Item[] } }>()
const page = usePage()
const baseUrl = (page.props as any).app_url || ''
const csrf = (page.props as any).csrf_token || ''

const items = ref(props.mr.items.map(i => ({
    item_name: i.item_name, specification: i.specification || '', qty: i.qty, unit: i.unit, purpose: i.purpose || ''
})))

const addItem = () => { items.value.push({ item_name: '', specification: '', qty: 1, unit: '', purpose: '' }) }
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
    if (items.value.some(i => !i.item_name || !i.unit)) { Snackbar.warning('Nama barang & satuan wajib diisi'); return }
    const res = await fetch(`${baseUrl}/material-requests/${props.mr.id}/resubmit`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ items: items.value }),
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

            <div v-for="(item, i) in items" :key="i" class="item-card">
                <div class="item-header">Barang #{{ i + 1 }} <var-button text round @click="removeItem(i)"><var-icon name="delete" :size="18" color="#ef4444" /></var-button></div>
                <div class="grid">
                    <var-input v-model="item.item_name" label="Nama Barang *" placeholder="Nama barang" />
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
.item-card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.item-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;font-weight:600; }
.grid { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.full-width { grid-column:span 2; }
</style>
