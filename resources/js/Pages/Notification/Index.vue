<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

interface NotifItem {
    id: string; message: string; mr_id: number | null; mr_number: string; time: string
}

const props = defineProps<{ notifications: NotifItem[] }>()

const markRead = async (id: string, mrId: number | null) => {
    if (mrId) {
        await fetch(`${baseUrl}/notifications/${id}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
        window.location.href = baseUrl + '/material-requests/' + mrId
    }
}

const goBack = () => router.get(route('dashboard'))
</script>

<template>
    <Head title="Notifikasi - SUKIRMAN" />
    <div class="layout">
        <var-app-bar title="Notifikasi" title-position="center">
            <template #left><var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button></template>
            <template #right>
                <var-button round text @click="() => { fetch(baseUrl + '/notifications/read-all', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }).then(() => window.location.reload()) }">
                    <var-icon name="check-all" :size="20" color="#4f46e5" />
                </var-button>
            </template>
        </var-app-bar>
        <main class="content">
            <div v-if="!notifications.length" class="empty">
                <var-icon name="bell-off-outline" :size="48" color="#cbd5e1" />
                <p>Tidak ada notifikasi</p>
            </div>
            <div v-for="n in notifications" :key="n.id" class="card" @click="markRead(n.id, n.mr_id)">
                <div class="card-body">
                    <p class="msg">{{ n.message }}</p>
                    <span class="time">{{ n.time }}</span>
                </div>
                <var-icon name="chevron-right" :size="20" color="#94a3b8" />
            </div>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;overflow:hidden; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px; }
.empty { display:flex;flex-direction:column;align-items:center;justify-content:center;margin-top:80px;color:#94a3b8; }
.empty p { margin-top:12px;font-size:14px; }
.card { display:flex;align-items:center;gap:12px;background:#fff;border-radius:16px;padding:14px 16px;border:1px solid #f1f5f9;margin-bottom:8px;cursor:pointer; }
.card-body { flex:1; }
.msg { margin:0;font-size:13px;color:#0f172a;line-height:1.4; }
.time { font-size:11px;color:#94a3b8; }
</style>
