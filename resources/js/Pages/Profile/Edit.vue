<script setup lang="ts">
import { ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

interface UserInfo { id: number; name: string; email: string; nik?: string|null; departemen_id?: number|null }
interface Dept { id: number; nama: string }

const props = defineProps<{ user: UserInfo; departemens?: Dept[] }>()

const editName = ref(props.user.name || '')
const editEmail = ref(props.user.email || '')
const editDept = ref(props.user.departemen_id ? String(props.user.departemen_id) : '')
const saving = ref(false)
const errorMsg = ref('')

const save = async () => {
    errorMsg.value = ''
    if (!editName.value.trim()) { errorMsg.value = 'Nama tidak boleh kosong'; return }
    if (!editEmail.value.trim() || !/^\S+@\S+\.\S+$/.test(editEmail.value)) { errorMsg.value = 'Email tidak valid'; return }

    saving.value = true
    try {
        const res = await fetch(`${baseUrl}/profile/update`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ name: editName.value.trim(), email: editEmail.value.trim(), departemen_id: editDept.value || null }),
        })
        const data = await res.json().catch(() => ({}))
        saving.value = false
        if (res.ok) { window.location.href = baseUrl + '/profile' }
        else { errorMsg.value = data.message || 'Gagal menyimpan profil' }
    } catch { saving.value = false; errorMsg.value = 'Gagal menyimpan profil' }
}

const back = () => { window.location.href = baseUrl + '/profile' }
</script>

<template>
    <Head title="Edit Profil - SUKIRMAN" />
    <div class="screen">
        <div class="header-bar">
            <button class="back-btn" @click="back">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
            </button>
            <h1>Edit Profil</h1>
            <div style="width:40px"></div>
        </div>

        <div class="content">
            <div class="avatar">{{ props.user.name?.charAt(0)?.toUpperCase() || '?' }}</div>

            <div class="edit-card">
                <div class="field">
                    <label>Nama</label>
                    <input v-model="editName" type="text" placeholder="Nama lengkap" :disabled="saving" />
                </div>

                <div class="field">
                    <label>Email</label>
                    <input v-model="editEmail" type="email" placeholder="email@contoh.com" :disabled="saving" />
                </div>

                <div class="field">
                    <label>Departemen <span class="opt">(opsional)</span></label>
                    <select v-model="editDept" :disabled="saving">
                        <option value="">— Pilih Departemen —</option>
                        <option v-for="d in departemens || []" :key="d.id" :value="String(d.id)">{{ d.nama }}</option>
                    </select>
                </div>

                <p v-if="errorMsg" class="err">{{ errorMsg }}</p>

                <button class="save-btn" :disabled="saving" @click="save">
                    {{ saving ? 'Menyimpan...' : 'Simpan Profil' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.screen { min-height:100vh; background:#f8fafc; font-family:'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
.header-bar { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; background:#fff; border-bottom:1px solid #f1f5f9; }
.back-btn { width:40px; height:40px; border-radius:12px; border:none; background:#f8fafc; color:#4f46e5; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.header-bar h1 { font-size:18px; font-weight:700; color:#0f172a; margin:0; }
.content { padding:24px 20px; display:flex; flex-direction:column; align-items:center; gap:20px; }
.avatar { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; font-size:32px; font-weight:800; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 25px rgba(79,70,229,.3); }
.edit-card { width:100%; max-width:400px; background:#fff; border-radius:20px; padding:24px; border:1px solid #f1f5f9; box-shadow:0 4px 15px rgba(0,0,0,.03); box-sizing:border-box; }
.field { margin-bottom:16px; }
.field label { display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px; }
.field .opt { font-size:11px; color:#94a3b8; font-weight:400; }
.field input, .field select { width:100%; padding:11px 14px; border:1px solid #e2e8f0; border-radius:12px; font-size:14px; color:#0f172a; background:#fff; outline:none; font-family:inherit; box-sizing:border-box; }
.field input:focus, .field select:focus { border-color:#4f46e5; }
.err { font-size:12px; color:#dc2626; font-weight:600; margin:0 0 10px; }
.save-btn { width:100%; padding:14px; border:none; border-radius:14px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity .2s; }
.save-btn:disabled { opacity:.5; }
</style>