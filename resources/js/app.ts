import '../css/app.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/inter/800.css';
import '@varlet/touch-emulator';
import '@varlet/ui/es/style';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, defineComponent, Transition } from 'vue';
import Varlet, { Themes, StyleProvider } from '@varlet/ui';
import { ZiggyVue } from 'ziggy-js';

StyleProvider(Themes.md3Light);

createInertiaApp({
    title: (title) => `${title} - App`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render() {
                return h(Transition, {
                    name: 'slide',
                    mode: 'out-in',
                }, () => h('div', { style: 'min-height:100vh' }, h(App, props)))
            }
        })
            .use(plugin)
            .use(Varlet)
            .use(ZiggyVue)
            .mount(el);
    },
});
