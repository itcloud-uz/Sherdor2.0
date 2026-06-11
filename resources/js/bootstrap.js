import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    (response) => {
        const method = response.config?.method?.toLowerCase();
        if (['post', 'put', 'patch', 'delete'].includes(method)) {
            window.dispatchEvent(new CustomEvent('show-success-notification'));
        }
        return response;
    },
    (error) => {
        return Promise.reject(error);
    }
);
