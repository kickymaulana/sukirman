<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

interface UserOpt { id: number; name: string; nik: string }

const props = defineProps<{
    mr: any
    targetRole: string | null
    managers: UserOpt[]
    fmGms: UserOpt[]
    direksis: UserOpt[]
    allStatuses: string[]
}>()

const baseUrl = (usePage().props as any).app_url || ''

const form = useForm({
    type: props.mr.type,
    factory: props.mr.factory,
    allocation: props.mr.allocation,
    status_pembelian: props.mr.status_pembelian,
    status_workflow: props.mr.status_workflow,
    manager_id: props.mr.manager_id ? String(props.mr.manager_id) : '',
    fm_gm_id: props.mr.fm_gm_id ? String(props.mr.fm_gm_id) : '',
    direksi_id: props.mr.direksi_id ? String(props.mr.direksi_id) : '',
})

const typeOptions = ['Lokal', 'Import']
const factoryOptions = ['KIM', 'DALU 1', 'DALU 2']
const allocationOptions = ['Project', 'Proses']
const statusPembelianOptions = ['Normal', 'Urgent']

type TargetKey = 'manager_id' | 'fm_gm_id' | 'direksi_id'

const targetOptions = computed<{ role: string; options: UserOpt[]; key: TargetKey } | null>(() => {
    if (props.targetRole === 'Manager') return { role: 'Manager', options: props.managers, key: 'manager_id' }
    if (props.targetRole === 'FM/GM') return { role: 'FM/GM', options: props.fmGms, key: 'fm_gm_id' }
    if (props.targetRole === 'Direksi') return { role: 'Direksi', options: props.direksis, key: 'direksi_id' }
    return null
})

const targetValue = computed({
    get: () => (targetOptions.value ? String(form[targetOptions.value.key] || '') : ''),
    set: (v: any) => { if (targetOptions.value) form[targetOptions.value.key] = String(v || '') },
})

const statusBadge = (s: string) => {
    if (['Fully Approved'].includes(s)) return 'success'
    if (['Rejected'].includes(s)) return 'danger'
    if (['Pending Manager', 'Pending FM/GM', 'Pending Direksi'].includes(s)) return 'warning'
    if (['Verifikasi Gudang', 'Purchasing'].includes(s)) return 'info'
    return 'default'
}

const save = () => {
    form.post(`${baseUrl}/admin/overview/${props.mr.id}/update`, {
        onSuccess: () => Snackbar.success('MR berhasil diperbarui'),
    })
}
</script>

