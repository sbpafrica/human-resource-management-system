import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import axios from 'axios';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ——————————————————————————————————————
// FIXED & PERMANENT AXIOS SETUP (NO MORE 419!)
// ——————————————————————————————————————
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// This interceptor runs on EVERY request → always uses the latest CSRF token
axios.interceptors.request.use((config) => {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    } else {
        console.warn('CSRF token not available in meta tag');
    }

    return config;
});

// Optional: helpful 419 debugging (remove in production if you want)
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            console.error('CSRF token mismatch (419) – this should no longer happen');
        }
        return Promise.reject(error);
    }
);
// ——————————————————————————————————————

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx')
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
