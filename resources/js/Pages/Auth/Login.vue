<script setup lang="ts">
import { usePage, Head } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Snackbar } from '@varlet/ui'
import lottie from 'lottie-web'

const page = usePage()
const errors = (page.props as any)?.errors
const flashSuccess = (page.props as any)?.flash?.success
const ssoUrl = (page.props as any).app_url + '/auth/sso'

const lottieContainer = ref<HTMLElement | null>(null)
let lottieInstance: any = null

onMounted(() => {
  if (lottieContainer.value) {
    const baseUrl = (page.props.app_url as string) || window.location.origin
    const cleanBaseUrl = baseUrl.replace(/\/$/, '')
    lottieInstance = lottie.loadAnimation({
      container: lottieContainer.value,
      renderer: 'svg',
      loop: true,
      autoplay: true,
      path: `${cleanBaseUrl}/assets/lottie/purple_check.json`
    })
  }
  if (errors?.message) Snackbar.error(errors.message)
  if (flashSuccess) Snackbar.success(flashSuccess)
})

onBeforeUnmount(() => {
  if (lottieInstance) lottieInstance.destroy()
})
</script>

<template>
  <Head title="SUKIRMAN - Masuk" />

  <div class="android-layout">
    <div class="android-content">
      <div class="brand-section">
        <div class="logo-avatar">
          <div ref="lottieContainer" class="lottie-box"></div>
        </div>
        <h1 class="app-title">SUKIRMAN</h1>
        <p class="app-subtitle">Sistem Usulan Kebutuhan Barang Internal Manajemen</p>
      </div>

      <div class="benefits-box">
        <div class="benefit">✅ Ajukan kebutuhan barang</div>
        <div class="benefit">✅ Pantau status approval real-time</div>
        <div class="benefit">✅ Proses cepat tanpa kertas</div>
      </div>

      <a :href="ssoUrl" class="sso-btn">
        <var-icon name="shield-account" :size="22" />
        Masuk dengan SSO Perusahaan
      </a>

      <p class="sso-info">
        Gunakan NIK & Password SSO yang terdaftar
      </p>
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
  background: #f8fafc;
  font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #1e293b;
}

.android-content {
  flex: 1;
  padding: 40px 24px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.brand-section {
  text-align: center;
  margin-bottom: 24px;
}

.logo-avatar {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
}

.lottie-box {
  width: 160px;
  height: 160px;
}

.app-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 1px;
  margin: 0 0 6px 0;
  color: #4f46e5;
}

.app-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0 auto;
  max-width: 280px;
  line-height: 1.4;
}

.benefits-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 16px 20px;
  width: 100%;
  max-width: 340px;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.benefit {
  font-size: 13px;
  color: #475569;
}

.sso-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  max-width: 340px;
  padding: 16px 24px;
  background: linear-gradient(135deg, #4f46e5, #6366f1);
  border: none;
  border-radius: 100px;
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
  box-shadow: 0 4px 15px rgba(79,70,229,0.25);
  margin-bottom: 16px;
}

.sso-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(79,70,229,0.35);
}

.sso-info {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
  text-align: center;
}

.android-footer {
  padding: 16px;
  text-align: center;
}

.android-footer p {
  margin: 0;
  font-size: 11px;
  color: #94a3b8;
  letter-spacing: 0.5px;
}
</style>
