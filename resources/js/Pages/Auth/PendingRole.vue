<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ user: { name: string; nik: string }; departemens: { id: number; nama: string }[] }>()
const baseUrl = (usePage().props as any).app_url || ''

const roles = [
    { name: 'Supervisor', desc: 'Membuat & mengajukan usulan barang (MR)' },
    { name: 'Manager', desc: 'Menyetujui & meneruskan MR ke FM/GM' },
    { name: 'FM/GM', desc: 'Acknowledge & meneruskan MR ke Direksi' },
    { name: 'Direksi', desc: 'Keputusan akhir: approve / reject / revisi' },
    { name: 'Gudang', desc: 'Verifikasi stok & membersihkan data MR' },
    { name: 'Purchasing', desc: 'Download & proses pembelian (export Accurate)' },
]

const form = useForm({ role: '', departemen_id: '' })

const submit = () => {
    if (!form.role) { Snackbar.warning('Pilih posisi/jabatan Anda terlebih dahulu'); return }
    if (!form.departemen_id) { Snackbar.warning('Pilih departemen Anda terlebih dahulu'); return }
    form.post(baseUrl + '/pending-role', {
        onError: () => Snackbar.error('Gagal menyimpan. Coba lagi.'),
    })
}
</script>

<template>
    <Head title="Pilih Posisi - SUKIRMAN" />
    <div class="screen">
        <div class="card">
            <div class="brand">
                <h1 class="app-title">SUKIRMAN</h1>
                <p class="welcome">Halo, <strong>{{ user.name }}</strong> ({{ user.nik }})! 👋</p>
                <p class="subtitle">
                    Selamat datang! Sebelum Admin mengaktifkan akun Anda, pilih dulu posisi/jabatan Anda di perusahaan.
                    Permintaan ini akan dikirim ke Admin untuk diproses.
                </p>
            </div>

            <div class="role-list">
                <div
                    v-for="r in roles"
                    :key="r.name"
                    class="role-item"
                    :class="{ active: form.role === r.name }"
                    @click="form.role = r.name"
                >
                    <div class="role-info">
                        <span class="role-name">{{ r.name }}</span>
                        <span class="role-desc">{{ r.desc }}</span>
                    </div>
                    <var-icon :name="form.role === r.name ? 'radiobox-marked' : 'radiobox-blank'" :size="22" :color="form.role === r.name ? '#4f46e5' : '#cbd5e1'" />
                </div>
            </div>

            <!-- Departemen (opsional) -->
            <div class="dept-block">
                <label class="dept-label">Departemen <span class="dept-tag">(wajib)</span></label>
                <var-select
                    v-model="form.departemen_id"
                    filterable
                    placeholder="Ketik untuk mencari departemen Anda..."
                    clearable
                >
                    <var-option v-for="d in departemens" :key="d.id" :label="d.nama" :value="String(d.id)" />
                </var-select>
            </div>

            <var-button type="primary" block size="large" :loading="form.processing" @click="submit">
                Kirim Permintaan Posisi
            </var-button>
        </div>
    </div>
</template>

<style scoped>
.screen {
    min-height: 100vh;
    background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 60%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}

.card {
    width: 100%;
    max-width: 480px;
    background: #ffffff;
    border-radius: 24px;
    padding: 28px 24px;
    box-shadow: 0 20px 50px rgba(79, 70, 229, 0.12);
    border: 1px solid #eef2ff;
}

.brand { margin-bottom: 20px; }

.app-title {
    font-size: 26px;
    font-weight: 800;
    color: #4f46e5;
    margin: 0 0 10px 0;
    letter-spacing: 1px;
}

.welcome { font-size: 15px; color: #0f172a; margin: 0 0 6px 0; }
.subtitle { font-size: 13px; color: #64748b; line-height: 1.5; margin: 0; }

.role-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }

.role-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.15s;
}

.role-item:hover { border-color: #c7d2fe; }
.role-item.active { border-color: #4f46e5; background: #eef2ff; }

.role-info { display: flex; flex-direction: column; gap: 2px; }
.role-name { font-size: 14px; font-weight: 700; color: #0f172a; }
.role-desc { font-size: 12px; color: #64748b; }
.dept-block { margin-bottom: 18px; }
.dept-label { display:block;font-size:13px;font-weight:600;color:#0f172a;margin-bottom:8px; }
.dept-tag { font-size:11px;color:#dc2626;font-weight:600; }
</style>
