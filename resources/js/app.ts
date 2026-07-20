import '../css/app.css';

// Gunakan entry point style resmi dari Varlet
import '@varlet/ui/es/style';

import { createInertiaApp } from '@inertiajs/vue3';
import Varlet from '@varlet/ui';
import { Themes, StyleProvider } from '@varlet/ui';

StyleProvider(Themes.md3Light);

createInertiaApp({
    withApp(app) {
        app.use(Varlet);
    },
});
