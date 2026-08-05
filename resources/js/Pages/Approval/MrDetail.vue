<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any; userRole: string; direksiUsers: { id: number; name: string }[]; fmGmUsers?: { id: number; name: string; nik: string }[] }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr
const role = (props.userRole || '').toLowerCase()

// Dialog state
const showForward = ref(false); const selectedDireksi = ref(''); const forwardNotes = ref('')
const showFmGm = ref(false); const selectedFmGm = ref('')
const showAction = ref(false); const actionType = ref(''); const actionNotes = ref('')

const back = () => router.get(route('dashboard'))

// Download XML Accurate — cek dulu item yang akan di-skip
const showSkipDialog = ref(false)
const skipList = ref<{ mr: string; item_name: string; item_code: string }[]>([])
const skipTotal = ref(0)
const downloadXml = async () => {
    try {
        const res = await fetch(`${baseUrl}/material-requests/${mr.id}/check-xml`)
        const data = await res.json()
        if (data.total > 0) {
            skipList.value = data.skips
            skipTotal.value = data.total
            showSkipDialog.value = true
        } else {
            window.location.href = `${baseUrl}/material-requests/${mr.id}/xml`
        }
    } catch {
        window.location.href = `${baseUrl}/material-requests/${mr.id}/xml`
    }
}
const doDownloadXml = () => {
    showSkipDialog.value = false
    window.location.href = `${baseUrl}/material-requests/${mr.id}/xml`
}

const openGudangEdit = () => {
    window.location.href = `${baseUrl}/approval/gudang/${mr.id}/edit`
}

const openPrint = () => {
    window.location.href = `${baseUrl}/material-requests/${mr.id}/print`
}

const logLabel = (action: string) => {
    const map: any = { forward: 'Diteruskan', acknowledge: 'Acknowledge', approve: 'Disetujui', reject: 'Ditolak', revision: 'Revisi', stock_available: 'Stok Tersedia', stock_unavailable: 'Stok Tidak Ada', gudang_edit: 'Diedit Gudang' }
    return map[action] || action
}

// Determine available actions
const actions = computed(() => {
    const a: { label: string; type: string; action: string }[] = []
    if (role === 'manager' && mr.status_workflow === 'Pending Manager') {
        a.push({ label: 'Lanjut ke FM/GM', type: 'manager_lanjut', action: '' })
        a.push({ label: 'Tolak', type: 'manager_tolak', action: '' })
    }
    if (role === 'fm/gm' && mr.status_workflow === 'Pending FM/GM') {
        a.push({ label: 'Forward ke Direksi', type: 'fmgm_forward', action: '' })
        a.push({ label: 'Tolak', type: 'fmgm_tolak', action: '' })
    }
    if (role === 'direksi' && mr.status_workflow === 'Pending Direksi' && mr.direksi_id === pp.auth?.user?.id) {
        a.push({ label: 'Approve', type: 'approve', action: '' })
        a.push({ label: 'Reject', type: 'reject', action: '' })
        a.push({ label: 'Revision (per item)', type: 'revision', action: '' })
    }
    if (role === 'gudang' && mr.status_workflow === 'Verifikasi Gudang') {
        a.push({ label: 'Stok Tersedia', type: 'stock_yes', action: '' })
        a.push({ label: 'Stok Tidak Ada', type: 'stock_no', action: '' })
    }
    return a
})

const confirmAction = async () => {
    let url = ''
    const body: any = {}

    if (actionType.value === 'manager_lanjut') {
        if (!selectedFmGm.value) { Snackbar.warning('Pilih FM/GM tujuan'); return }
        url = `${baseUrl}/approval/manager/${mr.id}/forward`
        body.action = 'lanjut'
        body.fm_gm_id = selectedFmGm.value
    } else if (actionType.value === 'manager_tolak') {
        url = `${baseUrl}/approval/manager/${mr.id}/forward`
        body.action = 'tolak'
        body.notes = actionNotes.value
    } else if (actionType.value === 'fmgm_forward') {
        if (!selectedDireksi.value) { Snackbar.warning('Pilih Direksi'); return }
        url = `${baseUrl}/approval/fmgm/${mr.id}/acknowledge`
        body.action = 'forward'
        body.direksi_id = selectedDireksi.value
        body.notes = forwardNotes.value
    } else if (actionType.value === 'fmgm_tolak') {
        url = `${baseUrl}/approval/fmgm/${mr.id}/acknowledge`
        body.action = 'tolak'
        body.notes = actionNotes.value
    } else if (['approve', 'reject'].includes(actionType.value)) {
        url = `${baseUrl}/approval/direksi/${mr.id}/decision`
        body.action = actionType.value
        body.notes = actionNotes.value
    } else if (actionType.value === 'stock_yes') {
        url = `${baseUrl}/approval/gudang/${mr.id}/verify`
        body.action = 'tersedia'
    } else if (actionType.value === 'stock_no') {
        url = `${baseUrl}/approval/gudang/${mr.id}/verify`
        body.action = 'tidak_tersedia'
    } else return

    const res = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    })
    if (res.ok || res.redirected) { Snackbar.success('Berhasil'); window.location.reload() }
    else { Snackbar.error('Gagal') }
}

