# AGENTS.md

## Propósito del proyecto

Este repositorio implementará una herramienta interna para importar expediciones de NACEX desde un CSV, encontrar sus pedidos correspondientes en Shopify y crear los cumplimientos con su seguimiento.

La prioridad del producto es evitar asociaciones, cumplimientos y notificaciones incorrectas o duplicadas. Ante una duda, el sistema debe detener la automatización de esa fila y enviarla a revisión manual.

## Fuentes de verdad

Antes de diseñar o implementar una funcionalidad, consulta:

1. `DOC/INDEX.md`, como índice obligatorio de la documentación viva.
2. Los documentos enlazados desde el índice que estén relacionados con la tarea.
3. `PRD_integracion_NACEX_Shopify_sin_BBDD.md`.
4. `requisitos_funcionales_nacex_shopify.md`.
5. Las decisiones vigentes recogidas en este archivo y, cuando existan, en `.agents/decisions/`.

Para cualquier trabajo de frontend, diseño de interfaz, componentes, interacción o movimiento, lee además por completo la skill `$emil-design-eng` y `DOC/FRONTEND.md` antes de revisar o modificar el código afectado. Si la skill no está disponible, detente y comunícalo; no sustituyas sus criterios por supuestos propios.

Si dos requisitos se contradicen, no elijas uno silenciosamente. Documenta la contradicción y solicita una decisión antes de implementar un comportamiento irreversible o inseguro.

## Prohibido asumir — norma innegociable

No inventes, completes ni des por supuesto ningún requisito, dato, contexto, comportamiento, valor predeterminado o decisión técnica que no esté expresamente definido en la documentación o confirmado por el usuario.

Si falta información o existen varias interpretaciones posibles:

1. Detén el desarrollo antes de modificar el código afectado.
2. Explica de forma concreta qué dato o decisión falta y por qué afecta al resultado.
3. Pregunta al usuario y espera su respuesta.
4. Documenta la decisión recibida antes o junto con su implementación.

No utilices una opción «razonable», una práctica habitual ni una preferencia propia para desbloquear el trabajo. Esta regla también se aplica a arquitectura, dependencias, interfaz, configuración, seguridad, datos, reglas de negocio, pruebas, despliegue e integración con Shopify.

Las comprobaciones de solo lectura necesarias para descubrir información ya presente en el repositorio sí están permitidas. Si la información no puede descubrirse, hay que preguntar.

## Documentación viva — norma innegociable

Este proyecto utiliza el modelo de **documentación viva**. Cada cambio, corrección y nueva funcionalidad debe quedar documentado como parte del mismo trabajo.

Antes de iniciar cualquier desarrollo:

1. Lee siempre `DOC/INDEX.md`.
2. Comprueba en el índice si ya existe documentación sobre el área afectada.
3. Lee por completo la documentación relacionada antes de inspeccionar o modificar el código.
4. Después revisa el código existente y prepara el cambio.

Durante y al finalizar el desarrollo:

1. Si ya existe un documento relacionado, actualízalo; no crees documentación paralela innecesaria.
2. Si no existe, crea el documento correspondiente dentro de `DOC/`.
3. Añade o actualiza su entrada en `DOC/INDEX.md`.
4. Revisa y actualiza el `README.md` raíz para mantener alineados el estado del proyecto, sus funcionalidades, instalación, configuración, uso y estructura.
5. Documenta el comportamiento final implementado, las decisiones relevantes, la configuración, los casos límite y las verificaciones aplicables.
6. Considera incompleto cualquier cambio de código que no incluya su actualización documental y la revisión del README.

El orden obligatorio de trabajo es:

```text
DOC/INDEX.md → documentación relacionada → código → actualización documental → README.md → verificación
```

Esta secuencia no puede omitirse, aunque el cambio parezca pequeño.

## Decisiones vigentes del MVP

