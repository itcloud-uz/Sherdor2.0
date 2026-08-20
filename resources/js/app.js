import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import i18n from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'IQS ERP';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n);
            
        app.config.globalProperties.$currency = function(value) {
            if (!value && value !== 0) return '';
            const currency = this.$page.props.branding?.default_currency || 'USD';
            const num = Number(value);
            
            if (currency === 'UZS') {
                return num.toLocaleString('ru-RU') + ' so\'m';
            }
            
            // Default to USD
            return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
