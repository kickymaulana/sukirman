<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ user: any; allRoles: string[]; departemens?: { id: number; nama: string }[] }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const currentRoles = (props.user.roles || []).map((r: any) => r.name)
const selectedRoles = ref<string[]>(currentRoles)
if (props.user.requested_role && !currentRoles.includes(props.user.requested_role)) {
    selectedRoles.value.push(props.user.requested_role)
}
const saving = ref(false)

const selectedDepartemen = ref(props.user.departemen_id ? String(props.user.departemen_id) : '')
const savingDept = ref(false)

const saveDepartemen = async () => {
    savingDept.value = true
    const res = await fetch(`${baseUrl}/admin/users/${props.user.id}/departemen`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ departemen_id: selectedDepartemen.value || null }),
    })
    savingDept.value = false
    if (res.ok) { Snackbar.success('Departemen diperbarui'); window.location.reload() }
    else { Snackbar.error('Gagal') }
}

const toggleRole = (r: string) => {
    const i = selectedRoles.value.indexOf(r)
    if (i > -1) selectedRoles.value.splice(i, 1)
    else selectedRoles.value.push(r)
}

const approve = async () => {
    const res = await fetch(`${baseUrl}/admin/users/${props.user.id}/approve`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
    if (res.ok) { Snackbar.success('User diaktifkan'); window.location.reload() }
}

const saveRole = async () => {
    if (!selectedRoles.value.length) { Snackbar.warning('Pilih minimal satu role'); return }
    saving.value = true
    const res = await fetch(`${baseUrl}/admin/users/${props.user.id}/role`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ roles: selectedRoles.value }),
    })
    saving.value = false
    if (res.ok) { Snackbar.success('Role diperbarui'); window.location.reload() }
    else { Snackbar.error('Gagal') }
}
</script>

<template>
    <Head :title="'User: ' + user.name" />
    <div class="layout">
        <var-app-bar title="Detail User" title-position="center">
            <template #left><var-button round text @click="router.get(route('admin.users'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <!-- Status -->
            <div class="card">
                <var-chip :type="user.is_approved ? 'success' : 'warning'" size="small">{{ user.is_approved ? '✅ AKTIF' : '⏳ MENUNGGU AKTIVASI' }}</var-chip>
            </div>

            <!-- Permintaan Role -->
            <div v-if="user.requested_role && !user.is_approved" class="card req-card">
                <div class="lbl">Permintaan Role dari User</div>
                <div class="req-box">
                    <span class="req-icon">📋</span>
                    <div>
                        <span class="req-label">User ini meminta role sebagai:</span>
                        <span class="req-role">{{ user.requested_role }}</span>
                        <span class="req-hint">Saat menekan "Aktifkan User", role ini otomatis diberikan. Anda bisa mengubahnya sebelum/sesudah.</span>
                    </div>
                </div>
            </div>

            <!-- Data User -->
            <div class="card">
                <div class="lbl">Data User</div>
                <div class="grid">
                    <span>NIK</span><span class="mono">{{ user.nik || '—' }}</span>
                    <span>Nama</span><span><strong>{{ user.name }}</strong></span>
                    <span>Email</span><span>{{ user.email }}</span>
                    <span>Departemen</span><span>{{ user.departemen?.nama || '—' }}</span>
                    <span>Role</span><span>{{ user.roles[0]?.name || '—' }}</span>
                </div>
            </div>

            <!-- Aksi -->
            <div class="card">
                <div class="lbl">Aksi</div>
                <div v-if="!user.is_approved" class="action-row">
                    <var-button type="success" block @click="approve">{{ user.requested_role ? `✅ Aktifkan & Beri Role ${user.requested_role}` : '✅ Aktifkan User' }}</var-button>
                </div>
                <div class="action-row">
                    <div class="role-checkboxes">
                        <label
                            v-for="r in allRoles"
                            :key="r"
                            class="role-check"
                            :class="{ checked: selectedRoles.includes(r) }"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedRoles.includes(r)"
                                @change="toggleRole(r)"
                            />
                            <span>{{ r }}</span>
                        </label>
                    </div>
                    <var-button type="primary" :loading="saving" @click="saveRole">Simpan Role</var-button>
                </div>

                <!-- Departemen -->
                <div class="action-row">
                    <var-select v-model="selectedDepartemen" placeholder="Pilih Departemen" clearable style="flex:1">
                        <var-option v-for="d in departemens || []" :key="d.id" :label="d.nama" :value="String(d.id)" />
                    </var-select>
                    <var-button type="primary" :loading="savingDept" @click="saveDepartemen">Simpan Departemen</var-button>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.lbl { font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px; }
.grid { display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:13px;color:#475569; }
.mono { font-family:monospace;color:#4f46e5; }
.action-row { display:flex;gap:10px;align-items:center;margin-top:8px; }
.role-checkboxes { display:flex;flex-wrap:wrap;gap:8px;flex:1; }
.role-check { display:flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;color:#475569;cursor:pointer; }
.role-check.checked { background:#eef2ff;border-color:#4f46e5;color:#4f46e5; }
.role-check input { accent-color:#4f46e5; }
.req-card { border-color:#fde68a;background:#fffbeb; }
.req-box { display:flex;gap:12px;align-items:flex-start; }
.req-icon { font-size:26px; }
.req-label { display:block;font-size:13px;color:#92400e;font-weight:600; }
.req-role { display:inline-block;margin-top:4px;font-size:14px;font-weight:800;color:#b45309;background:#fef3c7;padding:3px 10px;border-radius:8px; }
.req-hint { display:block;margin-top:6px;font-size:12px;color:#a16207;line-height:1.4; }
</style>
