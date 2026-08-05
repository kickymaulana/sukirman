<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

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
const pp = usePage().props as any
const baseUrl = pp.app_url || ''

// Autocomplete barang
const suggestions = ref<Record<number, { kode_barang: string; nama_barang: string }[]>>({})
const showSuggestions = ref<Record<number, boolean>>({})
let searchTimer: any = null

const onNamaInput = (index: number, query: string) => {
  if (query.trim().length < 2) {
    suggestions.value[index] = []
    showSuggestions.value[index] = false
    return
  }
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`${baseUrl}/barangs/search-api?q=${encodeURIComponent(query)}`)
      const data = await res.json()
      suggestions.value[index] = data
      showSuggestions.value[index] = data.length > 0
    } catch { suggestions.value[index] = [] }
  }, 300)
}

const selectBarang = (index: number, b: { kode_barang: string; nama_barang: string }) => {
  form.items[index].item_code = b.kode_barang
  form.items[index].item_name = b.nama_barang
  suggestions.value[index] = []
  showSuggestions.value[index] = false
}

const hideSuggestions = (index: number) => {
  nextTick(() => { setTimeout(() => { showSuggestions.value[index] = false }, 150) })
}

const form = useForm({
  type: 'Lokal',
  factory: 'KIM',
  allocation: 'Proses',
  status_pembelian: 'Normal',
  jenis: 'UMUM',
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
const jenisOptions = ['UMUM', 'MTC', 'IT', 'HRD']
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
  form.post(route('material-requests.store'), {
    onError: (errors) => {
      const first = Object.values(errors)[0]
      if (first) Snackbar.error(first)
    },
  })
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
                <label class="field-label">Jenis MR <span class="required">*</span></label>
                <var-select v-model="form.jenis" variant="outlined" placeholder="Pilih Jenis MR" :error-message="form.errors.jenis">
                  <var-option v-for="opt in jenisOptions" :key="opt" :label="opt" :value="opt" />
                </var-select>
                <p class="field-hint">UMUM = tanpa persetujuan departemen. MTC/IT/HRD = wajib persetujuan departemen terkait.</p>
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
              <div class="autocomplete-wrap">
                <var-input
                  v-model="item.item_name"
                  variant="outlined"
                  placeholder="Ketik Nama Barang (auto-saran)..."
                  :error-message="form.errors[`items.${index}.item_name`]"
                  @update:modelValue="onNamaInput(index, $event)"
                  @blur="hideSuggestions(index)"
                />
                <div v-if="showSuggestions[index] && suggestions[index]?.length" class="suggestions-box">
                  <div
                    v-for="(s, si) in suggestions[index]"
                    :key="si"
                    class="suggestion-item"
                    @mousedown.prevent="selectBarang(index, s)"
                  >
                    <span class="sug-kode">{{ s.kode_barang }}</span>
                    <span class="sug-nama">{{ s.nama_barang }}</span>
                  </div>
                </div>
              </div>

              <div class="grid-2-col">
                <var-input
                  v-model="item.item_code"
                  variant="outlined"
                  placeholder="Kode Barang (bisa diketik manual)"
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

.autocomplete-wrap {
  position: relative;
}

.suggestions-box {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 100;
  max-height: 220px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  margin-top: 4px;
}

.suggestion-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item:hover, .suggestion-item:active {
  background: #f0f5ff;
}

.sug-kode {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 700;
  color: #1d6bf0;
  background: #eef4ff;
  padding: 2px 8px;
  border-radius: 6px;
  max-width: 45%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sug-nama {
  font-size: 13px;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.field-hint {
  font-size: 11px;
  color: #94a3b8;
  margin: 4px 0 0;
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
