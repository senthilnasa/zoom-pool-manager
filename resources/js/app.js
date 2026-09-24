import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import App from './App.vue';

// Set up Axios default headers
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = window.__ZPM__?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.mount('#app');
