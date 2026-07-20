import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import App from './App.vue'; // Atau sesuaikan dengan root component Vue kamu

// Import Varlet dan Style-nya
import Varlet from '@varlet/ui';
import '@varlet/ui/es/style';

// (Opsional) Jika ingin langsung pakai Material Design 3 Light Mode
import { Themes, StyleProvider } from '@varlet/ui';
StyleProvider(Themes.md3Light);

createInertiaApp({
    // Secara default, plugin @inertiajs/vite akan mencari komponen di folder:
    // resources/js/Pages/ atau resources/js/pages/

    withApp(app) {
        // Daftarkan Varlet secara global agar bisa dipakai di seluruh halaman Inertia
        app.use(Varlet);
    },
});



