import { initializeApp } from 'firebase/app'
import { getMessaging, getToken, onMessage } from 'firebase/messaging'

export interface FirebaseSharedConfig {
    apiKey?: string
    authDomain?: string
    projectId?: string
    storageBucket?: string
    messagingSenderId?: string
    appId?: string
    measurementId?: string
    vapidKey?: string
    csrf_token?: string
    baseUrl?: string
}

let initialized = false
let messaging: ReturnType<typeof getMessaging> | null = null
let registration: ServiceWorkerRegistration | null = null
let config: FirebaseSharedConfig = {}
let permissionRequested = false

const isSupported = () => {
    return typeof window !== 'undefined'
        && 'Notification' in window
        && 'serviceWorker' in navigator
}

/**
 * Inisialisasi Firebase + daftarkan service worker.
 * TIDAK meminta permission (harus dipicu user gesture) — lihat enableNotifications().
 */
export const initFirebase = async (cfg: FirebaseSharedConfig = {}) => {
    console.log('[Firebase] init dipanggil | apiKey:', cfg.apiKey ? 'ADA' : 'KOSONG', '| vapid:', cfg.vapidKey ? 'ADA' : 'KOSONG', '| base:', cfg.baseUrl || 'KOSONG')

    if (!isSupported() || !cfg.apiKey) {
        console.log('[Firebase] skip — tidak didukung / apiKey kosong')
        return
    }
    if (initialized) {
        console.log('[Firebase] sudah di-inisialisasi')
        return
    }
    initialized = true
    config = cfg

    try {
        const app = initializeApp({
            apiKey: cfg.apiKey,
            authDomain: cfg.authDomain,
            projectId: cfg.projectId,
            storageBucket: cfg.storageBucket,
            messagingSenderId: cfg.messagingSenderId,
            appId: cfg.appId,
            measurementId: cfg.measurementId,
        })
        console.log('[Firebase] initializeApp OK')

        messaging = getMessaging(app)

        const base = cfg.baseUrl || ''
        const swPath = `${base}/firebase-messaging-sw.js`
        console.log('[Firebase] daftar service worker:', swPath)
        registration = await navigator.serviceWorker.register(swPath)
        console.log('[Firebase] service worker terdaftar, active:', !!registration.active)

        // Listener foreground (app terbuka) — setelah messaging siap
        onMessage(messaging, (payload) => {
            const title = payload.notification?.title || 'SUKIRMAN'
            const body = payload.notification?.body || ''
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    new Notification(title, { body })
                } catch {
                    // fallback
                }
            }
        })
    } catch (e) {
        console.error('[Firebase] Init gagal:', e)
    }
}

/**
 * Minta izin notifikasi + dapatkan FCM token.
 * WAJIB dipanggil dari user gesture (klik/tap) — Chrome menolak requestPermission otomatis.
 */
export const enableNotifications = async () => {
    if (!messaging || !config.vapidKey || !config.baseUrl) {
        console.log('[Firebase] enableNotifications skip — belum siap')
        return
    }
    if (permissionRequested) {
        console.log('[Firebase] permission sudah diminta sebelumnya')
        return
    }
    permissionRequested = true

    try {
        const permission = await Notification.requestPermission()
        console.log('[Firebase] permission:', permission)
        if (permission !== 'granted') return

        const reg = registration || (await navigator.serviceWorker.getRegistration())
        const token = await getToken(messaging, { vapidKey: config.vapidKey, serviceWorkerRegistration: reg })
        console.log('[Firebase] token:', token ? token.slice(0, 24) + '...' : 'KOSONG')
        if (token) {
            await fetch(`${config.baseUrl}/fcm-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf_token || '',
                },
                body: JSON.stringify({ fcm_token: token }),
            })
            console.log('[Firebase] token dikirim ke backend')
        }
    } catch (e) {
        console.error('[Firebase] enableNotifications gagal:', e)
    }
}
