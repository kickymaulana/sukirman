<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface PoLine {
    id: number
    nomor_po: string | null
    tgl_po: string | null
    expected_date: string | null
    tanggal_disetujui_direksi: string | null
}

const props = defineProps<{
    item: { id: number; item_code: string | null; item_name: string; mr_number: string | null }
    line: PoLine | null
    remaining_qty: number | null
    return_url: string
}>()
const page = usePage()
const baseUrl = (page.props as any).app_url || ''
const isCreate = props.line === null
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const saveError = ref('')
const form = ref({
    qty: isCreate ? String(props.remaining_qty) : '',
    nomor_po: props.line?.nomor_po || '',
    tgl_po: props.line?.tgl_po || '',
    expected_date: props.line?.expected_date || '',
    tanggal_disetujui_direksi: props.line?.tanggal_disetujui_direksi || '',
})
const fields = [
    { key: 'nomor_po', label: 'Nomor PO', type: 'text' },
    { key: 'tgl_po', label: 'Tanggal PO', type: 'date' },
    { key: 'expected_date', label: 'Tanggal Perkiraan Tiba', type: 'date' },
    { key: 'tanggal_disetujui_direksi', label: 'Tanggal Disetujui Direksi', type: 'datetime-local' },
] as const
const goBack = () => {
    if (!saving.value) router.get(props.return_url)
}
const save = async () => {
    if (saving.value) return
    saving.value = true
    errors.value = {}
    saveError.value = ''
    try {
        const endpoint = isCreate
            ? `${baseUrl}/monitoring-items/${props.item.id}/po-lines`
            : `${baseUrl}/monitoring-items/${props.item.id}/po-lines/${props.line!.id}`
        const res = await fetch(endpoint, {
            method: isCreate ? 'POST' : 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': String(page.props.csrf_token || ''),
            },
            body: JSON.stringify(Object.fromEntries(Object.entries(form.value).map(([key, value]) => [key, value.trim() || null]))),
        })
        const data = await res.json()
        if (!res.ok) {
            errors.value = data.errors || {}
            saveError.value = data.message || 'Gagal menyimpan PO.'
            return
        }
        router.get(props.return_url)
    } catch {
        saveError.value = 'Gagal menyimpan PO. Periksa koneksi lalu coba lagi.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Head :title="`${isCreate ? 'Isi' : 'Edit'} PO - SUKIRMAN`" />
    <div class="layout">
        <var-app-bar :title="`${isCreate ? 'Isi' : 'Edit'} PO`" title-position="center">
            <template #left><var-button round text :disabled="saving" aria-label="Kembali ke monitoring item" @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <form class="po-card" :aria-busy="saving" @submit.prevent="save">
                <h1>{{ item.item_name }}</h1>
                <p>{{ item.item_code || 'Tanpa kode' }} · {{ item.mr_number || 'Tanpa MR' }}<template v-if="line"> · Baris PO #{{ line.id }}</template></p>
                <fieldset :disabled="saving">
                    <legend>Data PO</legend>
                    <div v-if="isCreate" class="po-field">
                        <label for="qty">Kuantitas (sisa {{ remaining_qty }})</label>
                        <input id="qty" v-model="form.qty" type="number" min="1" :max="remaining_qty || undefined" required :aria-invalid="!!errors.qty" :aria-describedby="errors.qty ? 'qty-error' : undefined" />
                        <span v-if="errors.qty" id="qty-error" class="error">{{ errors.qty.join(' ') }}</span>
                    </div>
                    <div v-for="field in fields" :key="field.key" class="po-field">
                        <label :for="field.key">{{ field.label }}</label>
                        <input :id="field.key" v-model="form[field.key]" :type="field.type" :maxlength="field.type === 'text' ? 100 : undefined" :aria-invalid="!!errors[field.key]" :aria-describedby="errors[field.key] ? `${field.key}-error` : undefined" />
                        <span v-if="errors[field.key]" :id="`${field.key}-error`" class="error">{{ errors[field.key].join(' ') }}</span>
                    </div>
                </fieldset>
                <p v-if="saveError" role="alert" class="error">{{ saveError }}</p>
                <div class="actions">
                    <button type="button" :disabled="saving" @click="goBack">Batal</button>
                    <button type="submit" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
                </div>
            </form>
        </main>
    </div>
</template>

<style scoped>
.layout { min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { padding:16px 24px 60px; }
.po-card { max-width:600px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px; }
h1 { font-size:20px;color:#0f172a; }
p { font-size:13px;color:#64748b; }
fieldset { border:0;padding:0;min-width:0; }
.po-field { display:flex;flex-direction:column;gap:6px;margin:14px 0; }
input { border:1px solid #94a3b8;border-radius:6px;padding:8px;min-width:0; }
.error { color:#b91c1c;font-size:12px; }
.actions { display:flex;justify-content:flex-end;gap:12px; }
button { padding:8px 16px;border:1px solid #94a3b8;border-radius:6px;cursor:pointer; }
button:disabled { opacity:.5;cursor:wait; }
</style>
