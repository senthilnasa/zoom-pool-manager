import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import App from './App.vue';
import { initCsrfKeepAlive } from './services/csrf';

// Initialize CSRF management, session keepalive, and 419 retry handling
initCsrfKeepAlive(axios);

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.mount('#app');