- El backend se desarrollará con PHP 8.3 o superior y Laravel 13.
- El frontend se desarrollará con Inertia 3, Vue 3 y TypeScript.
- Se utilizarán componentes Single-File Component (`.vue`) estándar con bloques `<template>`, `<script setup lang="ts">` y `<style>` cuando sea necesario.
- No se utilizará Filament.
- No se utilizará renderizado SSR en el MVP.
- La integración utilizará Shopify Admin GraphQL API.
- La herramienta atenderá a una única tienda Shopify.
- El primer entorno de ejecución será XAMPP y después se adaptará a un hosting compatible.
- No se utilizará una base de datos propia.
- Shopify será la fuente de verdad para el estado del pedido, los cumplimientos y los seguimientos existentes.
- La herramienta será interna y, de momento, no tendrá autenticación.
- Cada archivo tendrá como máximo 1.000 filas.
- El formato esperado es CSV con separador `;`.
- Solo se procesan expediciones cuyo estado normalizado sea `Solicitada`.
- Una expedición se deduplica durante la sesión mediante `agencia_origen + numero_albaran`.
- Los pedidos ambiguos no se procesan automáticamente, pero el usuario puede revisarlos y asignarlos manualmente a un pedido.
- Una asignación manual también debe revalidarse en Shopify justo antes de crear el cumplimiento.
- Si un resultado cumple simultáneamente `TRACKING_EXISTS` y `ALREADY_FULFILLED`, prevalece `TRACKING_EXISTS`.
- Los pedidos parcialmente preparados o con varias órdenes de cumplimiento requieren revisión manual en el MVP.
- La notificación al cliente está activada por defecto, pero solo se solicita al crear un cumplimiento nuevo.
- El fallo de una fila no debe detener el resto del lote.
- Los datos de la sesión y los informes no se almacenan de forma persistente.
- `SESSION_DRIVER=cookie` se utiliza únicamente para CSRF y mensajes operativos; está prohibido guardar en la cookie el CSV, datos personales o resultados.
- Los resultados temporales se mantienen solo en memoria en Vue y se pierden al recargar o cerrar la página.

## Reglas de seguridad funcional

- No modifiques Shopify durante la fase de análisis o vista previa.
- Revalida cada pedido inmediatamente antes de crear el cumplimiento.
- No crees un cumplimiento si el mismo albarán ya está registrado.
- No envíes una notificación si no se ha creado un cumplimiento nuevo.
- No selecciones automáticamente coincidencias ambiguas, pedidos parciales, pedidos ya preparados ni seguimientos existentes.
- Trata agencias, albaranes y códigos postales como texto para conservar ceros iniciales y evitar conversiones numéricas.
- No expongas credenciales, tokens ni datos personales en código, logs, errores o archivos versionados.
- No introduzcas persistencia, automatización sin revisión, scraping de NACEX ni sincronización de estados posteriores sin ampliar explícitamente el alcance.

## Arquitectura y calidad de código — normas innegociables

La arquitectura aprobada se describe en `DOC/ARQUITECTURA.md` y debe respetarse en cada cambio.

- Aplica Clean Code, responsabilidad única, nombres explícitos, funciones pequeñas y dependencias dirigidas hacia contratos.
- Mantén controladores HTTP delgados; no coloques en ellos parsing de CSV, matching, reglas de negocio ni llamadas GraphQL directas.
- Separa dominio, casos de uso, infraestructura e interfaz HTTP.
- Encapsula la integración con Shopify y el parsing de NACEX detrás de contratos propios.
- No crees clases genéricas de tipo `Helper`, `Utils`, `Manager` o servicios enormes que acumulen responsabilidades.
- No dupliques normalizaciones, reglas de matching, estados ni construcción de seguimientos.
- No mezcles PHP o Blade dentro de componentes Vue.
- Los componentes `.vue` seguirán el estándar oficial de Vue; sus bloques de plantilla, TypeScript y estilo sí pueden convivir dentro del mismo SFC.
- Extrae componentes, composables, tipos y utilidades cuando una página Vue acumule responsabilidades independientes.
- No introduzcas lógica de negocio crítica exclusivamente en el navegador. El backend debe validar y revalidar todas las operaciones que afecten a Shopify.
- No añadas una capa, patrón o abstracción si no tiene una responsabilidad concreta en este proyecto.
- Todo comportamiento nuevo debe incluir las pruebas unitarias o de integración que correspondan.

