import '../css/app.css';
import './bootstrap';

// Patch for "Added non-passive event listener to a scroll-blocking 'touchstart' event" violation
(function () {
    if (typeof EventTarget !== 'undefined') {
        const func = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function (type, fn, capture) {
            this.func = func;
            if (typeof capture !== 'boolean') {
                capture = capture || {};
            } else {
                capture = { capture: capture };
            }

            // Only force passive on scroll-blocking events for window/document/body
            // This silences the specific violation while allowing interactive elements to work
            if (['touchstart', 'touchmove', 'wheel', 'mousewheel'].includes(type)) {
                if (capture.passive === undefined) {
                    // Check if target is a global scrolling container
                    if (this === window || this === document || this === document.body) {
                        capture.passive = true;
                    }
                }
            }

            this.func(type, fn, capture);
        };
    }
})();

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

//  Vue Toastification
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

//  Lucide icons (modern & lightweight)
import * as LucideIcons from 'lucide-vue-next';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) });

        //  Core plugins
        vueApp.use(plugin);
        vueApp.use(ZiggyVue);
        vueApp.use(VueApexCharts); // <--  ApexCharts added here

        //  Toastification setup
        vueApp.use(Toast, {
            position: 'top-right',
            timeout: 3000,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
            draggablePercent: 80,
            showCloseButtonOnHover: true,
            closeButton: 'button',
            icon: true,
        });

        //  Register Lucide icons globally
        Object.entries(LucideIcons).forEach(([name, component]) => {
            if (name && component && typeof component === 'object') {
                vueApp.component(name, component);
            }
        });

        vueApp.mount(el);

        // Remove initial loader with fade out
        const loader = document.getElementById('app-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 500);
        }
    },
    progress: {
        color: '#4B5563',
    },
});
