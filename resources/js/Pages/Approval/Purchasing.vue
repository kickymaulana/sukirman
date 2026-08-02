<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface MR { id: number; mr_number: string; factory: string; type: string; status_workflow: string; user: { name: string }; items: any[] }
const props = defineProps<{ requests: { data: MR[] } }>()
const baseUrl = (usePage().props as any).app_url || ''

const totalItems = (items: any[]) => items.reduce((sum: number, i: any) => sum + Number(i.qty), 0)
const openDetail = (id: number) => { window.location.href = baseUrl + '/material-requests/' + id }

// Download XML — cek dulu item yang akan di-skip
const showSkipDialog = ref(false)
const skipList = ref<{ mr: string; item_name: string; item_code: string }[]>([])
const skipTotal = ref(0)
const downloadXml = async () => {
    try {
        const res = await fetch(baseUrl + '/approval/purchasing/check-xml')
        const data = await res.json()
        if (data.total > 0) {
            skipList.value = data.skips
            skipTotal.value = data.total
            showSkipDialog.value = true
        } else {
            window.open(baseUrl + '/approval/purchasing/export', '_blank')
        }
    } catch {
        window.open(baseUrl + '/approval/purchasing/export', '_blank')
    }
}
const doDownloadXml = () => {
    showSkipDialog.value = false
    window.open(baseUrl + '/approval/purchasing/export', '_blank')
}
</script>

<template>
    <Head title="Purchasing - SUKIRMAN" />
    <div class="android-layout">
        <var-app-bar title="Purchasing" title-position="center">
            <template #left><var-button round text @click="router.get(route('dashboard'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right>
                <a :href="baseUrl + '/approval/purchasing/export'" target="_blank">
                    <var-button round text><var-icon name="file-download" :size="24" color="#22c55e" /></var-button>
                </a>
            </template>
        </var-app-bar>
        <main class="android-content">
            <div style="margin-bottom:12px;display:flex;gap:8px;font-size:13px;color:#64748b;">
                <span>MR siap eksekusi: <strong>{{ requests.data.length }}</strong></span>
            </div>

            <div @click="downloadXml" style="display:block;background:#22c55e;color:#fff;text-align:center;padding:14px;border-radius:12px;font-weight:700;cursor:pointer;margin-bottom:16px;">
                ⬇ Download XML Accurate
            </div>

            <var-dialog :show="showSkipDialog" title="Item di-skip dari XML" @confirm="doDownloadXml" @close="showSkipDialog=false" @cancel="showSkipDialog=false" confirm-button-text="Tetap Download" cancel-button-text="Batal">
                <p style="font-size:13px;color:#334155;margin:0 0 10px;">
                    <strong>{{ skipTotal }}</strong> item akan dilewati karena kode barang tidak terdaftar di tabel Barang (dan pasti gagal di Accurate):
                </p>
                <div v-for="(s, si) in skipList" :key="si" style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">
                    <span>{{ s.mr }} • {{ s.item_name }}</span>
                    <span style="font-family:monospace;color:#dc2626;flex-shrink:0;">{{ s.item_code }}</span>
                </div>
            </var-dialog>

            <div v-if="!requests.data.length" class="empty">Belum ada MR</div>
            <div v-for="mr in requests.data" :key="mr.id" class="card" @click="openDetail(mr.id)">
                <div class="card-h">
                    <span class="mr-num">{{ mr.mr_number }}</span>
                    <var-chip :type="mr.status_workflow === 'Fully Approved' ? 'success' : 'warning'" size="mini">{{ mr.status_workflow }}</var-chip>
                </div>
                <p><strong>{{ mr.user?.name }}</strong> • {{ mr.factory }} • {{ mr.type }}</p>
                <p class="items">{{ mr.items.length }} item, total {{ totalItems(mr.items) }} unit</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.android-layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.android-content { flex:1;overflow-y:auto;padding:16px 20px 80px; }
.empty { text-align:center;padding:40px;color:#94a3b8; }
.card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9;margin-bottom:10px;cursor:pointer; }
.card-h { display:flex;justify-content:space-between;align-items:center;margin-bottom:4px; }
.mr-num { font-family:monospace;font-weight:800;font-size:13px; }
p { margin:2px 0;font-size:13px;color:#475569; }
.items { font-size:12px;color:#64748b; }
</style>
