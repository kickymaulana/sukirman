<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

const props = defineProps<{
    mr: any
    deptRole?: string | null
    deptApproved?: boolean
    deptApproverName?: string | null
    managerApproved?: boolean
    managerApproverName?: string | null
    fmGmApproved?: boolean
    fmGmApproverName?: string | null
    skipFmGm?: boolean
    direksiApproved?: boolean
    direksiApproverName?: string | null
}>()

const mr = props.mr

const actionLabel = (action: string) => {
  const map: any = {
    forward: 'Diteruskan',
    acknowledge: 'Acknowledge',
    approve: 'Disetujui',
    reject: 'Ditolak',
    revision: 'Revisi',
    stock_available: 'Stok Tersedia',
    stock_unavailable: 'Stok Tidak Ada',
    gudang_edit: 'Diedit Gudang',
    admin_edit: 'Diedit Admin',
  }
  return map[action] || action
}

const formatFull = (val: string) => {
  if (!val) return '-'
  return new Date(val).toLocaleString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const today = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })

const doPrint = () => window.print()
const goBack = () => window.history.back()
</script>

<template>
  <Head title="Cetak MR" />

  <div class="print-page">
    <div class="toolbar no-print">
      <button class="btn-back" @click="goBack">← Kembali</button>
      <button class="btn-print" @click="doPrint">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="document">
      <!-- Header -->
      <div class="doc-header">
        <div>
          <h1 class="company">PT. MARK DYNAMICS INDONESIA Tbk</h1>
          <p class="doc-title">MATERIAL REQUEST (PERMINTAAN BARANG)</p>
        </div>
        <div class="doc-no">
          <p>No: <strong>{{ mr.mr_number }}</strong></p>
          <p>Tanggal Cetak: {{ today }}</p>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Info -->
      <table class="info-table">
        <tr>
          <td class="lbl">Pengaju</td>
          <td><strong>{{ mr.user?.name }}</strong> ({{ mr.user?.nik || '-' }}){{ mr.user?.departemen?.nama ? ' — ' + mr.user.departemen.nama : '' }}</td>
          <td class="lbl">Pabrik</td>
          <td>{{ mr.factory }}</td>
        </tr>
        <tr>
          <td class="lbl">Jenis Pembelian</td>
          <td>{{ mr.type }}</td>
          <td class="lbl">Jenis MR</td>
          <td>{{ mr.jenis || 'UMUM' }}</td>
        </tr>
        <tr>
          <td class="lbl">Urgensi</td>
          <td>{{ mr.status_pembelian }}</td>
          <td class="lbl">Status</td>
          <td><strong>{{ mr.status_workflow }}</strong></td>
        </tr>
        <tr>
          <td class="lbl">Tanggal Pengajuan</td>
          <td>{{ formatFull(mr.created_at) }}</td>
          <td class="lbl">Jumlah Item</td>
          <td>{{ mr.items?.length || 0 }} item</td>
        </tr>
      </table>

      <!-- Items -->
      <h3 class="section-title">Daftar Barang</h3>
      <table class="items-table">
        <thead>
          <tr>
            <th class="no-col">No</th>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Spesifikasi</th>
            <th>Departemen</th>
            <th class="qty-col">Qty</th>
            <th class="unit-col">Satuan</th>
            <th>Keperluan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, i) in mr.items" :key="it.id">
            <td class="center">{{ i + 1 }}</td>
            <td>{{ it.item_code || '-' }}</td>
            <td>{{ it.item_name }}</td>
            <td>{{ it.specification || '-' }}</td>
            <td>{{ it.departemen?.nama || '-' }}</td>
            <td class="center">{{ it.qty }}</td>
            <td class="center">{{ it.unit }}</td>
            <td>{{ it.purpose || '-' }}</td>
          </tr>
          <tr v-if="!mr.items?.length">
            <td colspan="8" class="center empty">Tidak ada item</td>
          </tr>
        </tbody>
      </table>

      <!-- Riwayat -->
      <h3 class="section-title">Riwayat Persetujuan</h3>
      <table class="items-table">
        <thead>
          <tr>
            <th class="no-col">No</th>
            <th>Level</th>
            <th>Tindakan</th>
            <th>Oleh</th>
            <th>Waktu</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(lg, i) in mr.approval_logs" :key="lg.id">
            <td class="center">{{ i + 1 }}</td>
            <td>{{ lg.role }}</td>
            <td>{{ actionLabel(lg.action) }}</td>
            <td>{{ lg.user?.name || '-' }}</td>
            <td>{{ formatFull(lg.created_at) }}</td>
          </tr>
          <tr v-if="!mr.approval_logs?.length">
            <td colspan="5" class="center empty">Belum ada riwayat</td>
          </tr>
        </tbody>
      </table>

      <!-- Tanda tangan approver (stempel elektronik) -->
      <div class="signatures">
        <div class="sign-box">
          <p class="sign-role">Manager</p>
          <div v-if="managerApproved" class="approved-stamp"><span class="stamp-txt">✓ Approved</span></div>
          <p class="sign-name">{{ managerApproverName || mr.manager?.name || '( belum ditandatangani )' }}</p>
        </div>
        <div v-if="deptRole" class="sign-box">
          <p class="sign-role">{{ deptRole }}</p>
          <div v-if="deptApproved" class="approved-stamp"><span class="stamp-txt">✓ Approved</span></div>
          <p class="sign-name">{{ deptApproverName || '( belum ditandatangani )' }}</p>
        </div>
        <div v-if="!skipFmGm" class="sign-box">
          <p class="sign-role">FM/GM</p>
          <div v-if="fmGmApproved" class="approved-stamp"><span class="stamp-txt">✓ Approved</span></div>
          <p class="sign-name">{{ fmGmApproverName || mr.fm_gm?.name || '( belum ditandatangani )' }}</p>
        </div>
        <div class="sign-box">
          <p class="sign-role">Direksi</p>
          <div v-if="direksiApproved" class="approved-stamp"><span class="stamp-txt">✓ Approved</span></div>
          <p class="sign-name">{{ direksiApproverName || mr.direksi?.name || '( belum ditandatangani )' }}</p>
        </div>
      </div>

      <p class="footer-note">Dokumen ini dihasilkan otomatis oleh sistem SUKIRMAN. Riwayat persetujuan tercatat di sistem sebagai bukti.</p>
    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; }