<template>
    <Head :title="'Edit MR ' + mr.mr_number" />
    <div class="layout">
        <var-app-bar :title="'Edit: ' + mr.mr_number" title-position="center">
            <template #left><var-button round text @click="router.get(route('admin.overview'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>

        <main class="content">
            <!-- Info -->
            <div class="card">
                <div class="head-row">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip :type="statusBadge(mr.status_workflow)" size="small">{{ mr.status_workflow }}</var-chip>
                </div>
                <div class="info-txt">
                    <span>Diajukan oleh: <strong>{{ mr.user?.name }}</strong> ({{ mr.user?.nik }})</span>
                    <span class="muted">{{ mr.created_at }} • {{ mr.items?.length || 0 }} item</span>
                </div>
                <div class="link-row">
                    <a :href="`${baseUrl}/material-requests/${mr.id}`" class="detail-link">Lihat Detail MR</a>
                </div>
            </div>

            <!-- Status & Identitas -->
            <div class="card">
                <div class="lbl">Status & Identitas</div>
                <div class="field">
                    <label>Status Workflow</label>
                    <var-select v-model="form.status_workflow" filterable>
                        <var-option v-for="s in allStatuses" :key="s" :label="s" :value="s" />
                    </var-select>
                </div>
                <div class="field">
                    <label>Jenis Pembelian</label>
                    <var-select v-model="form.type">
                        <var-option v-for="o in typeOptions" :key="o" :label="o" :value="o" />
                    </var-select>
                </div>
                <div class="field">
                    <label>Pabrik</label>
                    <var-select v-model="form.factory">
                        <var-option v-for="o in factoryOptions" :key="o" :label="o" :value="o" />
                    </var-select>
                </div>
                <div class="field">
                    <label>Alokasi</label>
                    <var-select v-model="form.allocation">
                        <var-option v-for="o in allocationOptions" :key="o" :label="o" :value="o" />
                    </var-select>
                </div>
                <div class="field">
                    <label>Status Pembelian</label>
                    <var-select v-model="form.status_pembelian">
                        <var-option v-for="o in statusPembelianOptions" :key="o" :label="o" :value="o" />
                    </var-select>
                </div>
            </div>

            <!-- Tujuan -->
            <div class="card">
                <div class="lbl">Tujuan Approval</div>
                <p v-if="!targetOptions" class="hint-txt">
                    MR ini tidak sedang menunggu approval (tujuan tidak relevan). Tujuan bisa diubah saat MR kembali ke status menunggu.
                </p>
                <div v-if="targetOptions" class="field">
                    <label>Tujuan {{ targetOptions.role }} (relevan dengan status)</label>
                    <var-select v-model="targetValue" filterable clearable placeholder="Pilih...">
                        <var-option v-for="o in targetOptions.options" :key="o.id" :label="`${o.name} (${o.nik})`" :value="String(o.id)" />
                    </var-select>
                </div>

                <div class="field">
                    <label>Manager</label>
                    <var-select v-model="form.manager_id" filterable clearable placeholder="Pilih Manager...">
                        <var-option v-for="o in managers" :key="o.id" :label="`${o.name} (${o.nik})`" :value="String(o.id)" />
                    </var-select>
                </div>
                <div class="field">
                    <label>FM/GM</label>
                    <var-select v-model="form.fm_gm_id" filterable clearable placeholder="Pilih FM/GM...">
                        <var-option v-for="o in fmGms" :key="o.id" :label="`${o.name} (${o.nik})`" :value="String(o.id)" />
                    </var-select>
                </div>
                <div class="field">
                    <label>Direksi</label>
                    <var-select v-model="form.direksi_id" filterable clearable placeholder="Pilih Direksi...">
                        <var-option v-for="o in direksis" :key="o.id" :label="`${o.name} (${o.nik})`" :value="String(o.id)" />
                    </var-select>
                </div>
            </div>

            <!-- Items (read only) -->
            <div class="card">
                <div class="lbl">Item Barang ({{ mr.items?.length || 0 }})</div>
                <div v-for="(it, i) in mr.items" :key="i" class="item-row">
                    <span class="item-name">{{ it.item_name }}</span>
                    <span class="item-qty">{{ it.qty }} {{ it.unit }}</span>
                </div>
                <p v-if="!mr.items?.length" class="hint-txt">Tidak ada item.</p>
            </div>

            <var-button type="primary" block :loading="form.processing" @click="save">
                Simpan Perubahan
            </var-button>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;min-height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.card { background:#fff;border-radius:14px;padding:16px;border:1px solid #f1f5f9; }
.head-row { display:flex;justify-content:space-between;align-items:center;margin-bottom:8px; }
.mr-num { font-family:monospace;font-weight:800;font-size:15px;color:#0f172a; }
.info-txt { display:flex;flex-direction:column;gap:2px;font-size:13px;color:#0f172a; }
.info-txt .muted { color:#64748b;font-size:12px; }
.link-row { margin-top:8px; }
.detail-link { color:#4f46e5;font-size:13px;font-weight:600;text-decoration:none; }
.lbl { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px; }
.field { margin-bottom:12px; }
.field label { display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px; }
.hint-txt { font-size:12px;color:#94a3b8;margin:0 0 10px; }
.item-row { display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px; }
.item-name { color:#0f172a;font-weight:500; }
.item-qty { color:#4f46e5;font-weight:700;white-space:nowrap; }
</style>