const doAction = (type: string) => {
    if (type === 'revision') { window.location.href = baseUrl + '/approval/direksi/' + mr.id + '/revision'; return }
    if (type === 'fmgm_forward') { showForward.value = true; return }
    if (type === 'manager_lanjut') { showFmGm.value = true; return }
    if (['manager_tolak', 'fmgm_tolak', 'stock_yes', 'stock_no'].includes(type)) { actionType.value = type; confirmAction(); return }
    actionType.value = type; showAction.value = true
}
</script>

<template>
    <Head :title="'MR ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar :title="mr.mr_number" title-position="center">
            <template #left><var-button round text @click="back"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <!-- Status -->
            <div class="card"><span class="lbl">Status</span><var-chip :type="mr.status_workflow === 'Fully Approved' ? 'success' : mr.status_workflow === 'Rejected' ? 'danger' : 'warning'">{{ mr.status_workflow }}</var-chip></div>

            <!-- Info -->
            <div class="card">
                <div class="lbl">Informasi MR</div>
                <div class="info-grid">
                    <span>Dibuat oleh</span><span><strong>{{ mr.user?.name }}</strong></span>
                    <span>Factory</span><span>{{ mr.factory }}</span>
                    <span>Tipe</span><span>{{ mr.type }}</span>
                    <span>Alokasi</span><span>{{ mr.allocation }}</span>
                    <span>Urgensi</span><span>{{ mr.status_pembelian }}</span>
                    <span v-if="mr.manager">Manager</span><span v-if="mr.manager">{{ mr.manager?.name }}</span>
                    <span v-if="mr.direksi">Direksi</span><span v-if="mr.direksi">{{ mr.direksi?.name }}</span>
                </div>
                <div v-if="mr.revision_notes" class="revision-box">{{ mr.revision_notes }}</div>
            </div>

            <!-- Items -->
            <div class="card">
                <div class="lbl">Barang ({{ mr.items?.length }})</div>
                <div v-for="item in mr.items" :key="item.id" class="item-detail-card">
                    <div class="item-detail-header">
                        <span class="item-detail-name">{{ item.item_name }}</span>
                        <var-chip v-if="item.item_status" size="mini" type="info">{{ item.item_status }}</var-chip>
                    </div>
                    <div v-if="item.item_code" class="item-detail-code">Kode: {{ item.item_code }}</div>
                    <div v-if="item.specification" class="item-detail-spec">{{ item.specification }}</div>
                    <div class="item-detail-grid">
                        <div class="item-detail-box"><span class="item-detail-label">Jumlah</span><span class="item-detail-value">{{ item.qty }} {{ item.unit }}</span></div>
                        <div class="item-detail-box"><span class="item-detail-label">Pemakaian / Bulan</span><span class="item-detail-value">{{ item.monthly_usage ?? 0 }}</span></div>
                        <div class="item-detail-box"><span class="item-detail-label">Stock On Hand</span><span class="item-detail-value">{{ item.stock_on_hand ?? 0 }}</span></div>
                    </div>
                    <div v-if="item.purpose" class="item-detail-purpose">Keperluan: {{ item.purpose }}</div>
                </div>
            </div>

            <!-- Cetak / Print (semua role) -->
            <div
                @click="openPrint"
                style="display:flex;align-items:center;justify-content:center;gap:8px;background:#0ea5e9;color:#fff;border-radius:12px;padding:14px;font-weight:700;cursor:pointer;"
            >
                <var-icon name="printer-outline" :size="20" /> Cetak MR
            </div>

            <!-- Edit MR (Gudang) -->
            <div
                v-if="role === 'gudang' && mr.status_workflow === 'Verifikasi Gudang'"
                @click="openGudangEdit"
                style="display:flex;align-items:center;justify-content:center;gap:8px;background:#f59e0b;color:#fff;border-radius:12px;padding:14px;font-weight:700;cursor:pointer;"
            >
                <var-icon name="pencil" :size="20" /> Edit MR (Bersihkan Data)
            </div>

            <!-- Download XML Accurate (Purchasing, Gudang, admin) -->
            <div
                v-if="['purchasing', 'gudang', 'admin'].includes(role)"
                @click="downloadXml"
                style="display:flex;align-items:center;justify-content:center;gap:8px;background:#22c55e;color:#fff;border-radius:12px;padding:14px;font-weight:700;cursor:pointer;"
            >
                <var-icon name="file-download" :size="20" /> Download XML Accurate
            </div>

            <!-- Action Buttons -->
            <div v-if="actions.length" class="actions">
                <var-button v-for="a in actions" :key="a.type" block type="primary" @click="doAction(a.type)">{{ a.label }}</var-button>
            </div>

            <!-- History -->
            <div class="card">
                <div class="lbl">Riwayat</div>
                <div v-for="log in mr.approval_logs" :key="log.id" class="log-row">
                    <span class="lr">{{ log.role }}</span>
                    <span class="la">{{ logLabel(log.action) }}</span>
                    <span class="lu">{{ log.user?.name }}</span>
                </div>
                <div v-if="!mr.approval_logs?.length" class="empty">Belum ada riwayat</div>
            </div>
        </main>

        <!-- Forward Dialog (FM/GM ke Direksi) -->
        <var-dialog :show="showForward" title="Forward ke Direksi" @confirm="actionType='fmgm_forward';confirmAction()" @close="showForward=false" @cancel="showForward=false" confirm-button-text="Kirim" cancel-button-text="Batal">
            <var-select v-model="selectedDireksi" placeholder="Ketik & Pilih Direksi" filterable style="margin-bottom:12px">
                <var-option v-for="d in direksiUsers" :key="d.id" :label="d.name" :value="d.id" />
            </var-select>
            <var-input v-model="forwardNotes" placeholder="Catatan (opsional)" />
        </var-dialog>

        <!-- FM/GM Dialog (Manager pilih FM/GM) -->
        <var-dialog :show="showFmGm" title="Lanjut ke FM/GM" @confirm="actionType='manager_lanjut';confirmAction()" @close="showFmGm=false" @cancel="showFmGm=false" confirm-button-text="Kirim" cancel-button-text="Batal">
            <var-select v-model="selectedFmGm" placeholder="Ketik & Pilih FM/GM" filterable style="margin-bottom:12px">
                <var-option v-for="f in (fmGmUsers || [])" :key="f.id" :label="f.name + ' (' + f.nik + ')'" :value="f.id" />
            </var-select>
        </var-dialog>

        <!-- Tolak / Revision Dialog -->
        <var-dialog :show="showAction" :title="['manager_tolak','fmgm_tolak'].includes(actionType) ? 'Tolak MR?' : actionType === 'revision' ? 'Catatan Revisi' : 'Keputusan'" @confirm="confirmAction" @close="showAction=false" @cancel="showAction=false" confirm-button-text="Ya" cancel-button-text="Batal">
            <var-input v-if="['manager_tolak','fmgm_tolak'].includes(actionType) || actionType === 'revision'" v-model="actionNotes" :placeholder="actionType === 'revision' ? 'Catatan revisi...' : 'Alasan ditolak...'" textarea />
        </var-dialog>

        <!-- Dialog: item yang di-skip dari XML -->
        <var-dialog :show="showSkipDialog" title="Item di-skip dari XML" @confirm="doDownloadXml" @close="showSkipDialog=false" @cancel="showSkipDialog=false" confirm-button-text="Tetap Download" cancel-button-text="Batal">
            <p style="font-size:13px;color:#334155;margin:0 0 10px;">
                <strong>{{ skipTotal }}</strong> item akan dilewati karena kode barang tidak terdaftar di tabel Barang (dan pasti gagal di Accurate):
            </p>
            <div v-for="(s, si) in skipList" :key="si" style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">
                <span>{{ s.item_name }}</span>
                <span style="font-family:monospace;color:#dc2626;flex-shrink:0;">{{ s.item_code }}</span>
            </div>
        </var-dialog>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.lbl { font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px; }
.info-grid { display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:13px;color:#475569; }
.revision-box { margin-top:8px;padding:10px;background:#fef3c7;border-radius:8px;font-size:12px;color:#92400e; }
.item-detail-card { background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;margin-bottom:8px; }
.item-detail-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:4px; }
.item-detail-name { font-size:14px;font-weight:700;color:#0f172a; }
.item-detail-code { font-size:11px;color:#64748b;font-family:monospace;margin-bottom:2px; }
.item-detail-spec { font-size:12px;color:#475569;font-style:italic;margin-bottom:8px; }
.item-detail-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:6px; }
.item-detail-box { background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:6px 8px;text-align:center; }
.item-detail-label { display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.3px; }
.item-detail-value { display:block;font-size:14px;font-weight:700;color:#4f46e5; }
.item-detail-purpose { font-size:12px;color:#64748b; }
.actions { display:flex;flex-direction:column;gap:8px; }
.log-row { display:flex;gap:10px;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px; }
.lr { font-weight:700;color:#4f46e5;min-width:60px; }
.la { color:#0f172a; } .lu { color:#64748b;margin-left:auto; }
.empty { color:#94a3b8;font-size:13px;text-align:center;padding:12px; }
</style>