body { margin: 0; background: #e2e8f0; }
</style>

<style scoped>
.print-page { min-height: 100vh; background: #e2e8f0; padding: 20px; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }

.toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; justify-content: space-between; gap: 10px; }
.btn-back, .btn-print { padding: 10px 18px; border-radius: 10px; border: none; font-weight: 700; font-size: 14px; cursor: pointer; font-family: inherit; }
.btn-back { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
.btn-print { background: #4f46e5; color: #fff; }

.document { max-width: 800px; margin: 0 auto; background: #fff; padding: 32px 36px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

.doc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.company { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: 0.5px; }
.doc-title { margin: 4px 0 0; font-size: 13px; font-weight: 700; color: #4f46e5; }
.doc-no { text-align: right; font-size: 12px; color: #334155; }
.doc-no p { margin: 2px 0; }

.divider { border-top: 2px solid #0f172a; margin: 16px 0; }

.info-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.info-table td { padding: 5px 6px; vertical-align: top; }
.info-table .lbl { font-weight: 700; color: #64748b; width: 130px; }

.section-title { margin: 22px 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }

.items-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.items-table th { background: #f1f5f9; color: #334155; font-weight: 700; padding: 7px 8px; border: 1px solid #e2e8f0; text-align: left; }
.items-table td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
.no-col { width: 34px; } .qty-col { width: 50px; } .unit-col { width: 60px; }
.center { text-align: center; }
.empty { color: #94a3b8; font-style: italic; }
.item-dept { font-weight:400;color:#64748b;font-size:10px; }

.signatures { display: flex; justify-content: space-between; gap: 16px; margin-top: 46px; }
.sign-box { flex: 1; text-align: center; }
.sign-role { font-size: 11px; font-weight: 700; color: #64748b; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 1px; }
.sign-name { font-size: 12px; font-weight: 700; color: #0f172a; margin: 14px 0 0; padding-top: 8px; border-top: 1px solid #e2e8f0; }
.approved-stamp {
  display: inline-block;
  margin: 6px auto;
  padding: 6px 16px;
  border: 2px solid #10b981;
  border-radius: 8px;
  color: #059669;
  background: rgba(16, 185, 129, 0.08);
  transform: rotate(-8deg);
  font-style: italic;
}
.stamp-txt { font-size: 13px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; }

.footer-note { margin-top: 28px; font-size: 10px; color: #94a3b8; text-align: center; }

@media print {
  .no-print { display: none !important; }
  .print-page { background: #fff; padding: 0; }
  .document { box-shadow: none; padding: 20px; border-radius: 0; max-width: 100%; }
}
</style>