/* global importScripts, firebase */
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

// Config Firebase — jaga konsisten dengan .env (VITE_FIREBASE_*)
firebase.initializeApp({
    apiKey: 'AIzaSyBamrF0s1QhLrPKJxkZTCUg9XtcrK7XNd4',
    authDomain: 'markdynamicsindonesia.firebaseapp.com',
    projectId: 'markdynamicsindonesia',
    storageBucket: 'markdynamicsindonesia.firebasestorage.app',
    messagingSenderId: '710451244814',
    appId: '1:710451244814:web:d3f02cc99c04d96b1af15f',
    measurementId: 'G-GHJ55987Z7',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title || 'SUKIRMAN';
    const options = {
        body: payload.notification?.body || '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: { url: payload.data?.url || '' },
    };

    self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/dashboard';
    event.waitUntil(clients.openWindow(url));
});
