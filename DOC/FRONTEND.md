# Frontend y diseño de interacción

## Estado

Norma aprobada para todo el frontend. La integración técnica de Inertia 3, Vue 3, TypeScript y Vite está implementada, junto con una página mínima de comprobación.

RF-01 define el sistema visual inicial mediante CSS propio: tipografía del sistema, superficies neutras, azul para acciones y foco, verde para validación y rojo para errores. RF-02 a RF-11 cubren carga, análisis y selección. RF-12 a RF-18 añaden confirmación centrada, ejecución bloqueada frente a doble clic, resultados por fila y reintento de errores. RF-19 incorpora una descarga CSV explícita después de obtener resultados. No utiliza librería de componentes ni de movimiento.

## Fuente obligatoria

Todo desarrollo o revisión de interfaz debe utilizar la skill `$emil-design-eng`, ubicada actualmente en:

```text
C:\Users\aleja\.agents\skills\emil-design-eng\SKILL.md
```

La skill debe leerse completa al iniciar cada trabajo de frontend. Este documento resume las reglas aplicadas al proyecto, pero no sustituye a la skill. Si no está disponible, el desarrollo debe detenerse y comunicarse al usuario.

## Objetivo de experiencia

La interfaz debe transmitir seguridad operativa, claridad y rapidez. El usuario manejará acciones que pueden modificar pedidos y enviar notificaciones, por lo que el diseño debe:

- Hacer visible el estado real de cada expedición.
- Diferenciar claramente análisis, selección, confirmación, ejecución y resultado.
- Evitar acciones accidentales.
- Proporcionar feedback inmediato sin introducir lentitud artificial.
- Mantener los casos ambiguos y errores comprensibles y accionables.
- Dar prioridad a la legibilidad de la tabla y a la revisión segura frente a efectos decorativos.

## Criterio para animaciones

Antes de crear una animación se responderá, en orden:

1. ¿Debe animarse teniendo en cuenta su frecuencia de uso?
2. ¿Qué propósito concreto cumple?
3. ¿Qué easing corresponde a ese movimiento?
4. ¿Cuál es la duración mínima que mantiene la transición comprensible?

Propósitos válidos:

- Explicar una relación espacial.
- Indicar un cambio de estado.
- Confirmar que una interacción fue recibida.
- Evitar un cambio visual brusco.

No se añadirá movimiento únicamente por decoración en interacciones frecuentes. Las acciones iniciadas por teclado no se animarán.

## Movimiento y respuesta

- Las animaciones habituales de interfaz deben permanecer por debajo de 300 ms.
- Botones: feedback de pulsación entre 100 y 160 ms, con una escala sutil cercana a `0.97`.
- Tooltips y popovers pequeños: entre 125 y 200 ms.
- Desplegables: entre 150 y 250 ms.
- Modales y drawers: entre 200 y 500 ms solo cuando el contexto lo justifique.
- Entradas y salidas utilizarán normalmente una curva `ease-out` explícita.
- Los movimientos dentro de pantalla utilizarán `ease-in-out` cuando corresponda.
- Los cambios de color o hover utilizarán `ease`.
- El movimiento constante utilizará `linear`.
- No se utilizará `ease-in` para respuestas de interfaz.
- No se animará desde `scale(0)`; las entradas por escala partirán de `0.9` o superior junto con opacidad.
- No se utilizará `transition: all`; deben declararse las propiedades exactas.

Curvas base iniciales permitidas:

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

Los valores definitivos se validarán visualmente en el contexto de cada componente; no se aplicarán automáticamente a todo.

## Componentes

- Todo elemento presionable debe responder de inmediato a la pulsación.
- Los popovers deben usar un origen de transformación relacionado con su disparador.
- Los modales mantendrán el origen centrado.
- Los tooltips pueden tener retraso inicial, pero los tooltips adyacentes posteriores deben aparecer sin retrasos repetitivos cuando la implementación lo permita.
- Para UI interrumpible se prefieren transiciones CSS a keyframes.
- Se utilizarán springs únicamente en gestos o interacciones físicas que puedan interrumpirse y cuando exista una justificación expresa.
- Los estados de carga deben mejorar la percepción de velocidad sin ocultar el progreso real ni simular resultados.
- Las transiciones nunca impedirán seleccionar, cancelar, revisar o continuar una acción.

## Rendimiento

- Se animarán preferentemente `transform` y `opacity`.
- No se animarán `width`, `height`, `margin` o `padding` cuando exista una alternativa que evite layout y paint continuos.
- Para animaciones predeterminadas se priorizará CSS sobre JavaScript.
- Las animaciones programáticas deben utilizar WAAPI cuando aporte control sin requerir una dependencia adicional.
- No se añadirá una librería de movimiento sin una necesidad concreta y una decisión documentada.
- La tabla de hasta 1.000 filas no debe degradarse por animaciones por fila, observers innecesarios ni recálculos globales.

