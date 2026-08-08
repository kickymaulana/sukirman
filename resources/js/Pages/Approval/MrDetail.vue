<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar, Dialog } from '@varlet/ui'

const props = defineProps<{ mr: any; userRole: string; deptRole?: string | null; direksiUsers: { id: number; name: string }[]; fmGmUsers?: { id: number; name: string; nik: string }[] }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr
const role = (props.userRole || '').toLowerCase()

const statusChip = computed(() => {
    if (mr.status_workflow === 'Fully Approved') return 'success'
    if (mr.status_workflow === 'Rejected') return 'danger'
    return 'warning'
})

const targetText = computed(() =>
    [mr.manager?.name, mr.fm_gm?.name, mr.direksi?.name].filter(Boolean).join(' → ') || '—'
)

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

const openGudangInput = () => {
    window.location.href = `${baseUrl}/approval/gudang/${mr.id}/input`
}

const openPrint = () => {
    window.location.href = `${baseUrl}/material-requests/${mr.id}/print`
}

// Hapus MR — hanya pengaju & status tertentu yang belum diproses lanjut
const canDelete = computed(() =>
    mr.user_id === pp.auth?.user?.id &&
    ['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD', 'Revision'].includes(mr.status_workflow)
)
const confirmDeleteMr = () => {
    Dialog({
        title: 'Hapus MR?', message: `MR ${mr.mr_number} akan dihapus permanen beserta item, riwayat approval, dan semua notifikasi terkait.`,
        confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
        onConfirm: () => router.delete(route('material-requests.destroy', mr.id)),
    })
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
    if (props.deptRole && mr.status_workflow === `Pending ${props.deptRole}`) {
        a.push({ label: `Setujui (${props.deptRole})`, type: 'dept_approve', action: '' })
        a.push({ label: `Tolak (${props.deptRole})`, type: 'dept_reject', action: '' })
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
    } else if (['dept_approve', 'dept_reject'].includes(actionType.value)) {
        url = `${baseUrl}/approval/${(props.deptRole || '').toLowerCase()}/${mr.id}/decision`
        body.action = actionType.value === 'dept_approve' ? 'approve' : 'reject'
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
    if (['manager_tolak', 'fmgm_tolak', 'stock_yes', 'stock_no', 'dept_approve', 'dept_reject'].includes(type)) { actionType.value = type; confirmAction(); return }
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
            <!-- Ringkas: nomor + status + aksi -->
            <div class="top-card">
                <div class="top-left">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip :type="statusChip" size="small">{{ mr.status_workflow }}</var-chip>
                </div>
                <div class="actions-row">
                    <button class="mini-btn blue" @click="openPrint"><var-icon name="printer-outline" :size="14" style="margin-right:4px" /> Cetak</button>
                    <button v-if="canDelete" class="mini-btn red" @click="confirmDeleteMr"><var-icon name="delete" :size="14" style="margin-right:4px" /> Hapus</button>
                    <button v-if="role === 'gudang'" class="mini-btn green" @click="openGudangInput">Accurate</button>
                    <button v-if="role === 'gudang' && mr.status_workflow === 'Verifikasi Gudang'" class="mini-btn amber" @click="openGudangEdit">Edit</button>
                    <button v-if="['purchasing', 'gudang', 'admin'].includes(role)" class="mini-btn lg" @click="downloadXml">XML</button>
                </div>
            </div>

            <!-- Info ringkas -->
            <div class="card">
                <div class="info-grid">
                    <span>Pengaju</span><span><strong>{{ mr.user?.name }}</strong> ({{ mr.user?.nik || '-' }})</span>
                    <span>Factory</span><span>{{ mr.factory }}</span>
                    <span>Tipe / Jenis</span><span>{{ mr.type }} • {{ mr.jenis || 'UMUM' }}</span>
                    <span>Alokasi / Urgensi</span><span>{{ mr.allocation }} • {{ mr.status_pembelian }}</span>
                    <span>Tanggal</span><span>{{ mr.created_at }}</span>
                    <span>Tujuan</span><span>{{ targetText }}</span>
                </div>
                <div v-if="mr.revision_notes" class="revision-box">{{ mr.revision_notes }}</div>
            </div>

            <!-- Aksi approval -->
            <div v-if="actions.length" class="actions">
                <var-button v-for="a in actions" :key="a.type" block type="primary" @click="doAction(a.type)">{{ a.label }}</var-button>
            </div>

            <!-- Barang + info PO -->
            <div class="card">
                <div class="lbl">Barang ({{ mr.items?.length }})</div>
                <table class="itbl">
                    <thead>
                        <tr><th>No</th><th>Barang</th><th style="text-align:center">Qty</th><th>PO</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in mr.items" :key="item.id">
                            <td class="tno">{{ i + 1 }}</td>
                            <td>
                                <span class="iname">{{ item.item_name }}</span>
                                <span v-if="item.item_code" class="icode">{{ item.item_code }}</span>
                                <span v-if="item.specification" class="ispec">{{ item.specification }}</span>
                                <span v-if="item.purpose" class="ipurpose">{{ item.purpose }}</span>
                            </td>
                            <td class="iqty">{{ item.qty }} {{ item.unit }}</td>
                            <td>
                                <div v-for="l in item.item_po_lines || []" :key="l.id" class="po-line">
                                    <span class="po-no">{{ l.nomor_po || '-' }}</span>
                                    <span class="po-qty">{{ l.qty }}</span>
                                    <span v-if="l.tgl_po" class="po-dt">PO {{ l.tgl_po }}</span>
                                    <span v-if="l.expected_date" class="po-dt">etd {{ l.expected_date }}</span>
                                    <span v-if="l.tanggal_disetujui_direksi" class="po-dt">setuju {{ String(l.tanggal_disetujui_direksi).slice(0, 16).replace('T', ' ') }}</span>
                                </div>
                                <span v-if="!item.item_po_lines?.length" class="no-po">belum ada PO</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Riwayat -->
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
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:10px; }
.top-card { display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between; }
.top-left { display:flex;align-items:center;gap:10px; }
.mr-num { font-family:monospace;font-weight:800;font-size:16px;color:#0f172a; }
.actions-row { display:flex;flex-wrap:wrap;gap:6px; }
.mini-btn { display:flex;align-items:center;justify-content:center;padding:7px 12px;border:none;border-radius:9px;font-size:12px;font-weight:700;color:#fff;cursor:pointer;font-family:inherit; }
.mini-btn.blue { background:#0ea5e9; }
.mini-btn.red { background:#ef4444; }
.mini-btn.green { background:#10b981; }
.mini-btn.amber { background:#f59e0b; }
.mini-btn.lg { background:#22c55e; }
.card { background:#fff;border-radius:14px;padding:14px 16px;border:1px solid #f1f5f9; }
.lbl { font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px; }
.info-grid { display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:13px;color:#475569; }
.revision-box { margin-top:8px;padding:10px;background:#fef3c7;border-radius:8px;font-size:12px;color:#92400e; }
.itbl { width:100%;border-collapse:collapse;font-size:13px; }
.itbl th { background:#f8fafc;color:#334155;text-align:left;padding:8px;font-size:11px;text-transform:uppercase; }
.itbl td { padding:8px;border-top:1px solid #f1f5f9;vertical-align:top; }
.tno { width:20px;color:#94a3b8; }
.iname { display:block;font-weight:700;color:#0f172a; }
.icode { display:block;font-size:11px;color:#4f46e5;font-family:monospace;margin-top:1px; }
.ispec { display:block;font-size:12px;color:#64748b;font-style:italic;margin-top:1px; }
.ipurpose { display:block;font-size:11px;color:#94a3b8;margin-top:1px; }
.iqty { text-align:center;font-weight:700;color:#4f46e5;white-space:nowrap; }
.po-line { display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12px;padding:2px 0; }
.po-no { font-family:monospace;font-weight:700;color:#065f46; }
.po-qty { font-weight:700;color:#334155; }
.po-dt { font-size:11px;color:#94a3b8; }
.no-po { font-size:11px;color:#94a3b8;font-style:italic; }
.actions { display:flex;flex-direction:column;gap:8px; }
.log-row { display:flex;gap:10px;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px; }
.lr { font-weight:700;color:#4f46e5;min-width:60px; }
.la { color:#0f172a; } .lu { color:#64748b;margin-left:auto; }
.empty { color:#94a3b8;font-size:13px;text-align:center;padding:12px; }
</style>
