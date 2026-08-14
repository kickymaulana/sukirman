<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

interface RequestItem {
  item_code: string
  item_name: string
  specification: string
  departemen_id: string
  qty: number
  unit: string
  item_status: 'Urgent' | 'Normal' | 'New' | 'Replace'
  monthly_usage: number
  stock_on_hand: number
  purpose: string
  foto: File | null
}

const props = defineProps<{
  mr: {
    id: number
    mr_number: string
    type: string
    factory: string
    allocation: string
    status_pembelian: string
    jenis: string
    manager_id: number
    items: {
      id: number
      item_code: string | null
      item_name: string
      specification: string | null
      departemen_id: number | null
      qty: number
      unit: string
      item_status: string
      monthly_usage: number
      stock_on_hand: number
      purpose: string | null
      foto: string | null
    }[]
  }
  managers: { id: number; name: string; nik: string }[]
  departemens?: { id: number; nama: string }[]
}>()
const page = usePage()
const baseUrl = (page.props as any).app_url || ''

const suggestions = ref<Record<number, { kode_barang: string; nama_barang: string }[]>>({})
const openName = ref<Record<number, boolean>>({})
const openCode = ref<Record<number, boolean>>({})
let searchTimer: any = null

const searchBarang = (index: number, q: string) => {
  if (q.trim().length < 2) { suggestions.value[index] = []; return }
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`${baseUrl}/barangs/search-api?q=${encodeURIComponent(q)}`)
      suggestions.value[index] = await res.json()
    } catch { suggestions.value[index] = [] }
  }, 300)
}

const onName = (index: number, v: any) => {
  form.items[index].item_name = String(v).toUpperCase()
  openName.value[index] = true
  openCode.value[index] = false
  searchBarang(index, String(v))
}

const onCode = (index: number, v: any) => {
  form.items[index].item_code = String(v).toUpperCase()
  openName.value[index] = false
  openCode.value[index] = true
  searchBarang(index, String(v))
}

const selectBarang = (index: number, b: { kode_barang: string; nama_barang: string }) => {
  form.items[index].item_code = b.kode_barang
  form.items[index].item_name = b.nama_barang
  suggestions.value[index] = []
  openName.value[index] = false
  openCode.value[index] = false
}

const hideSug = (index: number) => {
  nextTick(() => setTimeout(() => { openName.value[index] = false; openCode.value[index] = false }, 150))
}

const form = useForm({
  type: props.mr.type,
  factory: props.mr.factory,
  allocation: props.mr.allocation,
  status_pembelian: props.mr.status_pembelian,
  jenis: props.mr.jenis,
  manager_id: props.mr.manager_id,
  items: props.mr.items.map((item) => ({
    item_code: item.item_code || '',
    item_name: item.item_name,
    specification: item.specification || '',
    departemen_id: item.departemen_id ? String(item.departemen_id) : '',
    qty: item.qty,
    unit: item.unit,
    item_status: item.item_status,
    monthly_usage: item.monthly_usage,
    stock_on_hand: item.stock_on_hand,
    purpose: item.purpose || '',
    foto: null,
    _existing_foto: item.foto,
  })) as RequestItem[],
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
    departemen_id: '',
    qty: 1,
    unit: 'Pcs',
    item_status: 'Normal',
    monthly_usage: 0,
    stock_on_hand: 0,
    purpose: '',
    foto: null,
    _existing_foto: null,
  })
}

const previews = ref<(string | null)[]>(props.mr.items.map(i => i.foto ? `/item-foto/${i.id}` : null))
const fotoLoading = ref<number[]>([])

const compressImage = (file: File, maxSize = 1200, quality = 0.75): Promise<File> => {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      const scale = Math.min(1, maxSize / Math.max(img.width, img.height))
      const w = Math.round(img.width * scale)
      const h = Math.round(img.height * scale)
      const canvas = document.createElement('canvas')
      canvas.width = w
      canvas.height = h
      const ctx = canvas.getContext('2d')
      if (!ctx) { URL.revokeObjectURL(url); reject(new Error('no ctx')); return }
      ctx.drawImage(img, 0, 0, w, h)
      URL.revokeObjectURL(url)
      canvas.toBlob((blob) => {
        if (!blob) { reject(new Error('compress fail')); return }
        resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }))
      }, 'image/jpeg', quality)
    }
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('load fail')) }
    img.src = url
  })
}

const handleFoto = async (e: Event, index: number) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (!/^image\//.test(file.type)) { Snackbar.warning('File harus berupa gambar'); input.value = ''; return }
  fotoLoading.value.push(index)
  try {
    const compressed = await compressImage(file, 1200, 0.75)
    form.items[index].foto = compressed
    if (previews.value[index]) URL.revokeObjectURL(previews.value[index])
    previews.value[index] = URL.createObjectURL(compressed)
  } catch {
    Snackbar.error('Gagal memproses gambar')
  } finally {
    fotoLoading.value = fotoLoading.value.filter(i => i !== index)
    input.value = ''
  }
}

const removeFoto = (index: number) => {
  form.items[index].foto = null
  if (previews.value[index]) {
    URL.revokeObjectURL(previews.value[index])
    previews.value[index] = null
  }
}

