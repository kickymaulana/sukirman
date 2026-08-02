<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

interface BarangItem { id: number; kode_barang: string; nama_barang: string }

const props = defineProps<{
    barangs: { data: BarangItem[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string }
}>()

const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const searchVal = ref(props.filters?.search || '')

const search = () => {
    router.get(baseUrl + '/barangs', { search: searchVal.value })
}

// Form state
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ kode_barang: '', nama_barang: '' })

const openCreate = () => { editingId.value = null; form.value = { kode_barang: '', nama_barang: '' }; showForm.value = true }
const openEdit = (b: BarangItem) => { editingId.value = b.id; form.value = { kode_barang: b.kode_barang, nama_barang: b.nama_barang }; showForm.value = true }

const submit = async () => {
    if (!form.value.kode_barang.trim() || !form.value.nama_barang.trim()) { Snackbar.warning('Kode & nama barang wajib diisi'); return }
    const url = editingId.value ? `${baseUrl}/barangs/${editingId.value}` : `${baseUrl}/barangs`
    const method = editingId.value ? 'POST' : 'POST'
    const res = await fetch(url, {
        method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(form.value),
    })
    if (res.redirected || res.ok) { Snackbar.success(editingId.value ? 'Barang diperbarui' : 'Barang ditambahkan'); window.location.reload() }
    else { Snackbar.error('Gagal menyimpan') }
}

