<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { watch, onMounted, onBeforeUnmount } from 'vue'
import { initFirebase, enableNotifications, type FirebaseSharedConfig } from './firebase'

const page = usePage()
let gestureHandler: (() => void) | null = null

const tryInit = () => {
    const props = page.props as Record<string, any>
    const firebaseConfig = (props?.firebase_config || {}) as FirebaseSharedConfig
    firebaseConfig.csrf_token = props?.csrf_token || ''
    firebaseConfig.baseUrl = props?.app_url || ''

    console.log('[AppInit] URL:', window.location.href, '| firebase_config:', firebaseConfig.apiKey ? 'ADA' : 'KOSONG', '| auth.user:', props?.auth?.user ? 'ADA' : 'TIDAK ADA', '| baseUrl:', firebaseConfig.baseUrl)

    if (props?.auth?.user) {
        initFirebase(firebaseConfig)

        // Chrome butuh user gesture untuk requestPermission.
        // Attach sekali: saat user klik/tap di mana pun → minta izin notifikasi.
        if (!gestureHandler) {
            gestureHandler = () => enableNotifications()
            window.addEventListener('pointerdown', gestureHandler, { once: true })
        }
    }
}

onMounted(tryInit)
watch(() => page.props, tryInit, { deep: true })

onBeforeUnmount(() => {
    if (gestureHandler) {
        window.removeEventListener('pointerdown', gestureHandler)
        gestureHandler = null
    }
})
</script>
<template></template>