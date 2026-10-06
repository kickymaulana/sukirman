<script setup lang="ts">
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

onMounted(() => window.print())

interface RequestRow {
    mr_number: string
    pengaju: string | null
    departemen: string | null
    factory: string
    type: string
    items_count: number
    created_at: string | null
    direksi: string | null
    direksi_approved_at: string | null
}

defineProps<{
    requests: RequestRow[]
    printedAt: string
}>()
</script>

<template>
    <Head title="Print MR Purchasing - SUKIRMAN" />
    <main class="print-page">
        <header>
            <h1>Daftar MR Belum PO</h1>
            <p>Dicetak: {{ printedAt }}</p>
        </header>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>No. MR</th>
                    <th>Pengaju</th>
                    <th>Departemen</th>
                    <th>Factory</th>
                    <th>Tipe</th>
                    <th>Jumlah Item</th>
                    <th>Tanggal Dibuat</th>
                    <th>Direksi</th>
                    <th>Disetujui Direksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(mr, index) in requests" :key="mr.mr_number">
                    <td>{{ index + 1 }}</td>
                    <td>{{ mr.mr_number }}</td>
                    <td>{{ mr.pengaju || '-' }}</td>
                    <td>{{ mr.departemen || '-' }}</td>
                    <td>{{ mr.factory }}</td>
                    <td>{{ mr.type }}</td>
                    <td>{{ mr.items_count }} item</td>
                    <td>{{ mr.created_at || '-' }}</td>
                    <td>{{ mr.direksi || '-' }}</td>
                    <td>{{ mr.direksi_approved_at || '-' }}</td>
                </tr>
                <tr v-if="!requests.length">
                    <td colspan="10" class="empty">Tidak ada MR</td>
                </tr>
            </tbody>
        </table>
    </main>
</template>

<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
.print-page { padding: 24px; }
h1 { margin: 0 0 6px; font-size: 20px; }
p { margin: 0 0 18px; font-size: 12px; color: #4b5563; }
table { width: 100%; border-collapse: collapse; font-size: 11px; }
th, td { border: 1px solid #9ca3af; padding: 7px 8px; text-align: left; }
th { background: #e5e7eb; font-weight: 700; }
.empty { padding: 24px; text-align: center; }
@media print {
    @page { size: A4 landscape; margin: 12mm; }
    .print-page { padding: 0; }
}
</style>