const removeItem = (index: number) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
    if (previews.value[index]) {
      URL.revokeObjectURL(previews.value[index])
    }
    previews.value.splice(index, 1)
  }
}

const handleSubmit = () => {
  form.put(route('material-requests.update', props.mr.id), {
    onError: (errors) => {
      const first = Object.values(errors)[0]
      if (first) Snackbar.error(first)
    },
  })
}

const goBack = () => {
  router.get(route('material-requests.show', props.mr.id))
}
</script>
<template>
  <Head :title="'Edit Material Request ' + mr.mr_number + ' - SUKIRMAN'" />

  <div class="android-layout">
    <var-app-bar :title="'Edit ' + mr.mr_number" title-position="center">
      <template #left>
        <var-button round text @click="goBack">
          <var-icon name="arrow-left" :size="24" />
        </var-button>
      </template>
    </var-app-bar>

    <main class="android-content">
      <var-form id="mrForm" @submit="handleSubmit">
        <var-space direction="column" :size="['16px', 0]">

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
                  placeholder="Nama Barang *"
                  :error-message="form.errors[`items.${index}.item_name`]"
                  @update:modelValue="onName(index, $event)"
                  @blur="hideSug(index)"
                />
                <div v-if="openName[index] && suggestions[index]?.length" class="suggestions-box">
                  <div v-for="(s, si) in suggestions[index]" :key="si" class="suggestion-item" @mousedown.prevent="selectBarang(index, s)">
                    <span class="sug-kode">{{ s.kode_barang }}</span>
                    <span class="sug-nama">{{ s.nama_barang }}</span>
                  </div>
                </div>
              </div>

              <div class="grid-2-col">
                <div class="autocomplete-wrap">
                  <var-input
                    v-model="item.item_code"
                    variant="outlined"
                    placeholder="Kode Barang (auto-saran)"
                    @update:modelValue="onCode(index, $event)"
                    @blur="hideSug(index)"
                  />
                  <div v-if="openCode[index] && suggestions[index]?.length" class="suggestions-box">
                    <div v-for="(s, si) in suggestions[index]" :key="si" class="suggestion-item" @mousedown.prevent="selectBarang(index, s)">
                      <span class="sug-kode">{{ s.kode_barang }}</span>
                      <span class="sug-nama">{{ s.nama_barang }}</span>
                    </div>
                  </div>
                </div>
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
                  @update:modelValue="(v: any) => item.unit = String(v).toUpperCase()"
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
                @update:modelValue="(v: any) => item.specification = String(v).toUpperCase()"
              />

              <var-input
                v-model="item.purpose"
                variant="outlined"
                placeholder="Tujuan / Keperluan Barang"
                @update:modelValue="(v: any) => item.purpose = String(v).toUpperCase()"
              />

              <div class="field-group">
                <label class="field-label">Departemen Item <span class="opt-label">(opsional)</span></label>
                <var-select
                  v-model="item.departemen_id"
                  filterable
                  clearable
                  variant="outlined"
                  placeholder="Pilih departemen tujuan item ini..."
                >
                  <var-option v-for="d in props.departemens || []" :key="d.id" :label="d.nama" :value="String(d.id)" />
                </var-select>
              </div>

              <div class="field-group">
                <label class="field-label">Foto Barang <span class="opt-label">(opsional)</span></label>
                <div class="foto-row">
                  <label class="foto-btn">
                    <var-icon name="camera" :size="18" />
                    {{ fotoLoading.includes(index) ? 'Memproses...' : (item.foto ? 'Ganti Foto' : (item._existing_foto ? 'Ganti Foto' : 'Ambil Foto')) }}
                    <input type="file" accept="image/*" style="display:none" @change="handleFoto($event, index)" />
                  </label>
                  <div v-if="previews[index]" class="foto-preview">
                    <img :src="previews[index]" class="foto-img" alt="preview" />
                    <button class="foto-remove" @click="removeFoto(index)">X</button>
                  </div>
                </div>
              </div>
            </var-space>
          </div>

        </var-space>
      </var-form>
    </main>

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
        SIMPAN PERUBAHAN
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
  padding: 16px 20px 90px 20px;
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

.field-hint {
  font-size: 11px;
  color: #94a3b8;
  margin: 4px 0 0;
}
.opt-label {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 400;
}

.foto-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.foto-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border: 1px dashed #a5b4fc;
  border-radius: 10px;
  background: #eef2ff;
  color: #4f46e5;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.foto-btn:hover { background: #e0e7ff; }
.foto-preview { position: relative; }
.foto-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  display: block;
}
.foto-remove {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: #ef4444;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
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

.autocomplete-wrap { position: relative; }
.suggestions-box { position: absolute;top:100%;left:0;right:0;z-index:100;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.12);margin-top:4px; }
.suggestion-item { display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9; }
.suggestion-item:last-child { border-bottom:none; }
.suggestion-item:hover, .suggestion-item:active { background:#f0f5ff; }
.sug-kode { flex-shrink:0;font-size:12px;font-weight:700;color:#1d6bf0;background:#eef4ff;padding:2px 8px;border-radius:6px;max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.sug-nama { font-size:13px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

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