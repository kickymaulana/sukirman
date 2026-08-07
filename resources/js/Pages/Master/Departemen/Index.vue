<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

interface Dept { id: number; nama: string }

const props = defineProps<{
    departemens: { data: Dept[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string }
}>()

const pp = usePage().props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const searchVal = ref(props.filters?.search || '')

const search = () => { router.get(baseUrl + '/admin/master/departemens', { search: searchVal.value || undefined }, { preserveState: true }) }

const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ nama: '' })
const saving = ref(false)

const openAdd = () => { editingId.value = null; form.value = { nama: '' }; showForm.value = true }
const openEdit = (d: Dept) => { editingId.value = d.id; form.value = { nama: d.nama }; showForm.value = true }

const save = async () => {
    if (!form.value.nama.trim()) { Snackbar.warning('Nama departemen wajib diisi'); return }
    saving.value = true
    const url = editingId.value ? `${baseUrl}/admin/master/departemens/${editingId.value}` : `${baseUrl}/admin/master/departemens`
    const res = await fetch(url, {
        method: editingId.value ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(form.value),
    })
    saving.value = false
    if (res.ok) { Snackbar.success(editingId.value ? 'Departemen diperbarui' : 'Departemen ditambahkan'); showForm.value = false; window.location.reload() }
    else Snackbar.error('Gagal')
}

const confirmDelete = (d: Dept) => {
    Dialog({
        title: 'Hapus Departemen?', message: `Hapus "${d.nama}"?`,
        confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
        onConfirm: async () => {
            const res = await fetch(`${baseUrl}/admin/master/departemens/${d.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } })
            if (res.ok) { Snackbar.success('Departemen dihapus'); window.location.reload() }
        },
    })
}
</script>

<template>
    <Head title="Kelola Departemen - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="🏷️ Kelola Departemen" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right><var-button round text @click="openAdd"><var-icon name="plus" :size="22" color="#22c55e" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="search-row">
                <var-input v-model="searchVal" placeholder="Cari departemen..." clearable @keyup.enter="search" style="flex:1" />
                <var-button type="primary" @click="search"><var-icon name="magnify" :size="16" /></var-button>
            </div>

            <div v-if="showForm" class="form-card">
                <h3>{{ editingId ? 'Edit Departemen' : 'Tambah Departemen' }}</h3>
                <var-input v-model="form.nama" label="Nama Departemen *" placeholder="cth: FILLING" @keyup.enter.native="save" />
                <div class="form-actions">
                    <var-button text @click="showForm = false">Batal</var-button>
                    <var-button type="primary" :loading="saving" @click="save">Simpan</var-button>
                </div>
            </div>

            <div v-if="!departemens.data.length" class="empty">Belum ada departemen.</div>
            <div v-for="d in departemens.data" :key="d.id" class="row-card">
                <span class="name">{{ d.nama }}</span>
                <div class="row-actions">
                    <var-button size="small" text round @click="openEdit(d)"><var-icon name="pencil" :size="16" color="#d97706" /></var-button>
                    <var-button size="small" text round @click="confirmDelete(d)"><var-icon name="delete" :size="16" color="#ef4444" /></var-button>
                </div>
            </div>

            <div class="pagination">
                <a v-if="departemens.prev_page_url" :href="departemens.prev_page_url" class="page-btn">Sebelumnya</a>
                <span class="page-info">{{ departemens.from }}–{{ departemens.to }} dari {{ departemens.total }}</span>
                <a v-if="departemens.next_page_url" :href="departemens.next_page_url" class="page-btn">Selanjutnya</a>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;font-family:Roboto,sans-serif;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:10px; }
.search-row { display:flex;gap:8px;align-items:center; }
.search-form { background:#fff;border-radius:14px;padding:14px;border:1px solid #f1f5f9; }
.form-card { background:#fff;border-radius:14px;padding:16px;border:1px solid #f1f5f9; }
.form-card h3 { margin:0 0 12px;font-size:14px; }
.form-actions { display:flex;justify-content:flex-end;gap:8px;margin-top:12px; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.row-card { display:flex;align-items:center;justify-content:space-between;background:#fff;border-radius:12px;padding:12px 14px;border:1px solid #f1f5f9; }
.name { font-size:14px;font-weight:700;color:#0f172a; }
.row-actions { display:flex;gap:2px; }
.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }
</style>