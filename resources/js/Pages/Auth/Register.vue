<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import type { Form } from '@varlet/ui'
import { Snackbar } from '@varlet/ui'

type FormInstance = InstanceType<typeof Form>

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const registerForm = ref<FormInstance | null>(null)

const handleSubmit = (valid: boolean | null) => {
  if (valid) {
    form.post(route('register'), {
      onError: (errors) => {
        const firstError = Object.values(errors)[0]
        if (firstError) {
          Snackbar.error(firstError)
        }
      },
      onFinish: () => {
        form.reset('password', 'password_confirmation')
      },
    })
  }
}
</script>

<template>
  <div class="android-layout">
    <div class="android-content">
      <div class="brand-section">
        <h1 class="app-title">BUAT AKUN</h1>
        <p class="app-subtitle">Daftarkan diri Anda untuk mengakses sistem SUKIRMAN</p>
      </div>

      <var-form ref="registerForm" @submit="handleSubmit">
        <var-space direction="column" :size="['20px', 0]">

          <!-- Nama Lengkap -->
          <var-input
            v-model="form.name"
            variant="outlined"
            type="text"
            placeholder="Nama Lengkap"
            :disabled="form.processing"
            :error-message="form.errors.name"
            :rules="[(v) => !!v || 'Nama lengkap tidak boleh kosong']"
            clearable
            @input="form.clearErrors('name')"
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="account" />
            </template>
          </var-input>

          <!-- Email -->
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
            @input="form.clearErrors('email')"
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="email" />
            </template>
          </var-input>

          <!-- Password -->
          <var-input
            v-model="form.password"
            variant="outlined"
            type="password"
            placeholder="Password (Min. 8 Karakter)"
            :disabled="form.processing"
            :error-message="form.errors.password"
            :rules="[
              (v) => !!v || 'Password tidak boleh kosong',
              (v) => (v && v.length >= 8) || 'Password minimal 8 karakter'
            ]"
            clearable
            @input="form.clearErrors('password')"
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="lock" />
            </template>
          </var-input>

          <!-- Konfirmasi Password -->
          <var-input
            v-model="form.password_confirmation"
            variant="outlined"
            type="password"
            placeholder="Konfirmasi Password"
            :disabled="form.processing"
            :error-message="form.errors.password_confirmation"
            :rules="[
              (v) => !!v || 'Konfirmasi password tidak boleh kosong',
              (v) => v === form.password || 'Konfirmasi password tidak sesuai'
            ]"
            clearable
            @input="form.clearErrors('password_confirmation')"
          >
            <template #prepend-icon>
              <var-icon class="input-icon" name="checkbox-marked-circle-outline" />
            </template>
          </var-input>

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
              DAFTAR SEKARANG
            </var-button>
          </div>

          <div class="login-link-container">
            <span>Sudah memiliki akun? </span>
            <Link :href="route('login')" class="login-link">Masuk di sini</Link>
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
  --field-decorator-line-border-radius: 100px;
}

.android-content {
  flex: 1;
  padding: 40px 24px 24px 24px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

.brand-section {
  text-align: center;
  margin-bottom: 28px;
}

.app-title {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin: 0 0 6px 0;
  color: var(--color-primary);
}

.app-subtitle {
  font-size: 13px;
  color: #757575;
  margin: 0 auto;
  max-width: 280px;
  line-height: 1.4;
}

.input-icon {
  margin-left: 6px;
  margin-right: 6px;
  color: #616161;
}

:deep(.var-field-decorator__outlined) {
  padding-left: 20px !important;
  padding-right: 20px !important;
}

:deep(.var-field-decorator__controller) {
  min-height: 44px !important;
}

.action-section {
  margin-top: 12px;
}

.submit-btn {
  border-radius: 100px !important;
  font-weight: bold;
  height: 44px;
}

.login-link-container {
  text-align: center;
  font-size: 13px;
  color: #616161;
  margin-top: 8px;
}

.login-link {
  color: var(--color-primary);
  font-weight: 700;
  text-decoration: none;
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
