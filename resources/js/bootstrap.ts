import axios from 'axios';

// Laravel Breeze/Inertia expects axios configured for CSRF-protected requests.
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

