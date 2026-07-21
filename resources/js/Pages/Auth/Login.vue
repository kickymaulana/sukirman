<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import type { Form } from '@varlet/ui'
import lottie from 'lottie-web'

// 1. Definisikan Tipe dan State Hooks Pendukung
type FormInstance = InstanceType<typeof Form>
const page = usePage()

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const loginForm = ref<FormInstance | null>(null)

// Ref untuk elemen HTML wadah animasi Lottie
const lottieContainer = ref<HTMLElement | null>(null)
let lottieInstance: any = null

// 2. Lifecycle Hooks
onMounted(() => {
  if (lottieContainer.value) {
    const sharedUrl = page.props.app_url as string | undefined
    const baseUrl = sharedUrl || (window.location.origin + '/sukirman/public/')

    lottieInstance = lottie.loadAnimation({
      container: lottieContainer.value,
      renderer: 'svg',
      loop: true,
      autoplay: true,
      path: baseUrl.endsWith('/')
        ? `${baseUrl}assets/lottie/purple_check.json`
        : `${baseUrl}/assets/lottie/purple_check.json`
    })
  }
})

onBeforeUnmount(() => {
  if (lottieInstance) {
    lottieInstance.destroy()
  }
})

// 3. Form Submission Handler
const handleSubmit = (valid: boolean | null) => {
  if (valid) {
    form.post('/login', {
      onFinish: () => {
        form.reset('password')
      },
    })
  }
}
</script>

<template>
  <div class="android-layout">
    <div class="android-content">
      <div class="brand-section">
        <div class="logo-avatar">
          <div ref="lottieContainer" class="lottie-box"></div>
        </div>
        <h1 class="app-title">SUKIRMAN</h1>
        <p class="app-subtitle">Sistem Usulan Kebutuhan Barang Internal Manajemen</p>
      </div>

      <var-form ref="loginForm" @submit="handleSubmit">
        <var-space direction="column" :size="['24px', 0]">

          <var-input
            v-model="form.email"
            variant="outlined"
            type="text"
            placeholder="Alamat Email"
            :disabled="form.processing"
            :error-message="form.errors.email"
            :rules="[
              (v) => !!v || 'Email tidak boleh kosong',
              (v) => /.+@.+\..+/.test(v) || 'Format email harus valid'
            ]"
            clearable
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="email" />
            </template>
          </var-input>

          <var-input
            v-model="form.password"
            variant="outlined"
            type="password"
            placeholder="Password"
            :disabled="form.processing"
            :error-message="form.errors.password"
            :rules="[(v) => !!v || 'Password tidak boleh kosong']"
            clearable
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="lock" />
            </template>
          </var-input>

          <div class="remember-me-row">
            <var-checkbox v-model="form.remember" :disabled="form.processing">
              <span class="remember-text">Ingat akun saya di perangkat ini</span>
            </var-checkbox>
          </div>

          <div class="action-section">
            <var-button
              block
              type="primary"
              native-type="submit"
              size="large"
              :elevation="2"
              :loading="form.processing"
              loading-type="wave"
              class="submit-btn"
            >
              MASUK
            </var-button>
          </div>

        </var-space>
      </var-form>
    </div>

    <div class="android-footer">
      <p>© 2026 IT Department • v2.0.0</p>
    </div>
  </div>
</template>

<style scoped>
.android-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: #fafafa;
  font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #212121;

  /* Menyuntikkan kelengkungan bulat penuh ke semua komponen input di dalamnya */
  --field-decorator-line-border-radius: 100px;
}

.android-content {
  flex: 1;
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

.brand-section {
  text-align: center;
  margin-top: 8px;
  margin-bottom: 24px;
}

.logo-avatar {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
  margin-bottom: -12px;
}

.lottie-box {
  width: 180px;
  height: 180px;
}

.app-title {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin: 0 0 4px 0;
  color: var(--color-primary);
  position: relative;
}

.app-subtitle {
  font-size: 13px;
  color: #757575;
  margin: 0 auto;
  max-width: 280px;
  line-height: 1.4;
}

/* Penyesuaian jarak Ikon agar seimbang di dalam input kapsul */
.input-icon {
  margin-left: 6px;
  margin-right: 6px;
  color: #616161;
}

/*
  PERBAIKAN UTAMA:
  Menyeimbangkan padding horizontal pembungkus input Varlet
  agar lebarnya sejajar sempurna dengan tombol block.
*/
:deep(.var-field-decorator__outlined) {
  padding-left: 20px !important;
  padding-right: 20px !important;
}

/*
  Memaksa tinggi garis input agar sepadan dengan
  tinggi komponen tombol berskala besar (size="large").
*/
:deep(.var-field-decorator__controller) {
  min-height: 44px !important;
}

.remember-me-row {
  margin-top: -8px;
  padding-left: 2px;
}

.remember-text {
  font-size: 14px;
  color: #424242;
}

.action-section {
  margin-top: 16px;
}

.submit-btn {
  border-radius: 100px !important;
  font-weight: bold;
  height: 44px; /* Disamakan dengan tinggi controller input */
}

.android-footer {
  padding: 16px;
  text-align: center;
}

.android-footer p {
  margin: 0;
  font-size: 11px;
  color: #9e9e9e;
  letter-spacing: 0.5px;
}
</style>
