# Arquitectura y estructura del proyecto

## Estado

La base técnica y el flujo operativo completo del MVP están implementados. Laravel, Inertia, Vue y TypeScript están integrados; RF-01 a RF-11 preparan, buscan, clasifican, presentan y seleccionan; RF-12 a RF-18 confirman, revalidan, crean cumplimientos NACEX y aíslan los resultados; RF-19 genera el informe CSV temporal de la sesión.

## Stack aprobado

| Área | Tecnología |
|---|---|
| Backend | PHP 8.3 o superior y Laravel 13 |
| Puente frontend/backend | Inertia 3 |
| Frontend | Vue 3 y TypeScript |
| Integración Shopify | Shopify Admin GraphQL API |
| Base de datos propia | Ninguna |
| Renderizado SSR | No incluido |
| Panel administrativo | No se utilizará Filament |
| Tiendas Shopify | Una única tienda |
| Entorno inicial | XAMPP |
| Entorno posterior | Hosting compatible, pendiente de concretar |

Versiones instaladas en el entorno inicial:

- PHP 8.3.33 Thread Safe x64.
- Laravel Framework 13.23.0.
- Inertia Laravel 3.0.0 y adaptadores frontend 3.6.1.
- Vue 3.5.40, TypeScript 5.9.3 y Vite 7.3.6.

El detalle operativo se mantiene en `DOC/ENTORNO_DESARROLLO.md`.

## Principios arquitectónicos

- El dominio no debe depender de Laravel, Shopify, Inertia ni Vue.
- Los casos de uso coordinan el dominio mediante contratos explícitos.
- La infraestructura implementa el acceso al CSV y a Shopify.
- La capa HTTP valida la entrada y delega; no contiene reglas de negocio.
- Vue presenta y gestiona interacción de interfaz, pero no decide reglas críticas de negocio.
- Toda operación que pueda modificar Shopify se valida en el backend y se revalida inmediatamente antes de ejecutarse.
- No se introduce persistencia propia ni dependencia de Eloquent para el flujo funcional.
- La solución debe mantenerse como un monolito modular; no se creará una API separada para Inertia.

## Estructura backend

La base Laravel contiene el punto de entrada, configuración, middleware de Inertia y el primer caso de uso de validación de CSV. No se ha incorporado Eloquent, un modelo de usuario ni una carpeta `database/`.

Las capas funcionales se crearán progresivamente con esta estructura aprobada:

```text
app/
├── Domain/
│   ├── Shipment/          Expediciones, normalización y estados
│   ├── Matching/          Reglas y resultados de coincidencia
│   └── Fulfillment/       Reglas para completar y revalidar pedidos
├── Application/
│   ├── Actions/           Casos de uso del flujo
│   ├── Contracts/         Puertos requeridos por la aplicación
│   └── Data/              DTO inmutables entre capas
├── Infrastructure/
│   ├── Nacex/             Lectura y adaptación del CSV
│   └── Shopify/           Cliente GraphQL y adaptadores Shopify
└── Http/
    ├── Controllers/       Entrada HTTP delgada
    └── Requests/          Validación de peticiones y archivos
```

Esta estructura no autoriza a crear carpetas vacías ni abstracciones especulativas. Cada elemento se incorpora cuando existe un caso de uso que lo necesita.

RF-01 creó un caso de uso en `Application/Actions`, su resultado inmutable en `Application/Data`, un contrato de inspección en `Application/Contracts` y la implementación nativa de lectura en `Infrastructure/Nacex/Csv`. RF-02 incorpora `NacexCsvRow` como DTO inmutable y hace que la infraestructura adapte por posición las seis columnas requeridas, siempre como cadenas.

RF-03 crea `Domain/Shipment/NacexShipmentStatus`. Esta clase encapsula la equivalencia con `Solicitada`; el caso de uso la aplica a las filas leídas y conserva separados el total del archivo y las candidatas. La infraestructura no conoce la regla de estado y la capa HTTP continúa limitada a transformar la petición y la respuesta.

