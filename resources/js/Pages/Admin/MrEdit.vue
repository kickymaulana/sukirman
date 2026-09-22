<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

interface Departemen { id: number; nama: string }
interface RequestItem {
    id: number | null
    type: string
    item_code: string
    item_name: string
    specification: string
    departemen_id: string
    qty: number
    unit: string
    item_status: string
    monthly_usage: number
    stock_on_hand: number
    purpose: string
    foto: File | null
    remove_foto: boolean
    preview: string | null
}

const props = defineProps<{
    mr: any
    departemens: Departemen[]
    direksis: { id: number; name: string; nik: string }[]
    hasPo: boolean
}>()

const baseUrl = (usePage().props as any).app_url || ''
const showDelete = ref(false)
const direksiId = ref(props.mr.direksi_id ? String(props.mr.direksi_id) : '')
const changingDireksi = ref(false)
const canChangeDireksi = computed(() => props.mr.status_workflow === 'Pending Direksi')

const form = useForm({
    type: props.mr.type,
    factory: props.mr.factory,
    allocation: props.mr.allocation,
    status_pembelian: props.mr.status_pembelian,
    items: props.mr.items.map((item: any) => ({
        id: item.id,
        type: item.type || '',
        item_code: item.item_code || '',
        item_name: item.item_name || '',
        specification: item.specification || '',
        departemen_id: item.departemen_id ? String(item.departemen_id) : '',
        qty: item.qty,
        unit: item.unit || '',
        item_status: item.item_status || 'Normal',
        monthly_usage: item.monthly_usage || 0,
        stock_on_hand: item.stock_on_hand || 0,
        purpose: item.purpose || '',
        foto: null,
        remove_foto: false,
        preview: item.foto ? `${baseUrl}/item-foto/${item.id}` : null,
    })) as RequestItem[],
})

const typeOptions = ['Lokal', 'Import']
const factoryOptions = ['KIM', 'DALU 1', 'DALU 2']
const allocationOptions = ['Project', 'Proses']
const statusPembelianOptions = ['Normal', 'Urgent']
const itemStatusOptions = ['Normal', 'Urgent', 'New', 'Replace']
const canSubmit = computed(() => !props.hasPo && form.items.length > 0 && form.items.every(item => item.type && item.item_name && item.qty > 0 && item.unit))

const addItem = () => {
    form.items.push({
        id: null,
        type: props.mr.type || 'Lokal',
        item_code: '',
        item_name: '',
        specification: '',
        departemen_id: '',
        qty: 1,
        unit: 'PCS',
        item_status: 'Normal',
        monthly_usage: 0,
        stock_on_hand: 0,
        purpose: '',
        foto: null,
        remove_foto: false,
        preview: null,
    })
}

const removeItem = (index: number) => {
    if (form.items.length === 1) {
        Snackbar.warning('MR harus memiliki minimal satu item')
        return
    }
    form.items.splice(index, 1)
}

const handleFoto = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    if (!file.type.startsWith('image/')) {
        Snackbar.warning('File harus berupa gambar')
        input.value = ''
        return
    }
    form.items[index].foto = file
    form.items[index].remove_foto = false
    form.items[index].preview = URL.createObjectURL(file)
}

const removeFoto = (index: number) => {
    form.items[index].foto = null
    form.items[index].remove_foto = true
    form.items[index].preview = null
}

const save = () => {
    if (!canSubmit.value) return
    form.post(`${baseUrl}/admin/overview/${props.mr.id}/update`, {
        forceFormData: true,
        onSuccess: () => Snackbar.success('MR berhasil dikoreksi tanpa mengubah status'),
        onError: errors => Snackbar.error(String(Object.values(errors)[0] || 'Gagal menyimpan koreksi')),
    })
}

const changeDireksi = () => {
    if (!direksiId.value || direksiId.value === String(props.mr.direksi_id)) return
    changingDireksi.value = true
    router.post(`${baseUrl}/admin/overview/${props.mr.id}/target`, { user_id: direksiId.value }, {
        preserveScroll: true,
        onSuccess: () => Snackbar.success('Tujuan Direksi berhasil diubah'),
        onError: errors => Snackbar.error(String(Object.values(errors)[0] || 'Gagal mengubah tujuan Direksi')),
        onFinish: () => { changingDireksi.value = false },
    })
}

