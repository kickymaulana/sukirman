<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { Snackbar } from '@varlet/ui'

const props = defineProps<{ branch_code: string }>()

const form = useForm({
  branch_code: props.branch_code,
})

const save = () => {
  form.post(route('settings.update'), {
    onSuccess: () => Snackbar.success('Pengaturan berhasil disimpan'),
  })
}

const goBack = () => router.get(route('dashboard'))
</script>

<template>
  <Head title="Pengaturan - SUKIRMAN" />
  <div class="android-layout">
    <var-app-bar title="Pengaturan" title-position="center">
      <template #left>
        <var-button round text @click="goBack"><var-icon name="arrow-left" :size="24" /></var-button>
      </template>
    </var-app-bar>

    <main class="android-content">
      <div class="info-card">
        <var-icon name="information" :size="22" color="#1d6bf0" />
        <span>ID Cabang ini dipakai untuk keperluan export XML Accurate (PR / Permintaan Barang).</span>
      </div>

      <div class="form-card">
        <label class="field-label">ID Cabang (Branch Code)</label>
        <var-input
          v-model="form.branch_code"
          variant="outlined"
          placeholder="Contoh: 64260479"
          :error-message="form.errors.branch_code"
        />
        <p class="hint">Bisa berisi angka maupun teks. Contoh: <strong>64260479</strong></p>

        <var-button
          type="primary"
          block
          :loading="form.processing"
          @click="save"
          style="margin-top: 16px"
        >
          Simpan Pengaturan
        </var-button>
      </div>
    </main>
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
  padding: 16px 20px 80px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #eef4ff;
  border: 1px solid #c7d7ff;
  border-radius: 14px;
  padding: 14px;
  font-size: 13px;
  color: #1e40af;
  line-height: 1.5;
}

.form-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 18px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.field-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 8px;
}

.hint {
  font-size: 12px;
  color: #64748b;
  margin: 8px 0 0 0;
}
</style>