RF-04 añade `Domain/Shipment/NacexShipmentIdentity` para construir la clave temporal mediante agencia ajustada a cuatro dígitos y albarán. `Application/Actions/DeduplicateNacexCsvRows` agrupa los DTO después del filtro de estado y devuelve un resultado tipado con candidatas únicas y conflictos. Los DTO de conflicto conservan todas las filas originales para revisión; el dominio no depende de los DTO de aplicación y la infraestructura continúa limitada al parsing.

RF-05 añade `Domain/Shipment/NacexShipmentNormalizer` como única fuente de normalización para agencia, código postal y referencia. `Application/Actions/NormalizeNacexCsvRows` transforma únicamente las filas deduplicadas en `NacexCsvCandidate`, que mantiene el DTO fuente y expone los valores preparados para requisitos posteriores. `NacexShipmentIdentity` reutiliza la misma normalización de agencia, evitando reglas duplicadas.

RF-06 añade `Domain/Shipment/NacexTrackingUrlBuilder`, configurado desde `config/nacex.php` mediante el contenedor, para encapsular el formato exacto y la codificación de la URL. `Application/Actions/BuildNacexTrackingUrls` la incorpora únicamente a las candidatas normalizadas y devuelve `NacexShipmentCandidate`, que conserva la candidata RF-05 junto a su URL. La infraestructura de CSV y la interfaz HTTP no conocen las reglas de construcción, y esta fase no realiza llamadas externas.

La primera pieza de RF-07 es `Domain/Matching/OrderDateCompatibility`. Recibe la fecha de alta textual y la fecha de creación del pedido como instante; compara días de calendario en `Europe/Madrid` dentro de la ventana inclusiva configurada en `config/nacex.php`. `FindShopifyOrdersForNacexDate` y el análisis de RF-09 consumen esta regla.

La infraestructura de RF-07 añade `ShopifyStoreEndpoint` para limitar las solicitudes HTTPS al subdominio configurado y a la versión Admin GraphQL `2026-10`, `ShopifyClientCredentialsTokenProvider` para intercambiar las credenciales de la aplicación por un token temporal, y `ShopifyAdminGraphqlClient` para enviar operaciones GraphQL autenticadas. Los fallos se encapsulan mediante una excepción de aplicación con mensajes seguros; no se guarda el token fuera de memoria. `ShopifyOrderReader` implementa el contrato de aplicación homónimo y adapta pedidos paginados por la ventana temporal de dominio; `FindShopifyOrdersForNacexDate` coordina ambas piezas. El endpoint CSV utiliza esta lectura, que no modifica pedidos.

RF-08 utiliza `RecipientNameCompatibility`, que reutiliza la normalización existente y concentra la regla de prefijo normal e inverso. `FindShippingAddressMatchesForShipment` combina esa regla con código postal y fecha sobre los pedidos leídos. El valor máximo de la dirección inversa está en `config/nacex.php`. La infraestructura utiliza el nombre completo de envío o, si falta, nombre y apellidos de la misma dirección; no requiere aún `read_customers`.

RF-09 añade `ShipmentMatchStatus`, `ShipmentAnalysisResult` y `AnalyzeNacexShipments`. El caso de uso elimina pedidos cancelados, impide seleccionar coincidencias múltiples, aplica la precedencia de tracking sobre pedido preparado y bloquea parciales, múltiples órdenes de cumplimiento, cumplimientos inconcluyentes y estados no procesables. Solo una coincidencia `UNFULFILLED`, sin cumplimientos y con una única orden `OPEN` queda como `MATCHED_SAFE`. Los fallos de fecha o comunicación se convierten en `ERROR` por fila sin detener el lote.