const confirmDelete = () => {
    showDelete.value = false
    router.delete(`${baseUrl}/admin/overview/${props.mr.id}`, {
        onSuccess: () => router.get(`${baseUrl}/admin/overview`),
    })
}
</script>

<template>
    <Head :title="'Koreksi MR ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar :title="'Koreksi: ' + mr.mr_number" title-position="center">
            <template #left><var-button round text @click="router.get(route('admin.overview'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>

        <main class="content">
            <div class="card">
                <div class="head-row">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip type="warning" size="small">{{ mr.status_workflow }}</var-chip>
                </div>
                <div class="info-txt">
                    <span>Pengaju: <strong>{{ mr.user?.name }}</strong> ({{ mr.user?.nik }})</span>
                    <span class="muted">Status dan alur approval tidak akan berubah.</span>
                </div>
                <var-alert v-if="hasPo" type="warning" title="MR sudah memiliki PO" message="Data dan item dikunci agar PO tidak rusak." class="alert" />
            </div>

            <div class="card">
                <div class="lbl">Tujuan Direksi</div>
                <template v-if="canChangeDireksi">
                    <p class="muted">Hanya tersedia saat MR menunggu persetujuan Direksi. Status tidak berubah.</p>
                    <div class="target-row">
                        <var-select v-model="direksiId" filterable placeholder="Pilih Direksi" class="target-select">
                            <var-option v-for="direksi in direksis" :key="direksi.id" :label="`${direksi.name} (${direksi.nik})`" :value="String(direksi.id)" />
                        </var-select>
                        <var-button type="primary" :loading="changingDireksi" :disabled="direksiId === String(mr.direksi_id)" @click="changeDireksi">Ubah Tujuan</var-button>
                    </div>
                </template>
                <p v-else class="muted">Tujuan Direksi terkunci karena MR tidak sedang berstatus Pending Direksi.</p>
            </div>

            <div class="card">
                <div class="lbl">Informasi MR</div>
                <div class="grid">
                    <div class="field"><label>Jenis Pembelian</label><var-select v-model="form.type" :disabled="hasPo"><var-option v-for="o in typeOptions" :key="o" :label="o" :value="o" /></var-select></div>
                    <div class="field"><label>Pabrik</label><var-select v-model="form.factory" :disabled="hasPo"><var-option v-for="o in factoryOptions" :key="o" :label="o" :value="o" /></var-select></div>
                    <div class="field"><label>Alokasi</label><var-select v-model="form.allocation" :disabled="hasPo"><var-option v-for="o in allocationOptions" :key="o" :label="o" :value="o" /></var-select></div>
                    <div class="field"><label>Prioritas</label><var-select v-model="form.status_pembelian" :disabled="hasPo"><var-option v-for="o in statusPembelianOptions" :key="o" :label="o" :value="o" /></var-select></div>
                </div>
            </div>

            <div class="section-head">
                <strong>Daftar Item ({{ form.items.length }})</strong>
                <var-button type="primary" size="small" :disabled="hasPo" @click="addItem"><var-icon name="plus" /> Tambah Item</var-button>
            </div>

            <div v-for="(item, index) in form.items" :key="item.id ?? `new-${index}`" class="card item-card">
                <div class="head-row">
                    <strong>Item #{{ index + 1 }}</strong>
                    <var-button text type="danger" size="small" :disabled="hasPo" @click="removeItem(index)"><var-icon name="trash-can-outline" /> Hapus</var-button>
                </div>
                <div class="grid">
                    <var-input v-model="item.item_name" placeholder="Nama barang *" :disabled="hasPo" :error-message="form.errors[`items.${index}.item_name`]" />
                    <var-select v-model="item.type" placeholder="Jenis *" :disabled="hasPo"><var-option v-for="o in typeOptions" :key="o" :label="o" :value="o" /></var-select>
                    <var-input v-model="item.item_code" placeholder="Kode barang" :disabled="hasPo" />
                    <var-select v-model="item.item_status" placeholder="Status item" :disabled="hasPo"><var-option v-for="o in itemStatusOptions" :key="o" :label="o" :value="o" /></var-select>
                    <var-input v-model.number="item.qty" type="number" min="1" placeholder="Qty *" :disabled="hasPo" :error-message="form.errors[`items.${index}.qty`]" />
                    <var-input v-model="item.unit" placeholder="Satuan *" :disabled="hasPo" :error-message="form.errors[`items.${index}.unit`]" />
                    <var-input v-model.number="item.monthly_usage" type="number" min="0" placeholder="Pemakaian/bulan" :disabled="hasPo" />
                    <var-input v-model.number="item.stock_on_hand" type="number" min="0" placeholder="Stock on hand" :disabled="hasPo" />
                </div>
                <var-input v-model="item.specification" textarea rows="2" placeholder="Spesifikasi/deskripsi" :disabled="hasPo" class="wide-field" />
                <var-input v-model="item.purpose" textarea rows="2" placeholder="Tujuan/keperluan" :disabled="hasPo" class="wide-field" />
                <var-select v-model="item.departemen_id" filterable clearable placeholder="Departemen item" :disabled="hasPo" class="wide-field"><var-option v-for="d in departemens" :key="d.id" :label="d.nama" :value="String(d.id)" /></var-select>
                <div class="foto-row">
                    <label class="foto-btn" :class="{ disabled: hasPo }">Pilih/Ganti Foto<input type="file" accept="image/*" :disabled="hasPo" @change="handleFoto($event, index)" /></label>
                    <div v-if="item.preview" class="foto-preview"><img :src="item.preview" alt="Foto item" /><button type="button" :disabled="hasPo" @click="removeFoto(index)">Hapus foto</button></div>
                </div>
            </div>

            <var-button type="primary" block :loading="form.processing" :disabled="!canSubmit" @click="save">Simpan Koreksi</var-button>
            <var-button type="danger" block text @click="showDelete = true">Hapus MR</var-button>
            <var-dialog :show="showDelete" title="Hapus MR?" message="MR akan dihapus permanen beserta item dan riwayatnya." confirm-button-text="Ya, Hapus" cancel-button-text="Batal" @confirm="confirmDelete" @close="showDelete = false" @cancel="showDelete = false" />
        </main>
    </div>