const confirmDelete = (b: BarangItem) => {
    Dialog({
        title: 'Hapus Barang?', message: `Hapus "${b.nama_barang}"?`,
        confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
        onConfirm: async () => {
            const res = await fetch(`${baseUrl}/barangs/${b.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } })
            if (res.redirected || res.ok) { Snackbar.success('Barang dihapus'); window.location.reload() }
        },
    })
}

// Import CSV
const showImport = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const importing = ref(false)

const triggerFile = () => { fileInput.value?.click() }

const handleFile = async (e: Event) => {
    const input = e.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    importing.value = true
    const fd = new FormData()
    fd.append('file', file)
    const res = await fetch(`${baseUrl}/barangs/import`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd })
    importing.value = false
    if (res.redirected || res.ok) { Snackbar.success('Import selesai'); showImport.value = false; window.location.reload() }
    else { Snackbar.error('Gagal import') }
    input.value = ''
}
</script>

<template>
    <Head title="Manajemen Barang - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📦 Manajemen Barang" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right>
                <var-button round text @click="showImport = true"><var-icon name="upload" :size="22" color="#22c55e" /></var-button>
            </template>
        </var-app-bar>

        <main class="content">
            <!-- Search -->
            <div class="search-box">
                <var-input v-model="searchVal" placeholder="Cari kode / nama barang..." @keyup.enter="search" clearable>
                    <template #append-icon><var-button size="small" @click="search"><var-icon name="magnify" :size="16" /></var-button></template>
                </var-input>
            </div>

            <!-- Form Tambah/Edit -->
            <div v-if="showForm" class="form-card">
                <h3>{{ editingId ? 'Edit Barang' : 'Tambah Barang' }}</h3>
                <div class="form-row">
                    <var-input v-model="form.kode_barang" label="Kode Barang *" placeholder="BRG-0001" />
                    <var-input v-model="form.nama_barang" label="Nama Barang *" placeholder="Nama barang" />
                </div>
                <div class="form-actions">
                    <var-button text @click="showForm = false">Batal</var-button>
                    <var-button type="primary" @click="submit">{{ editingId ? 'Simpan' : 'Tambah' }}</var-button>
                </div>
            </div>

            <!-- Import Modal -->
            <var-dialog :show="showImport" title="Import CSV" @close="showImport=false" @cancel="showImport=false" confirm-button-text="OK" cancel-button-text="Tutup">
                <div class="import-info">
                    <p class="import-title">📋 Format CSV</p>
                    <p>Baris pertama (header) harus berisi kolom:</p>
                    <div class="csv-example">
                        <code>kode_barang,nama_barang</code>
                    </div>
                    <p>Contoh isi data (pakai koma):</p>
                    <div class="csv-example">
                        <code>
BRG-0001,Sapu Lantai Type-1<br/>
BRG-0002,Monitor 24 Inch Type-2<br/>
BRG-0003,Printer Laser Type-3
                        </code>
                    </div>
                    <p>Atau pakai titik koma + tanda kutip (Excel Indonesia):</p>
                    <div class="csv-example">
                        <code>
"kode_barang";"nama_barang"<br/>
"BRG-0001";"Sapu Lantai Type-1"<br/>
"BRG-0002";"Monitor 24 Inch Type-2"
                        </code>
                    </div>
                    <p class="import-notes">📌 Catatan:</p>
                    <ul class="import-notes-list">
                        <li>Kode baru → ditambahkan</li>
                        <li>Kode ada + nama beda → di-update</li>
                        <li>Kode ada + nama sama → di-skip</li>
                    </ul>
                </div>
                <input ref="fileInput" type="file" accept=".csv,.txt" style="display:none" @change="handleFile" />
                <var-button block type="primary" :loading="importing" @click="triggerFile">
                    <var-icon name="upload" :size="18" /> Pilih File CSV
                </var-button>
            </var-dialog>

            <!-- List -->
            <div class="info-bar">Menampilkan {{ barangs.from }}–{{ barangs.to }} dari {{ barangs.total }} barang</div>

            <div v-if="!barangs.data.length" class="empty">
                <var-icon name="package-variant-closed" :size="48" color="#cbd5e1" />
                <p>Belum ada data barang</p>
            </div>

            <div v-for="b in barangs.data" :key="b.id" class="barang-card">
                <span class="kode">{{ b.kode_barang }}</span>
                <span class="nama">{{ b.nama_barang }}</span>
                <div class="actions">
                    <var-button size="small" text round @click="openEdit(b)"><var-icon name="cog" :size="18" color="#d97706" /></var-button>
                    <var-button size="small" text round @click="confirmDelete(b)"><var-icon name="delete" :size="18" color="#ef4444" /></var-button>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <a v-if="barangs.prev_page_url" :href="barangs.prev_page_url + (searchVal ? '&search=' + searchVal : '')" class="page-btn">Sebelumnya</a>
                <span class="page-info">{{ barangs.from }}–{{ barangs.to }} dari {{ barangs.total }}</span>
                <a v-if="barangs.next_page_url" :href="barangs.next_page_url + (searchVal ? '&search=' + searchVal : '')" class="page-btn">Selanjutnya</a>
            </div>
        </main>

        <!-- FAB Tambah -->
        <var-button type="primary" fab class="fab-add" @click="openCreate"><var-icon name="plus" :size="28" /></var-button>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;font-family:Roboto,sans-serif;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.search-box { background:#fff;border-radius:12px;padding:4px;border:1px solid #f1f5f9; }
.form-card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.form-card h3 { margin:0 0 12px;font-size:14px; }
.form-row { display:flex;flex-direction:column;gap:10px; }
.form-actions { display:flex;justify-content:flex-end;gap:8px;margin-top:12px; }
.info-bar { font-size:12px;color:#64748b; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.barang-card { display:flex;align-items:center;gap:12px;background:#fff;border-radius:12px;padding:12px 14px;border:1px solid #f1f5f9; }
.kode { font-family:monospace;font-weight:700;color:#4f46e5;background:#e0e7ff;padding:4px 10px;border-radius:6px;font-size:12px;flex-shrink:0; }
.nama { flex:1;font-size:13px;color:#0f172a; }
.actions { display:flex;gap:2px; }
.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }
.fab-add { position:fixed !important;right:20px;bottom:24px;z-index:99; }

.import-info { margin-bottom:12px; }
.import-title { font-weight:700;font-size:14px;color:#0f172a;margin:0 0 8px; }
.import-info p { font-size:13px;color:#475569;margin:6px 0; }
.csv-example { background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin:4px 0 8px; }
.csv-example code { font-family:monospace;font-size:12px;color:#4f46e5;word-break:break-all;line-height:1.6; }
.import-notes { font-weight:600;color:#0f172a;margin:8px 0 4px !important; }
.import-notes-list { margin:0;padding-left:18px;font-size:13px;color:#475569; }
.import-notes-list li { margin:2px 0; }
</style>
