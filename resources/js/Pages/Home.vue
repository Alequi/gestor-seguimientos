<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CsvUploadPanel from '../Components/Csv/CsvUploadPanel.vue';
import type { CsvUploadConfig } from '../Types/CsvUpload';

defineProps<{
    homeUrl: string;
    csvUpload: CsvUploadConfig;
}>();
</script>

<template>
    <Head title="Importar expediciones" />

    <div class="app-shell">
        <header class="topbar">
            <Link class="brand" :href="homeUrl" aria-label="Gestor de seguimientos, inicio">
                <span class="brand-mark" aria-hidden="true">GS</span>
                <span>
                    <strong>Gestor de seguimientos</strong>
                    <small>NACEX → Shopify</small>
                </span>
            </Link>
            <span class="environment">Entorno local</span>
        </header>

        <main>
            <section class="page-intro" aria-labelledby="page-title">
                <p class="eyebrow">Importación de expediciones</p>
                <h1 id="page-title">Prepara los seguimientos con seguridad</h1>
                <p>
                    Valida el archivo de NACEX antes de buscar pedidos. Ninguna acción modificará
                    Shopify sin una revisión y confirmación posteriores.
                </p>
            </section>

            <CsvUploadPanel :config="csvUpload" />
        </main>

        <footer>
            <p>Herramienta interna · Sin almacenamiento persistente</p>
        </footer>
    </div>
</template>

<style scoped>
.app-shell {
    width: min(100% - 40px, 1180px);
    margin: 0 auto;
}

.topbar {
    display: flex;
    min-height: 82px;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 1px solid var(--color-border);
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    color: inherit;
    text-decoration: none;
}

.brand:focus-visible {
    border-radius: 8px;
    outline: 3px solid var(--color-focus);
    outline-offset: 3px;
}

.brand-mark {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 11px;
    color: #ffffff;
    background: var(--color-ink);
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.brand > span:last-child {
    display: grid;
    gap: 1px;
}

.brand strong {
    color: var(--color-ink);
    font-size: 0.875rem;
}

.brand small {
    color: var(--color-subtle);
    font-size: 0.75rem;
}

.environment {
    padding: 6px 9px;
    border: 1px solid #dfe3ea;
    border-radius: 999px;
    color: #626b78;
    background: #f7f8fa;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

main {
    padding: clamp(54px, 8vw, 92px) 0 48px;
}

.page-intro {
    max-width: 760px;
    margin-bottom: clamp(32px, 5vw, 48px);
}

.eyebrow {
    margin: 0 0 13px;
    color: var(--color-accent);
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}

.page-intro h1 {
    max-width: 680px;
    margin: 0;
    color: var(--color-ink);
    font-size: clamp(2.3rem, 6.5vw, 4.25rem);
    letter-spacing: -0.055em;
    line-height: 0.98;
}

.page-intro > p:last-child {
    max-width: 64ch;
    margin: 22px 0 0;
    color: var(--color-muted);
    font-size: clamp(1rem, 2vw, 1.125rem);
    line-height: 1.65;
}

footer {
    display: flex;
    min-height: 84px;
    align-items: center;
    justify-content: center;
    border-top: 1px solid var(--color-border);
    color: var(--color-subtle);
    font-size: 0.8125rem;
}

footer p {
    margin: 0;
}

@media (max-width: 520px) {
    .app-shell {
        width: min(100% - 24px, 1180px);
    }

    .topbar {
        min-height: 72px;
    }

    .environment {
        display: none;
    }

    main {
        padding-top: 44px;
    }

    .page-intro h1 {
        line-height: 1.02;
    }
}
</style>