`ShopifyOrderSnapshot` transporta exclusivamente los datos temporales necesarios para esa decisión. `ShopifyOrderReader` comprueba que la lista de cumplimientos esté completa, recoge todos los números de seguimiento devueltos y detecta más de una orden de cumplimiento con una lectura limitada a dos nodos. La consulta fue validada contra Admin GraphQL `2026-10`. `FindShopifyOrdersForNacexDate` reutiliza la lectura para fechas NACEX iguales durante una única petición, sin caché persistente.

`ValidateNacexCsv` coordina ahora RF-01 a RF-09 y devuelve los resultados de análisis desde el endpoint existente. Este cambio no añade lógica de negocio al controlador ni al navegador, y no introduce operaciones de escritura sobre Shopify.

RF-11 amplía `ShopifyStoreEndpoint` con la construcción validada del enlace administrativo de un pedido. `ShopifyOrderReader` incorpora esa URL al snapshot temporal únicamente después de aceptar un GID exacto de tipo `Order`; una respuesta con otro recurso, ID no numérico o ruta añadida falla de forma segura. No cambia la operación GraphQL ni añade permisos.

RF-12 a RF-18 añaden `PrepareNacexCsv` como preparación compartida para impedir que análisis y ejecución diverjan. `ExecuteSelectedNacexShipments` vuelve a localizar cada identidad en el CSV, revalida coincidencia y estado, aplica precedencias idempotentes y delega la única escritura posible en el contrato `ShopifyFulfillmentGateway`. La infraestructura consulta el pedido exacto y ejecuta `fulfillmentCreate` con todos los artículos restantes de una única orden válida. Cada excepción de comunicación se transforma en un resultado de fila y el lote continúa.

El endpoint de ejecución recibe el mismo archivo y una selección mínima; nunca acepta del navegador la URL NACEX, el estado del pedido ni una orden de cumplimiento. Los DTO `ShopifyOrderRevalidation`, `ShopifyFulfillmentOrder` y `FulfillmentExecutionResult` mantienen explícito el contrato entre aplicación e infraestructura. No se añade Eloquent ni persistencia.

RF-19 conserva en Vue el cruce temporal entre las filas analizadas y los resultados ejecutados. `GenerateExecutionReportCsv` recibe DTO `ExecutionReportRow`, aplica el contrato fijo de 16 columnas y devuelve bytes CSV directamente; `ExecutionReportController` no guarda el contenido ni utiliza sesión. La capa HTTP valida un máximo de 1.000 filas, responde con `Cache-Control: no-store` y no interviene en ninguna decisión ni operación de Shopify.

## Estructura frontend

Todo trabajo sobre esta capa debe comenzar leyendo `DOC/FRONTEND.md` y la skill `$emil-design-eng` completa.

```text
resources/js/
├── Components/            Componentes reutilizables y presentacionales
├── Composables/           Estado e interacción reutilizable de Vue
├── Layouts/               Estructuras visuales compartidas
├── Pages/                 Páginas servidas mediante Inertia
├── Types/                 Contratos TypeScript de datos de interfaz
└── app.ts                 Punto de entrada de Vue e Inertia
```

Actualmente están implementados `app.ts`, la página `Home.vue`, el panel de carga CSV, la vista previa y selección `AnalysisPreview.vue`, el panel de confirmación, resultados y descarga `FulfillmentExecutionPanel.vue`, los composables de validación y selección, la validación compartida de enlaces administrativos y los contratos TypeScript del flujo. Las demás carpetas se crearán únicamente cuando una funcionalidad concreta las necesite.

`useCsvValidation` actúa como adaptador del límite HTTP: solo entrega al componente resultados con el contrato reconocido. Mantiene compatibilidad transitoria con respuestas anteriores durante actualizaciones locales desacompasadas y rechaza filas, estados o pedidos estructuralmente desconocidos con un mensaje operativo, evitando fallos de renderizado.

