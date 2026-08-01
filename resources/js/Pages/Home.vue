<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Inicio" />

    <main class="launch-page">
        <section class="launch-card" aria-labelledby="page-title">
            <p class="eyebrow">Herramienta interna</p>
            <h1 id="page-title">Gestor de seguimientos</h1>
            <p class="summary">
                La base técnica está preparada para comenzar el desarrollo funcional.
            </p>

            <dl class="stack-list">
                <div>
                    <dt>Backend</dt>
                    <dd>Laravel 13 · PHP 8.3</dd>
                </div>
                <div>
                    <dt>Frontend</dt>
                    <dd>Inertia 3 · Vue 3 · TypeScript</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd><span class="status-dot" aria-hidden="true"></span>Preparado</dd>
                </div>
            </dl>
        </section>
    </main>
</template>