## Diseño frontend — norma innegociable

La implementación y revisión del frontend debe utilizar la skill `$emil-design-eng` ubicada actualmente en `C:\Users\aleja\.agents\skills\emil-design-eng\SKILL.md` y seguir `DOC/FRONTEND.md`.

- La interfaz debe sentirse rápida, clara y cuidada; los detalles invisibles forman parte de la calidad funcional.
- Antes de animar, decide si la animación es necesaria y qué propósito cumple.
- No animes acciones frecuentes iniciadas por teclado.
- Prioriza transiciones CSS interrumpibles y anima principalmente `transform` y `opacity`.
- No utilices `transition: all`, entradas desde `scale(0)` ni `ease-in` en interacciones de interfaz.
- Mantén normalmente las animaciones de interfaz por debajo de 300 ms y utiliza curvas de easing explícitas.
- Los elementos presionables deben ofrecer feedback inmediato y sutil.
- Los popovers deben originarse visualmente desde su disparador; los modales permanecen centrados.
- Respeta `prefers-reduced-motion` y limita los estados hover a dispositivos que realmente admitan hover.
- La animación nunca debe bloquear una acción ni ocultar el estado real del proceso.
- En revisiones de UI, presenta los hallazgos en una tabla Markdown con columnas `Before`, `After` y `Why`, tal como exige la skill.

## Aspectos todavía por concretar

No conviertas estos puntos en reglas definitivas sin una decisión de producto:

- Definición exacta de una fecha de pedido compatible con la fecha de alta de NACEX.
- Diferencia máxima de longitud aceptada para la coincidencia inversa entre referencia y nombre de Shopify.

No implementes comportamientos que dependan de estos puntos hasta obtener una decisión expresa del usuario. Esta restricción de desarrollo no altera la regla funcional de que la aplicación debe enviar a revisión manual los casos dudosos durante su ejecución.

## Forma de trabajo

1. Sigue siempre la secuencia obligatoria de documentación viva definida anteriormente.
2. Comprueba que dispones de todos los datos y decisiones necesarios; si falta alguno, detente y pregunta.
3. Mantén los cambios pequeños, trazables y limitados al requisito solicitado.
4. No elijas framework, arquitectura, versión de API de Shopify ni estrategia de despliegue sin una decisión expresa y documentada.
5. Añade pruebas para normalización, parsing, matching, precedencia de estados, revalidación e idempotencia cuando exista código para ello.
6. Verifica proporcionalmente cada cambio y comunica qué se comprobó y qué quedó sin comprobar.
7. No hagas commits, pushes, despliegues ni operaciones contra una tienda real salvo petición explícita.

## Criterios mínimos para cambios futuros

- El comportamiento debe respetar la PRD y los requisitos funcionales.
- `DOC/INDEX.md` y la documentación afectada deben reflejar el comportamiento final.
- El `README.md` debe reflejar el estado y la forma de uso vigentes del proyecto.
- Los casos dudosos deben fallar de forma segura.
- El código no debe depender de una base de datos.
- Las operaciones sobre distintas filas deben estar aisladas.
- Los mensajes mostrados al usuario deben ser claros y no revelar información sensible.
- Los cambios sobre Shopify deben poder repetirse sin duplicar cumplimientos ni correos.

## Carpeta `.agents`

Utiliza `.agents/` para documentación operativa del desarrollo agéntico:

- `.agents/plans/`: planes de implementación aprobados.
- `.agents/decisions/`: decisiones técnicas o funcionales que complementen la PRD.
- `.agents/handoffs/`: contexto temporal para transferir trabajo entre agentes.

No guardes secretos, datos de clientes, exportaciones reales de NACEX ni resultados de Shopify dentro de `.agents/`.
