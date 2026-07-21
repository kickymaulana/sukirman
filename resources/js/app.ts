import '../css/app.css';

// 1. Tambahkan ini agar komponen bisa diklik/digeser pakai mouse di Laptop/Desktop
import '@varlet/touch-emulator';

// 2. Entry point style resmi dari Varlet
import '@varlet/ui/es/style';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import Varlet, { Themes, StyleProvider } from '@varlet/ui';

// 💡 IMPORT ZiggyVue DI SINI
import { ZiggyVue } from 'ziggy-js';

// Set tema awal ke Material Design 3 Light
StyleProvider(Themes.md3Light);

createInertiaApp({
    title: (title) => `${title} - App`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Varlet)
            .use(ZiggyVue) // Now ZiggyVue is properly imported!
            .mount(el);
    },
});