</template>

<style scoped>
.layout { min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { max-width:900px;margin:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.card { background:#fff;border-radius:14px;padding:16px;border:1px solid #e2e8f0; }
.head-row,.section-head { display:flex;justify-content:space-between;align-items:center;gap:12px; }
.mr-num { font-family:monospace;font-weight:800;font-size:15px; }
.info-txt { display:flex;flex-direction:column;font-size:13px; }
.muted { color:#64748b;font-size:13px; }
.target-row { display:flex;gap:10px;align-items:center; }
.target-select { flex:1; }
.alert { margin-top:12px; }
.lbl { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:12px; }
.grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px; }
.field label { display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px; }
.item-card { display:flex;flex-direction:column;gap:12px; }
.wide-field { width:100%; }
.foto-row { display:flex;align-items:center;gap:12px;flex-wrap:wrap; }
.foto-btn { padding:9px 12px;border:1px dashed #6366f1;border-radius:8px;color:#4f46e5;cursor:pointer;font-size:13px;font-weight:600; }
.foto-btn.disabled { opacity:.5;cursor:not-allowed; }
.foto-btn input { display:none; }
.foto-preview { display:flex;align-items:center;gap:10px; }
.foto-preview img { width:80px;height:80px;object-fit:cover;border-radius:8px; }
.foto-preview button { border:0;background:none;color:#dc2626;cursor:pointer; }
@media (max-width:640px) { .grid { grid-template-columns:1fr; } .target-row { align-items:stretch;flex-direction:column; } }
</style>
