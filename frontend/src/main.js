import './assets/main.css'
import './assets/all.min.css'
import axios from 'axios'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

axios.defaults.baseURL = 'http://127.0.0.1:8000'
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

const pinia = createPinia()
const app = createApp(App)

app.use(pinia)
app.use(router)

app.mount('#app')