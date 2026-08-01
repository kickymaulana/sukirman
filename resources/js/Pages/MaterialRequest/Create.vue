<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'

interface RequestItem {
  item_code: string
  item_name: string
  specification: string
  qty: number
  unit: string
  item_status: 'Urgent' | 'Normal' | 'New' | 'Replace'
  monthly_usage: number
  stock_on_hand: number
  purpose: string
}

const props = defineProps<{ managers: { id: number; name: string; nik: string }[] }>()

const form = useForm({
  type: 'Lokal',
  factory: 'KIM',
  allocation: 'Proses',
  status_pembelian: 'Normal',
  manager_id: '',
  items: [
    {
      item_code: '',
      item_name: '',
      specification: '',
      qty: 1,
      unit: 'Pcs',
      item_status: 'Normal',
      monthly_usage: 0,
      stock_on_hand: 0,
      purpose: '',
    },
  ] as RequestItem[],
})

const typeOptions = ['Lokal', 'Import']
const factoryOptions = ['KIM', 'DALU 1', 'DALU 2']
const allocationOptions = ['Project', 'Proses']
const urgencyOptions = ['Normal', 'Urgent']
const itemStatusOptions = ['Normal', 'Urgent', 'New', 'Replace']

const addItem = () => {
  form.items.push({
    item_code: '',
    item_name: '',
    specification: '',
    qty: 1,
    unit: 'Pcs',
    item_status: 'Normal',
    monthly_usage: 0,
    stock_on_hand: 0,
    purpose: '',
  })
}

const removeItem = (index: number) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

const handleSubmit = () => {
  form.post(route('material-requests.store'))
}

const goBack = () => {
  router.get(route('material-requests.index'))
}
</script>

<template>
  <Head title="Buat Material Request - SUKIRMAN" />

  <div class="android-layout">
    <!-- AppBar dengan title di tengah dan tombol back di kiri -->
    <var-app-bar title="Buat Material Request (E-MR)" title-position="center">
      <template #left>
        <var-button round text @click="goBack">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>
    </var-app-bar>

    <!-- Main Content Form -->
    <main class="android-content">
      <var-form id="mrForm" @submit="handleSubmit">
        <var-space direction="column" :size="['16px', 0]">

          <!-- Section 1: Informasi Header MR -->
          <div class="form-card">
            <h3 class="card-section-title">Informasi Usulan</h3>

            <var-space direction="column" :size="['16px', 0]">
              <div class="field-group">
                <label class="field-label">Jenis Pembelian</label>
                <var-select v-model="form.type" variant="outlined" placeholder="Pilih Jenis">
                  <var-option v-for="opt in typeOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
              </div>

              <div class="field-group">
                <label class="field-label">Lokasi Pabrik</label>
                <var-select v-model="form.factory" variant="outlined" placeholder="Pilih Pabrik">
                  <var-option v-for="opt in factoryOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
              </div>

              <div class="field-group">
                <label class="field-label">Alokasi</label>
                <var-select v-model="form.allocation" variant="outlined" placeholder="Pilih Alokasi">
                  <var-option v-for="opt in allocationOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
              </div>

              <div class="field-group">
                <label class="field-label">Prioritas Dokumen</label>
                <var-select v-model="form.status_pembelian" variant="outlined" placeholder="Pilih Prioritas">
                  <var-option v-for="opt in urgencyOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
              </div>

              <div class="field-group">
                <label class="field-label">Manager Tujuan <span class="required">*</span></label>
                <var-select v-model="form.manager_id" variant="outlined" placeholder="Ketik & Pilih Manager" filterable :error-message="form.errors.manager_id">
                  <var-option v-for="m in managers" :key="m.id" :label="m.name + ' (' + m.nik + ')'" :value="m.id" />
                </var-select>
              </div>
            </var-space>
          </div>

          <!-- Section 2: List Item Barang -->
          <div class="section-header space-between">
            <h3 class="section-title">Daftar Barang ({{ form.items.length }})</h3>
            <var-button type="primary" size="small" @click="addItem">
              <var-icon name="plus" /> Tambah Item
            </var-button>
          </div>

          <div v-for="(item, index) in form.items" :key="index" class="item-card">
            <div class="item-card-header">
              <span class="item-index-badge">Item #{{ index + 1 }}</span>
              <var-button
                v-if="form.items.length > 1"
                text
                type="danger"
                size="small"
                @click="removeItem(index)"
                class="remove-item-btn"
              >
                <var-icon name="trash-can-outline" :size="18" />
              </var-button>
            </div>

            <var-space direction="column" :size="['12px', 0]">
              <var-input
                v-model="item.item_name"
                variant="outlined"
                placeholder="Nama Barang *"
                :error-message="form.errors[`items.${index}.item_name`]"
              />

              <div class="grid-2-col">
                <var-input
                  v-model="item.item_code"
                  variant="outlined"
                  placeholder="Kode Barang (Opsional)"
                />
                <var-select v-model="item.item_status" variant="outlined" placeholder="Status Item">
                  <var-option v-for="opt in itemStatusOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
              </div>

              <div class="grid-2-col">
                <var-input
                  v-model.number="item.qty"
                  type="number"
                  variant="outlined"
                  placeholder="Jumlah (Qty) *"
                  :error-message="form.errors[`items.${index}.qty`]"
                />
                <var-input
                  v-model="item.unit"
                  variant="outlined"
                  placeholder="Satuan (Pcs/Roll/dll) *"
                  :error-message="form.errors[`items.${index}.unit`]"
                />
              </div>

              <div class="grid-2-col">
                <var-input
                  v-model.number="item.monthly_usage"
                  type="number"
                  variant="outlined"
                  placeholder="Pemakaian / Bulan"
                  :min="0"
                />
                <var-input
                  v-model.number="item.stock_on_hand"
                  type="number"
                  variant="outlined"
                  placeholder="Stock On Hand"
                  :min="0"
                />
              </div>

              <var-input
                v-model="item.specification"
                variant="outlined"
                textarea
                rows="2"
                placeholder="Spesifikasi Teknik / Detail Merk"
              />

              <var-input
                v-model="item.purpose"
                variant="outlined"
                placeholder="Tujuan / Keperluan Barang"
              />
            </var-space>
          </div>

        </var-space>
      </var-form>
    </main>

    <!-- Fixed Action Bar di bagian bawah layar -->
    <div class="fixed-bottom-bar">
      <var-button
        block
        type="primary"
        size="large"
        form="mrForm"
        native-type="submit"
        :loading="form.processing"
        class="submit-btn"
      >
        KIRIM E-MR
      </var-button>
    </div>
  </div>
</template>

<style scoped>
.android-layout {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: #f8fafc;
  font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1e293b;
  overflow: hidden;
}

.android-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 90px 20px; /* Padding bottom 90px agar tidak terpotong oleh fixed-bottom-bar */
}

.form-card, .item-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.card-section-title, .section-title {
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 12px 0;
  color: #0f172a;
}

.section-header.space-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}

.item-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.item-index-badge {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-primary, #6200ee);
  background-color: #f3e8ff;
  padding: 4px 10px;
  border-radius: 20px;
}

.grid-2-col {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

/* Container tombol fixed di bawah */
.fixed-bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 20px;
  background-color: #ffffff;
  border-top: 1px solid #f1f5f9;
  box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
  z-index: 100;
}

.submit-btn {
  border-radius: 100px !important;
  font-weight: bold;
}
</style>
