<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ user: any; allRoles: string[] }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const selectedRole = ref(props.user.roles[0]?.name || '')
const saving = ref(false)

const approve = async () => {
    const res = await fetch(`${baseUrl}/admin/users/${props.user.id}/approve`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
    if (res.ok) { Snackbar.success('User diaktifkan'); window.location.reload() }
}

const saveRole = async () => {
    saving.value = true
    const res = await fetch(`${baseUrl}/admin/users/${props.user.id}/role`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ role: selectedRole.value }),
    })
    saving.value = false
    if (res.ok) Snackbar.success('Role diubah')
    else Snackbar.error('Gagal')
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

            <!-- Data User -->
            <div class="card">
                <div class="lbl">Data User</div>
                <div class="grid">
                    <span>NIK</span><span class="mono">{{ user.nik || '—' }}</span>
                    <span>Nama</span><span><strong>{{ user.name }}</strong></span>
                    <span>Email</span><span>{{ user.email }}</span>
                    <span>Role</span><span>{{ user.roles[0]?.name || '—' }}</span>
                </div>
            </div>

            <!-- Aksi -->
            <div class="card">
                <div class="lbl">Aksi</div>
                <div v-if="!user.is_approved" class="action-row">
                    <var-button type="success" block @click="approve">✅ Aktifkan User</var-button>
                </div>
                <div class="action-row">
                    <var-select v-model="selectedRole" placeholder="Pilih Role" style="flex:1">
                        <var-option v-for="r in allRoles" :key="r" :label="r" :value="r" />
                    </var-select>
                    <var-button type="primary" :loading="saving" @click="saveRole">Simpan Role</var-button>
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
</style>