`CsvUploadPanel.vue` conserva la carga, el feedback y las métricas. `AnalysisPreview.vue` representa RF-10 y los controles RF-11. `useShipmentSelection` encapsula selección automática, asignación y exclusión reversible. `FulfillmentExecutionPanel.vue` muestra la confirmación RF-12, bloquea dobles envíos y representa RF-18; envía el CSV y la selección al backend, pero no decide si un pedido es seguro. Todo el estado se pierde al recargar.

Los componentes utilizarán el formato Single-File Component estándar:

```vue
<script setup lang="ts">
// Estado y composición del componente.
</script>

<template>
  <!-- Marcado del componente. -->
</template>

<style scoped>
/* Solo cuando el componente necesite estilos propios. */
</style>
```

Este formato está permitido y no se considera una mezcla indebida. Está prohibido incluir PHP o Blade dentro de un componente Vue, así como colocar lógica de dominio en la plantilla o en el navegador.

Las decisiones visuales, de interacción, movimiento, rendimiento y accesibilidad se rigen por `DOC/FRONTEND.md`.

## Clean Code

- Una clase, acción o componente debe tener una responsabilidad reconocible.
- Los nombres deben expresar intención y utilizar el vocabulario del dominio.
- Los controladores, componentes y métodos deben mantenerse pequeños y delegar responsabilidades especializadas.
- Deben evitarse booleanos ambiguos, arrays sin contrato, cadenas mágicas y estados duplicados.
- Los resultados del matching y procesamiento deben representarse mediante tipos o enums definidos centralmente.
- Las dependencias externas deben quedar encapsuladas para poder sustituirse por dobles durante las pruebas.
- No se permitirán archivos que acumulen parsing, matching, acceso a Shopify y presentación.
- No se crearán helpers genéricos ni abstracciones sin un uso concreto.

## Estrategia de pruebas

```text
tests/
├── Unit/                  Normalización, matching, precedencia y dominio
└── Feature/               CSV, endpoints Inertia y adaptación de Shopify
```

Las llamadas a Shopify deben simularse en las pruebas. Ninguna prueba automatizada debe modificar una tienda real.

## Configuración y secretos

- El subdominio, ID y secreto de cliente de la aplicación instalada se proporcionan mediante variables de entorno; el token de acceso se obtiene en el backend y no se persiste.
- Ninguna credencial se almacenará en Git, documentación, logs o frontend.
- La versión de Shopify Admin API debe centralizarse en configuración.
- Los parámetros constantes de la URL de NACEX deben centralizarse en configuración.

Los nombres de variables y la versión inicial se documentan en `DOC/BUSQUEDA_PEDIDOS.md` y `README.md`.

## Despliegue

La primera ejecución funciona en XAMPP mediante `http://localhost/gestor-seguimientos/public`. Apache y PHP 8.3.33 sirven la aplicación, y los recursos frontend se generan con Vite.

La sesión utiliza el driver cifrado `cookie` exclusivamente para mantener CSRF y mensajes operativos entre peticiones. Está prohibido guardar en ella el CSV, datos personales o resultados del análisis. La caché permanece en memoria (`array`) y la cola es síncrona.

Los resultados de validación y análisis se devolverán directamente al frontend y permanecerán únicamente en el estado en memoria de Vue. Se perderán al recargar o cerrar la página. Esta estrategia evita base de datos, archivos de sesión con datos operativos y persistencia en el navegador.

La adaptación posterior a hosting se diseñará cuando se conozcan sus capacidades. No se asumirán versión de PHP, servidor web, acceso por terminal ni mecanismo de despliegue.

## Decisiones todavía necesarias

- Librería visual y sistema de estilos.
- Capacidades y restricciones del hosting definitivo.

El MVP local conserva el indicador único de operación en curso y no añadirá progreso detallado por fila: el volumen previsto no tendrá lotes largos. La adaptación a hosting queda aplazada hasta que el usuario decida retomarla. No deben implementarse las demás áreas afectadas hasta que la decisión correspondiente haya sido confirmada.
