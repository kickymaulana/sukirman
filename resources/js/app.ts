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

const app = createApp(App);

app.use(Varlet);
app.mount('#app');
