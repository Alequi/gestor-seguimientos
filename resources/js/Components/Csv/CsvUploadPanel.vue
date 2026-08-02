<script setup lang="ts">
import { ref } from 'vue';
import { useCsvValidation } from '../../Composables/useCsvValidation';
import type { CsvUploadConfig } from '../../Types/CsvUpload';

const props = defineProps<{
    config: CsvUploadConfig;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const {
    canValidate,
    clearFile,
    errors,
    isValidating,
    selectedFile,
    selectFile,
    validateFile,
    validationResult,
} = useCsvValidation(props.config);

function openFilePicker(): void {
    fileInput.value?.click();
}

function handleFileInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectFile(input.files?.[0] ?? null);
    input.value = '';
}

function handleDrop(event: DragEvent): void {
    isDragging.value = false;
    selectFile(event.dataTransfer?.files[0] ?? null);
}

function handleDragLeave(event: DragEvent): void {
    const target = event.currentTarget as HTMLElement;

    if (event.relatedTarget instanceof Node && target.contains(event.relatedTarget)) {
        return;
    }

    isDragging.value = false;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toLocaleString('es-ES', { maximumFractionDigits: 1 })} KB`;
    }

    return `${(bytes / (1024 * 1024)).toLocaleString('es-ES', { maximumFractionDigits: 2 })} MB`;
}
</script>

<template>
    <section class="upload-panel" aria-labelledby="upload-title">
        <div class="upload-main">
            <header class="section-heading">
                <p class="step-label">Validación inicial</p>
                <h2 id="upload-title">Importa las expediciones</h2>
                <p>
                    Selecciona el CSV exportado desde NACEX. Solo validaremos su estructura;
                    todavía no se consultará ni modificará Shopify.
                </p>
            </header>

            <form class="upload-form" :aria-busy="isValidating" @submit.prevent="validateFile">
                <input
                    ref="fileInput"
                    class="visually-hidden"
                    type="file"
                    name="csv"
                    accept=".csv,text/csv"
                    :disabled="isValidating"
                    @change="handleFileInput"
                >

                <button
                    type="button"
                    class="dropzone"
                    :class="{ 'is-dragging': isDragging }"
                    :disabled="isValidating"
                    aria-describedby="upload-requirements"
                    @click="openFilePicker"
                    @dragenter.prevent="isDragging = true"
                    @dragover.prevent="isDragging = true"
                    @dragleave="handleDragLeave"
                    @drop.prevent="handleDrop"
                >
                    <span class="upload-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14.5V18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5" />
                        </svg>
                    </span>
                    <span class="dropzone-title">Arrastra aquí el archivo CSV</span>
                    <span class="dropzone-copy">o pulsa para seleccionarlo desde tu equipo</span>
                    <span class="dropzone-limit">CSV · máximo {{ config.maxFileSizeMb }} MB</span>
                </button>

                <div v-if="selectedFile" class="selected-file">
                    <div class="file-details">
                        <span class="file-type" aria-hidden="true">CSV</span>
                        <span>
                            <strong>{{ selectedFile.name }}</strong>
                            <small>{{ formatFileSize(selectedFile.size) }}</small>
                        </span>
                    </div>
                    <button
                        type="button"
                        class="remove-file"
                        :disabled="isValidating"
                        :aria-label="`Quitar ${selectedFile.name}`"
                        @click="clearFile"
                    >
                        Quitar
                    </button>
                </div>

                <div v-if="errors.length" class="feedback feedback-error" role="alert">
                    <strong>No podemos analizar este archivo</strong>
                    <ul>
                        <li v-for="error in errors" :key="error">{{ error }}</li>
                    </ul>
                </div>

                <div
                    v-if="validationResult"
                    class="feedback feedback-result"
                    :class="
                        validationResult.requestedRowCount > 0 &&
                        validationResult.duplicateConflictCount === 0
                            ? 'feedback-success'
                            : 'feedback-warning'
                    "
                    role="status"
                >
                    <div>
                        <strong v-if="validationResult.duplicateConflictCount > 0">
                            Hay expediciones que requieren revisión
                        </strong>
                        <strong v-else-if="validationResult.processableRowCount > 0">
                            Expediciones deduplicadas correctamente
                        </strong>
                        <strong v-else>Sin expediciones solicitadas</strong>
                        <p
                            v-if="
                                validationResult.duplicateConflictCount > 0 &&
                                validationResult.processableRowCount > 0
                            "
                        >
                            Las filas con la misma agencia y albarán que contienen datos
                            contradictorios se han apartado y no se procesarán automáticamente.
                            Las demás expediciones pueden continuar.
                        </p>
                        <p v-else-if="validationResult.duplicateConflictCount > 0">
                            Todas las filas solicitadas pertenecen a grupos con datos
                            contradictorios. Se han apartado para revisión y no se procesará ninguna
                            automáticamente.
                        </p>
                        <p v-else-if="validationResult.processableRowCount > 0">
                            La estructura es válida. Las repeticiones de una misma expedición se
                            han reducido a una sola candidata sin modificar sus valores originales.
                        </p>
                        <p v-else>
                            No se han encontrado filas con estado «Solicitada». No se realizará
                            ningún cambio en Shopify.
                        </p>
                    </div>
                    <ul
                        v-if="validationResult.duplicateConflictCount > 0"
                        class="duplicate-conflicts"
                        aria-label="Expediciones duplicadas con datos contradictorios"
                    >
                        <li
                            v-for="conflict in validationResult.duplicateConflicts"
                            :key="`${conflict.originAgency}-${conflict.trackingNumber}`"
                        >
                            <strong>
                                Agencia {{ conflict.originAgency }} · Albarán
                                {{ conflict.trackingNumber }}
                            </strong>
                            <span>
                                {{ conflict.rows.length.toLocaleString('es-ES') }} filas para
                                revisar
                            </span>
                        </li>
                    </ul>
                    <dl>
                        <div>
                            <dt>Filas totales</dt>
                            <dd>{{ validationResult.rowCount.toLocaleString('es-ES') }}</dd>
                        </div>
                        <div>
                            <dt>Solicitadas</dt>
                            <dd>
                                {{ validationResult.requestedRowCount.toLocaleString('es-ES') }}
                            </dd>
                        </div>
                        <div>
                            <dt>Procesables</dt>
                            <dd>
                                {{ validationResult.processableRowCount.toLocaleString('es-ES') }}
                            </dd>
                        </div>
                        <div>
                            <dt>Conflictos</dt>
                            <dd>
                                {{ validationResult.duplicateConflictCount.toLocaleString('es-ES') }}
                            </dd>
                        </div>
                        <div>
                            <dt>Codificación</dt>
                            <dd>{{ validationResult.encoding }}</dd>
                        </div>
                        <div>
                            <dt>Tamaño</dt>
                            <dd>{{ formatFileSize(validationResult.sizeInBytes) }}</dd>
                        </div>
                    </dl>
                </div>

                <button class="primary-action" type="submit" :disabled="!canValidate">
                    {{ isValidating ? 'Validando CSV…' : 'Analizar CSV' }}
                </button>
            </form>
        </div>

        <aside id="upload-requirements" class="requirements" aria-labelledby="requirements-title">
            <div>
                <p class="aside-label">Antes de continuar</p>
                <h3 id="requirements-title">El archivo debe incluir</h3>
            </div>

            <ul class="column-list">
                <li v-for="column in config.requiredHeaders" :key="column">
                    <span aria-hidden="true">✓</span>{{ column }}
                </li>
            </ul>

            <div class="requirements-meta">
                <p><strong>Separador</strong><span>Punto y coma (;)</span></p>
                <p><strong>Límite</strong><span>{{ config.maxRows.toLocaleString('es-ES') }} filas</span></p>
                <p><strong>Codificación</strong><span>UTF-8, Windows-1252 o ISO-8859-1</span></p>
            </div>

            <p class="privacy-note">
                No guardamos el archivo ni mantenemos historial. Al recargar la página se perderá
                esta validación.
            </p>
        </aside>
    </section>
</template>

<style scoped>
.upload-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 0.38fr);
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: 24px;
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
}

.upload-main {
    padding: clamp(28px, 5vw, 52px);
}

.section-heading {
    max-width: 620px;
    margin-bottom: 32px;
}

.step-label,
.aside-label {
    margin: 0 0 10px;
    color: var(--color-accent);
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.section-heading h2 {
    margin: 0;
    color: var(--color-ink);
    font-size: clamp(1.65rem, 4vw, 2.25rem);
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.section-heading > p:last-child {
    max-width: 60ch;
    margin: 14px 0 0;
    color: var(--color-muted);
    line-height: 1.65;
}

.upload-form {
    display: grid;
    gap: 16px;
}

.dropzone {
    display: grid;
    width: 100%;
    min-height: 230px;
    place-items: center;
    align-content: center;
    gap: 7px;
    padding: 28px;
    border: 1.5px dashed #b7c0cf;
    border-radius: 18px;
    color: inherit;
    background: #f8fafc;
    cursor: pointer;
    font: inherit;
    text-align: center;
    transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.dropzone:focus-visible {
    outline: 3px solid var(--color-focus);
    outline-offset: 3px;
}

.dropzone.is-dragging {
    border-color: var(--color-accent);
    background: var(--color-accent-soft);
    box-shadow: inset 0 0 0 1px var(--color-accent);
}

.dropzone:disabled {
    cursor: wait;
    opacity: 0.68;
}

.upload-mark {
    display: grid;
    width: 48px;
    height: 48px;
    margin-bottom: 7px;
    place-items: center;
    border: 1px solid #d8deea;
    border-radius: 14px;
    color: var(--color-accent);
    background: var(--color-surface);
    box-shadow: 0 6px 16px rgb(22 34 57 / 7%);
}

.upload-mark svg {
    width: 24px;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.8;
}

.dropzone-title {
    color: var(--color-ink);
    font-weight: 720;
}

.dropzone-copy {
    color: var(--color-muted);
    font-size: 0.9375rem;
}

.dropzone-limit {
    margin-top: 9px;
    color: var(--color-subtle);
    font-size: 0.75rem;
    font-weight: 650;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.selected-file {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 16px;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    background: var(--color-surface);
}

.file-details {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
}

.file-details > span:last-child {
    display: grid;
    min-width: 0;
    gap: 3px;
}

.file-details strong {
    overflow: hidden;
    color: var(--color-ink);
    font-size: 0.9375rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-details small {
    color: var(--color-subtle);
}

.file-type {
    flex: 0 0 auto;
    padding: 7px 8px;
    border-radius: 8px;
    color: #19623a;
    background: #e8f6ed;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.remove-file {
    flex: 0 0 auto;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    color: var(--color-muted);
    background: transparent;
    cursor: pointer;
    font: inherit;
    font-size: 0.875rem;
    font-weight: 650;
    transition: color 140ms ease, background-color 140ms ease;
}

.remove-file:focus-visible,
.primary-action:focus-visible {
    outline: 3px solid var(--color-focus);
    outline-offset: 2px;
}

.feedback {
    padding: 16px 18px;
    border: 1px solid;
    border-radius: 14px;
}

.feedback strong {
    color: var(--color-ink);
}

.feedback-error {
    border-color: #efc7c5;
    color: #8f2f2a;
    background: #fff5f4;
}

.feedback-error ul {
    margin: 8px 0 0;
    padding-left: 20px;
}

.feedback-result {
    display: grid;
    gap: 16px;
}

.feedback-success {
    border-color: #b9ddc6;
    color: #23623a;
    background: #f1faf4;
}

.feedback-warning {
    border-color: #e8d49c;
    color: #755817;
    background: #fff9e8;
}

.feedback-result p {
    margin: 4px 0 0;
    line-height: 1.5;
}

.duplicate-conflicts {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.duplicate-conflicts li {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 12px;
    border: 1px solid rgb(132 109 57 / 22%);
    border-radius: 9px;
    background: rgb(255 255 255 / 72%);
}

.duplicate-conflicts strong {
    color: #5f4815;
    font-size: 0.875rem;
}

.duplicate-conflicts span {
    flex: 0 0 auto;
    color: #755817;
    font-size: 0.8125rem;
}

.feedback-result dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin: 0;
}

.feedback-result dl > div {
    padding: 10px 12px;
    border-radius: 9px;
    background: rgb(255 255 255 / 72%);
}

.feedback-result dt {
    color: #568068;
    font-size: 0.75rem;
}

.feedback-result dd {
    margin: 3px 0 0;
    color: #194b2c;
    font-weight: 750;
}

.feedback-warning dt {
    color: #846d39;
}

.feedback-warning dd {
    color: #5f4815;
}

.primary-action {
    min-height: 48px;
    padding: 0 22px;
    border: 0;
    border-radius: 12px;
    color: #ffffff;
    background: var(--color-accent);
    cursor: pointer;
    font: inherit;
    font-weight: 720;
    transition: background-color 150ms ease, box-shadow 150ms ease, transform 140ms var(--ease-out);
}

.primary-action:disabled {
    color: #9199a7;
    background: #e5e8ed;
    cursor: not-allowed;
    box-shadow: none;
}

.requirements {
    display: flex;
    flex-direction: column;
    gap: 26px;
    padding: clamp(28px, 4vw, 42px);
    border-left: 1px solid var(--color-border);
    background: #f8f9fb;
}

.requirements h3 {
    margin: 0;
    color: var(--color-ink);
    font-size: 1.125rem;
    letter-spacing: -0.02em;
}

.column-list {
    display: grid;
    gap: 11px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.column-list li {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #414957;
    font-size: 0.9rem;
}

.column-list span {
    display: grid;
    width: 19px;
    height: 19px;
    place-items: center;
    border-radius: 50%;
    color: #226840;
    background: #def3e6;
    font-size: 0.6875rem;
    font-weight: 800;
}

.requirements-meta {
    display: grid;
    gap: 11px;
    padding-top: 22px;
    border-top: 1px solid var(--color-border);
}

.requirements-meta p {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 0;
    color: var(--color-muted);
    font-size: 0.8125rem;
}

.requirements-meta strong {
    color: #3d4552;
}

.privacy-note {
    margin: auto 0 0;
    padding-top: 22px;
    border-top: 1px solid var(--color-border);
    color: var(--color-subtle);
    font-size: 0.8125rem;
    line-height: 1.55;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
}

@media (hover: hover) and (pointer: fine) {
    .dropzone:not(:disabled):hover {
        border-color: #76849a;
        background: #f5f7fa;
    }

    .remove-file:not(:disabled):hover {
        color: #a33b35;
        background: #fff0ef;
    }

    .primary-action:not(:disabled):hover {
        background: var(--color-accent-hover);
        box-shadow: 0 8px 20px rgb(47 91 210 / 20%);
    }

    .primary-action:not(:disabled):active,
    .remove-file:not(:disabled):active {
        transform: scale(0.97);
    }
}

@media (max-width: 820px) {
    .upload-panel {
        grid-template-columns: 1fr;
    }

    .requirements {
        border-top: 1px solid var(--color-border);
        border-left: 0;
    }

    .privacy-note {
        margin-top: 0;
    }
}

@media (max-width: 520px) {
    .upload-main,
    .requirements {
        padding: 24px 20px;
    }

    .dropzone {
        min-height: 210px;
        padding: 22px 16px;
    }

    .feedback-result dl {
        grid-template-columns: 1fr;
    }

    .duplicate-conflicts li {
        align-items: flex-start;
        flex-direction: column;
        gap: 3px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dropzone,
    .primary-action,
    .remove-file {
        transition-duration: 0ms;
    }
}
</style>
