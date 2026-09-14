<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Transition } from 'vue'

interface PoLine {
    id: number
    nomor_po: string | null
    tgl_po: string | null
    expected_date: string | null
    tanggal_disetujui_direksi: string | null
    purchasing: string
}

interface Item {
    id: number
    item_code: string | null
    item_name: string
    specification: string | null
    purpose: string | null
    qty: number
    remaining_qty: number
    qty_tersedia: number | null
    unit: string
    type: string
    po_lines: PoLine[]
    has_foto: boolean
    mr_id: number | null
    mr_number: string | null
    jenis: string | null
    factory: string | null
    status_workflow: string | null
    created_at: string | null
    pengaju: string | null
    departemen: string | null
}

const props = defineProps<{
    items: { data: Item[]; links: any[]; from: number; to: number; total: number; prev_page_url: string|null; next_page_url: string|null }
    filters?: { search?: string; factory?: string; jenis?: string; status?: string; type?: string }
    can_edit_po: boolean
    allFactories: string[]
    allJenis: string[]
    allStatuses: string[]
}>()

const baseUrl = (usePage().props as any).app_url || ''
const searchVal = ref(props.filters?.search || '')
const factoryVal = ref(props.filters?.factory || '')
const jenisVal = ref(props.filters?.jenis || '')
const statusVal = ref(props.filters?.status || '')
const typeVal = ref(props.filters?.type || '')

const poPageParams = () => {
    const query = new URLSearchParams(window.location.search)
    return { ...props.filters, page: query.get('page') || '1' }
}
const createPo = (item: Item) => router.get(`${baseUrl}/monitoring-items/${item.id}/po-lines/create`, poPageParams())
const editPo = (item: Item, line: PoLine) => router.get(`${baseUrl}/monitoring-items/${item.id}/po-lines/${line.id}/edit`, poPageParams())

const statusBadge = (s: string | null) => {
    if (['Fully Approved'].includes(s || '')) return 'success'
    if (['Rejected'].includes(s || '')) return 'danger'
    if (['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD', 'Revision'].includes(s || '')) return 'warning'
    if (['Verifikasi Gudang', 'Purchasing'].includes(s || '')) return 'info'
    return 'default'
}

const applyFilters = () => {
    router.get(baseUrl + '/monitoring-items', {
        search: searchVal.value || undefined,
        factory: factoryVal.value || undefined,
        jenis: jenisVal.value || undefined,
        status: statusVal.value || undefined,
        type: typeVal.value || undefined,
    }, { preserveState: true })
}

const openMr = (id: number | null) => {
    if (id) router.get(baseUrl + '/material-requests/' + id)
}

const goBack = () => router.get(route('dashboard'))

// Photo viewer
const photoShow = ref(false)
const photoId = ref(0)
const photoName = ref('')
const showPhoto = (id: number, name: string) => {
    photoId.value = id
    photoName.value = name
    photoShow.value = true
}
</script>

