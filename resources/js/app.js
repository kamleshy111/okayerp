import '../css/app.css';
import './bootstrap';
import '../css/custom.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';


import DataTablesLib from 'datatables.net'; 
import DataTable from 'datatables.net-vue3';

DataTable.use(DataTablesLib);


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
              vueApp.use(plugin);
              vueApp.use(ZiggyVue);

              //Datatable use
              vueApp.component('DataTable', DataTable);

              // Mount the app
              vueApp.mount(el);

              return vueApp;
    },
    progress: {
        color: '#4B5563',
    },
});