## Accesibilidad

- Debe respetarse `prefers-reduced-motion`.
- En modo de movimiento reducido se eliminarán desplazamientos y escalados prescindibles, manteniendo transiciones discretas de opacidad o color que ayuden a comprender el estado.
- Los hover animados se limitarán mediante:

```css
@media (hover: hover) and (pointer: fine) {
  /* Estados hover reales. */
}
```

- El estado no dependerá únicamente del color o de una animación.
- El foco, la navegación por teclado y los anuncios accesibles deben conservarse durante cambios de estado y modales.

## Verificación de interfaz

Cada funcionalidad frontend deberá comprobar, según corresponda:

- Estado normal, hover, active, focus, disabled, loading, success y error.
- Navegación mediante teclado.
- Movimiento reducido.
- Interacciones táctiles cuando existan.
- Vista móvil y escritorio.
- Animaciones a velocidad reducida o fotograma a fotograma cuando sea necesario.
- Rendimiento durante carga, filtrado y ejecución de lotes.

Las revisiones de código o movimiento deben presentar los hallazgos en esta tabla:

| Before | After | Why |
|---|---|---|
| Comportamiento actual | Cambio propuesto | Motivo funcional o de calidad |

## Decisiones pendientes

- Estrategia exacta para toasts, modales, popovers y tooltips.
- Necesidad real de una librería de movimiento.

No se elegirán estas opciones hasta que el usuario las confirme o solicite una propuesta específica.

La estrategia de progreso detallado por fila queda fuera del MVP local por decisión del usuario: los lotes previstos no serán largos. Se conserva el estado real de operación en curso existente, sin barra ni porcentaje simulado.

## Implementación base actual

```text
resources/
├── css/app.css             Reset y tokens visuales globales
├── js/app.ts               Arranque de Vue e Inertia sin SSR
├── js/Components/Analysis/ Vista previa tabular de resultados RF-10
├── js/Components/Csv/      Panel presentacional de carga
├── js/Composables/         Validación CSV y selección temporal de expediciones
├── js/Pages/Home.vue       Página de importación
├── js/Types/               Contratos TypeScript del CSV y análisis Shopify
├── js/vite-env.d.ts        Tipos de Vite
└── views/app.blade.php     Contenedor Blade exclusivo para Inertia
```

Blade se limita al documento raíz requerido por Inertia. No contiene componentes Vue ni lógica de interfaz, y ningún archivo `.vue` contiene PHP o Blade.

La interfaz RF-01 utiliza feedback de pulsación solo en punteros precisos, transiciones breves de propiedades concretas y desactiva el movimiento bajo `prefers-reduced-motion`. Los estados de éxito y error usan `role="status"` y `role="alert"`, respectivamente.

RF-02 no introdujo una tabla ni controles adicionales. El panel confirmó en el estado de éxito que la lectura conservó texto y ceros iniciales, mientras el composable retuvo el resultado completo —incluidas las filas— únicamente en memoria. La presentación posterior corresponde a RF-10.

RF-03 muestra cuatro métricas estáticas —filas totales, candidatas, codificación y tamaño— y diferencia mediante texto, color y semántica el resultado con candidatas del resultado vacío. No se anima la aparición porque no aporta una relación espacial ni mejora una interacción frecuente. El aviso vacío usa `role="status"`, no bloquea una nueva carga y muestra expresamente que Shopify no será modificado.

RF-04 amplía el mismo bloque estático con métricas de solicitadas, procesables y conflictos. Cuando existe un grupo contradictorio, el aviso enumera su agencia normalizada, albarán y número de filas afectadas, explica que no se procesará automáticamente y conserva `role="status"`. No se incorpora tabla general, interacción ni animación; la lista se adapta a móvil sin desbordamiento.

El composable normaliza el límite HTTP antes de publicar el resultado al componente. Si durante una actualización local recibe todavía el contrato RF-03, deriva las procesables de sus filas y usa cero conflictos; si faltan campos básicos, presenta un error operativo. El template no debe acceder directamente a propiedades opcionales de una respuesta sin adaptar, porque un error de renderizado ocultaría el formulario completo.

RF-05 no añade controles, tablas ni animaciones. El aviso de éxito existente aclara que agencia, código postal y referencia de comparación están normalizados sin modificar la referencia original. Las candidatas completas, incluida `normalizedReference`, continúan solo en memoria para los requisitos posteriores.

RF-06 amplió el contrato de cada candidata con `trackingUrl`. RF-10 presenta esa URL como enlace aislado hacia NACEX; no se incorporan controles ni animaciones por fila.

## Vista previa RF-10

La densidad y el comportamiento de la tabla fueron confirmados por el usuario: presentación compacta, cabecera fija dentro de una región desplazable, desplazamiento horizontal en pantallas estrechas y ninguna animación por fila. La tabla admite hasta 1.000 resultados sin observers, transiciones de entrada ni controles interactivos por expedición.

