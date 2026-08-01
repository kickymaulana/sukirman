<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

interface Item {
    id: number; item_name: string; specification: string | null; qty: number; unit: string; purpose: string | null
}

const props = defineProps<{ mr: any }>()
const page = usePage()
const pp = page.props as any
const baseUrl = pp.app_url || ''
const csrf = pp.csrf_token || ''

const mr = props.mr
const items = ref(mr.items.map((i: Item) => ({ id: i.id, item_name: i.item_name, specification: i.specification || '', qty: i.qty, unit: i.unit, decision: 'setuju', notes: '' })))

const decisions = [
    { value: 'setuju', label: '✅ Setuju' },
    { value: 'tolak', label: '❌ Tolak' },
    { value: 'ganti', label: '🔄 Ganti' },
]

const submitted = ref(false)
</script>

<template>
    <div class="layout">
        <Head :title="'Revisi ' + mr.mr_number" />
        <var-app-bar title="Revisi MR (Per Item)" title-position="center">
            <template #left><var-button round text @click="router.get(route('approval.direksi'))"><var-icon name="arrow-left" :size="24" /></var-button></template>
        </var-app-bar>
        <main class="content">
            <div class="info-card">
                <span class="mr-num">{{ mr.mr_number }}</span>
                <p><strong>{{ mr.user?.name }}</strong> • {{ mr.factory }} • {{ mr.items.length }} item</p>
                <p class="hint">Tentukan keputusan untuk setiap item. Item yang "Ganti" akan diperbaiki Supervisor, yang "Tolak" akan dihapus.</p>
            </div>

            <form :action="baseUrl + '/approval/direksi/' + mr.id + '/revision'" method="POST">
                <input type="hidden" name="_token" :value="csrf" />

                <div v-for="(item, i) in items" :key="item.id" class="item-card">
                    <input type="hidden" :name="'items[' + i + '][id]'" :value="item.id" />
                    <div class="item-header">
                        <span class="item-name">{{ item.item_name }}</span>
                        <select :name="'items[' + i + '][decision]'" v-model="items[i].decision" class="decision-select">
                            <option v-for="d in decisions" :key="d.value" :value="d.value">{{ d.label }}</option>
                        </select>
                    </div>
                    <div class="item-meta">{{ item.qty }} {{ item.unit }} <span v-if="item.specification">• {{ item.specification }}</span></div>
                    <div v-if="items[i].decision === 'tolak' || items[i].decision === 'ganti'" class="notes-row">
                        <textarea :name="'items[' + i + '][notes]'" v-model="items[i].notes" :placeholder="items[i].decision === 'ganti' ? 'Catatan untuk penggantian...' : 'Alasan ditolak...'" rows="2" class="notes-textarea"></textarea>
                    </div>
                </div>

                <button type="submit" class="submit-btn" :disabled="submitted">Terapkan Revisi</button>
            </form>
        </main>
    </div>
</template>

<style scoped>
.layout { display:flex;flex-direction:column;height:100vh;background:#f8fafc;font-family:Roboto,sans-serif; }
.content { flex:1;overflow-y:auto;padding:16px 20px 80px;display:flex;flex-direction:column;gap:12px; }
.info-card { background:#fff;border-radius:16px;padding:16px;border:1px solid #f1f5f9; }
.mr-num { font-family:monospace;font-weight:800;font-size:14px;color:#0f172a; }
p { margin:4px 0;font-size:13px;color:#475569; }
.hint { font-size:12px;color:#64748b; }
.item-card { background:#fff;border-radius:16px;padding:14px 16px;border:1px solid #f1f5f9; }
.item-header { display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px; }
.item-name { font-size:14px;font-weight:600;color:#0f172a; }
.decision-select { padding:6px 10px;border-radius:8px;border:1px solid #e2e8f0;font-size:13px;background:#fff;font-family:inherit; }
.item-meta { font-size:12px;color:#64748b;margin-bottom:6px; }
.notes-row { margin-top:6px; }
.notes-textarea { width:100%;padding:8px;border-radius:8px;border:1px solid #e2e8f0;font-size:13px;font-family:inherit;resize:vertical; }
.submit-btn { width:100%;padding:14px;border:none;border-radius:12px;background:#4f46e5;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit; }
.submit-btn:hover { background:#4338ca; }
.submit-btn:disabled { opacity:0.6;cursor:not-allowed; }
</style>
