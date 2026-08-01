<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ mr: any; userRole: string; direksiUsers: { id: number; name: string }[] }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr
const role = (props.userRole || '').toLowerCase()

// Dialog state
const showForward = ref(false); const selectedDireksi = ref(''); const forwardNotes = ref('')
const showAction = ref(false); const actionType = ref(''); const actionNotes = ref('')

const back = () => router.get(route('dashboard'))

const logLabel = (action: string) => {
    const map: any = { forward: 'Diteruskan', acknowledge: 'Acknowledge', approve: 'Disetujui', reject: 'Ditolak', revision: 'Revisi', stock_available: 'Stok Tersedia', stock_unavailable: 'Stok Tidak Ada' }
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
        a.push({ label: 'Revision', type: 'revision', action: '' })
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
        url = `${baseUrl}/approval/manager/${mr.id}/forward`
        body.action = 'lanjut'
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
    } else if (['approve', 'reject', 'revision'].includes(actionType.value)) {
        url = `${baseUrl}/approval/direksi/${mr.id}/decision`
        body.action = actionType.value
        if (actionType.value === 'revision') body.notes = actionNotes.value
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
    if (type === 'fmgm_forward') { showForward.value = true; return }
    if (type === 'manager_lanjut') { actionType.value = type; confirmAction(); return }
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
                <div v-for="item in mr.items" :key="item.id" class="item-row">
                    <span class="iname">{{ item.item_name }} <em v-if="item.specification">({{ item.specification }})</em></span>
                    <span class="iqty">{{ item.qty }} {{ item.unit }}</span>
                    <span v-if="item.purpose" class="ipurpose">{{ item.purpose }}</span>
                </div>
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
            <var-select v-model="selectedDireksi" placeholder="Pilih Direksi" style="margin-bottom:12px">
                <var-option v-for="d in direksiUsers" :key="d.id" :label="d.name" :value="d.id" />
            </var-select>
            <var-input v-model="forwardNotes" placeholder="Catatan (opsional)" />
        </var-dialog>

        <!-- Tolak / Revision Dialog -->
        <var-dialog :show="showAction" :title="['manager_tolak','fmgm_tolak'].includes(actionType) ? 'Tolak MR?' : actionType === 'revision' ? 'Catatan Revisi' : 'Keputusan'" @confirm="confirmAction" @close="showAction=false" @cancel="showAction=false" confirm-button-text="Ya" cancel-button-text="Batal">
            <var-input v-if="['manager_tolak','fmgm_tolak'].includes(actionType) || actionType === 'revision'" v-model="actionNotes" :placeholder="actionType === 'revision' ? 'Catatan revisi...' : 'Alasan ditolak...'" textarea />
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
.item-row { display:flex;flex-wrap:wrap;gap:4px 12px;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:13px; }
.iname { flex:1;font-weight:500; } .iqty { color:#4f46e5;font-weight:600; } .ipurpose { width:100%;font-size:11px;color:#94a3b8; }
.actions { display:flex;flex-direction:column;gap:8px; }
.log-row { display:flex;gap:10px;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px; }
.lr { font-weight:700;color:#4f46e5;min-width:60px; }
.la { color:#0f172a; } .lu { color:#64748b;margin-left:auto; }
.empty { color:#94a3b8;font-size:13px;text-align:center;padding:12px; }
</style>