`AnalysisPreview.vue` presenta la referencia NACEX, agencia, albarán, código postal, fecha de alta, enlace de seguimiento, pedido o candidatos Shopify, destinatario, estado de preparación y clasificación final. Los casos ambiguos enumeran sus candidatos para revisión sin elegir uno. Las etiquetas contienen texto explícito, de modo que el resultado no depende solo del color.

La región de tabla puede recibir foco por teclado, tiene nombre accesible y conserva visible la cabecera durante el desplazamiento vertical. El enlace de seguimiento se abre en una pestaña separada con aislamiento del contexto de origen. No se incluyen enlaces al administrador de Shopify, selección de filas, asignación manual ni acciones de cumplimiento porque corresponden a requisitos posteriores.

`useCsvValidation` normaliza el contrato RF-10 antes de publicarlo. Mantiene compatibilidad temporal con respuestas anteriores mediante `matchStatus: null`, pero rechaza filas, pedidos, candidatos o estados con estructura desconocida. Los datos continúan solo en memoria de Vue y se pierden al recargar.

## Selección RF-11

Las filas `MATCHED_SAFE` con pedido identificado se seleccionan por defecto. El usuario puede desmarcarlas, volver a seleccionar todas las coincidencias seguras y excluir o restaurar cualquier expedición. La exclusión siempre retira la fila de la selección. Los demás estados no se pueden seleccionar, salvo `AMBIGUOUS` después de asignar manualmente uno de sus candidatos.

Los candidatos ambiguos se presentan con radios nativos, tal como confirmó el usuario. Elegir o cambiar el candidato deja la fila sin seleccionar para exigir una segunda acción explícita mediante checkbox. Esta separación reduce el riesgo de convertir una inspección en una inclusión accidental. La asignación, selección y exclusión viven únicamente en memoria mediante `useShipmentSelection`; solo la selección confirmada se envía al endpoint de ejecución junto con el mismo CSV.

Cada pedido expone un enlace “Abrir” en una pestaña separada. El frontend solo acepta URLs HTTPS con host `*.myshopify.com` y ruta numérica `/admin/orders/{id}`; cualquier forma desconocida invalida la respuesta. Los controles conservan foco visible, nombre accesible y feedback de pulsación limitado a punteros precisos. No se animan filas, radios, checkboxes ni cambios de selección.

## Confirmación y resultados RF-12 a RF-18

`FulfillmentExecutionPanel.vue` separa visualmente la operación destructiva de la tabla de revisión. El botón indica cuántos pedidos siguen pendientes y abre un diálogo centrado con los recuentos de pedidos y notificaciones. La notificación está activada por defecto y su explicación aclara que solo se solicita para un cumplimiento nuevo. La operación requiere una segunda acción explícita.

Durante la petición, los botones de confirmar y reintentar quedan deshabilitados y muestran el estado real. No se presenta una barra de progreso ficticia: el lote se comunica como una única operación en curso y los resultados llegan por fila. El diálogo puede cerrarse con la acción Cancelar o pulsando el fondo solo antes de iniciar la petición.

Los resultados usan texto además de color y muestran `Completado`, `Seguimiento existente`, `Ya preparado`, `Revisión necesaria` o `Error`. Un pedido con URL administrativa validada conserva acceso directo. Solo los errores de comunicación ofrecen `Reintentar`; los resultados seguros ya resueltos salen del recuento pendiente. Todo permanece en memoria y se pierde al recargar.

No hay animaciones por fila ni transiciones de entrada. El diálogo aparece centrado sin movimiento; los botones usan únicamente feedback breve de presión en punteros precisos. `prefers-reduced-motion` elimina las transiciones de controles.

## Descarga de informe RF-19

La acción “Descargar informe CSV” aparece junto al resultado después de recibir al menos una respuesta de ejecución. La descarga no introduce modal, toast ni animación: cambia su texto mientras se genera, evita solicitudes duplicadas y presenta un error accesible sin borrar los resultados existentes.

Vue combina únicamente información ya presente en memoria: todas las filas analizadas, sus pedidos temporales y los resultados de ejecución. El navegador envía las 16 columnas finales al endpoint temporal y descarga su respuesta como `informe-ejecucion-nacex.csv`. No se usa almacenamiento local, cookie funcional ni estado persistente; recargar elimina tanto el análisis como la capacidad de regenerar el informe.

“Notificación solicitada” vale `SÍ` solo para `COMPLETED` cuando la opción estaba activa en esa petición. No afirma la entrega del correo. La fecha de procesamiento usa `DD/MM/AAAA HH:mm:ss` en `Europe/Madrid` y queda vacía para filas no ejecutadas. Un caso ambiguo no asignado deja vacíos los campos singulares de pedido; una asignación manual ejecutada utiliza el candidato efectivamente procesado.
