import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
    pages: './Pages',
    title: (title) => title
        ? `${title} · Gestor de seguimientos`
        : 'Gestor de seguimientos',
    setup({ el, App, props, plugin }) {
        if (!el) {
            throw new Error('No se ha encontrado el elemento raíz de Inertia.');
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