<template>
    <Head title="Monitoring Item - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="📋 Monitoring Item" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="filter-bar">
                <var-input v-model="searchVal" placeholder="Cari item / kode / MR / nama / NIK..." clearable @keyup.enter="applyFilters" style="flex:1;max-width:320px" />
                <var-select v-model="factoryVal" placeholder="Semua Factory" style="width:200px" @change="applyFilters">
                    <var-option label="Semua Factory" value="" />
                    <var-option v-for="f in allFactories" :key="f" :label="f" :value="f" />
                </var-select>
                <var-select v-model="jenisVal" placeholder="Semua Jenis" style="width:200px" @change="applyFilters">
                    <var-option label="Semua Jenis" value="" />
                    <var-option v-for="j in allJenis" :key="j" :label="j" :value="j" />
                </var-select>
                <var-select v-model="typeVal" placeholder="Tipe Item" aria-label="Tipe Item" style="width:200px" @change="applyFilters">
                    <var-option label="Semua" value="" />
                    <var-option label="Lokal" value="Lokal" />
                    <var-option label="Import" value="Import" />
                </var-select>
                <var-select v-model="statusVal" placeholder="Semua Status" style="width:200px" @change="applyFilters">
                    <var-option label="Semua Status" value="" />
                    <var-option v-for="s in allStatuses" :key="s" :label="s" :value="s" />
                </var-select>
                <var-button type="primary" @click="applyFilters"><var-icon name="magnify" :size="16" /></var-button>
            </div>

            <div class="info-bar">Menampilkan {{ items.from }}–{{ items.to }} dari {{ items.total }} item</div>

            <div v-if="!items.data.length" class="empty">
                <var-icon name="text-box-remove-outline" :size="48" color="#cbd5e1" />
                <p>Tidak ada item</p>
            </div>

            <div v-for="it in items.data" :key="it.id" class="item-card">
                <div class="item-head">
                    <span v-if="it.item_code" class="kode">{{ it.item_code }}</span>
                    <span v-if="it.jenis" class="jenis">{{ it.jenis }}</span>
                    <span v-if="it.factory" class="factory">{{ it.factory }}</span>
                    <var-chip v-if="it.status_workflow" :type="statusBadge(it.status_workflow)" size="mini">{{ it.status_workflow }}</var-chip>
                </div>
                <div class="item-body">
                    <div class="item-info">
                        <span class="iname">{{ it.item_name }}</span>
                        <span class="ispec">Tipe: {{ it.type }}</span>
                        <div v-for="po in it.po_lines" :key="po.id" class="ispec">
                            PO: {{ po.nomor_po?.trim() || 'Nomor belum diisi' }} · Purchasing: {{ po.purchasing }}
                            <var-button v-if="can_edit_po" size="mini" text type="primary" :aria-label="`Edit PO ${po.nomor_po?.trim() || po.id} untuk ${it.item_name}`" @click="editPo(it, po)">Edit PO</var-button>
                        </div>
                        <span v-if="!it.po_lines.length" class="ispec">Belum ada PO</span>
                        <var-button v-if="can_edit_po && it.status_workflow === 'Purchasing' && it.remaining_qty > 0" size="small" type="primary" :aria-label="`${it.po_lines.length ? 'Tambah' : 'Isi'} PO untuk ${it.item_name}`" @click="createPo(it)">{{ it.po_lines.length ? 'Tambah PO' : 'Isi PO' }}</var-button>
                        <span v-if="it.specification" class="ispec">{{ it.specification }}</span>
                        <span v-if="it.purpose" class="ipurpose">{{ it.purpose }}</span>
                        <span class="iqty">{{ it.qty }} {{ it.unit }}<template v-if="it.qty_tersedia != null"> · stok {{ it.qty_tersedia }}</template></span>
                        <span class="imeta">
                            <a v-if="it.mr_number" class="link-mr" @click.prevent="openMr(it.mr_id)" href="#">{{ it.mr_number }}</a>
                            <template v-if="it.pengaju"> · {{ it.pengaju }}</template>
                            <template v-if="it.departemen"> · {{ it.departemen }}</template>
                            <template v-if="it.created_at"> · {{ it.created_at }}</template>
                        </span>
                    </div>
                    <div v-if="it.has_foto" class="thumb-wrap">
                        <img :src="`${baseUrl}/item-foto/${it.id}`" class="thumb" alt="foto" @click="showPhoto(it.id, it.item_name)" />
                    </div>
                </div>
            </div>

            <div v-if="items.total > 0" class="pagination">
                <a v-if="items.prev_page_url" :href="items.prev_page_url" class="page-btn">Sebelumnya</a>
                <span class="page-info">{{ items.from }}–{{ items.to }} dari {{ items.total }}</span>
                <a v-if="items.next_page_url" :href="items.next_page_url" class="page-btn">Selanjutnya</a>
            </div>
        </main>

        <Transition name="fade">
            <div v-if="photoShow" class="photo-overlay" @click="photoShow = false">
                <div class="photo-title">{{ photoName }}</div>
                <img :src="`${baseUrl}/item-foto/${photoId}`" alt="preview" class="photo-image" @click.stop />
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 24px 60px;display:flex;flex-direction:column;gap:14px; }
.filter-bar { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }
.info-bar { font-size:12px;color:#64748b; }
.empty { text-align:center;padding:40px;color:#94a3b8; }

.item-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px; }
.item-head { display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px; }
.kode { font-family:monospace;font-weight:700;color:#4f46e5;background:#e0e7ff;padding:4px 10px;border-radius:6px;font-size:12px; }
.jenis { font-size:11px;font-weight:600;color:#334155;background:#f1f5f9;padding:2px 8px;border-radius:6px; }
.factory { font-size:11px;color:#475569;font-weight:600; }
.item-body { display:flex;align-items:flex-start;gap:12px; }
.item-info { flex:1;display:flex;flex-direction:column;gap:2px; }
.iname { font-size:14px;font-weight:700;color:#0f172a; }
.ispec { font-size:12px;color:#64748b;font-style:italic; }
.ipurpose { font-size:11px;color:#94a3b8; }
.iqty { font-size:12px;font-weight:700;color:#4f46e5; }
.imeta { font-size:11px;color:#94a3b8; }
.link-mr { color:#4f46e5;font-family:monospace;font-weight:700;text-decoration:underline; }
.thumb-wrap { flex-shrink:0; }
.thumb { width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:zoom-in; }

.pagination { display:flex;align-items:center;justify-content:center;gap:16px;font-size:13px; }
.page-btn { padding:6px 16px;border-radius:8px;background:#e0e7ff;color:#4f46e5;text-decoration:none;font-weight:600; }
.page-info { color:#64748b; }

.photo-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: #000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.photo-title {
  color: #fff;
  margin-bottom: 12px;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
  padding: 0 16px;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.photo-image {
  max-width: 100vw;
  max-height: 85vh;
  object-fit: contain;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>