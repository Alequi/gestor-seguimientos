# Arquitectura y estructura del proyecto

## Estado

La base técnica y la importación inicial están implementadas. Laravel, Inertia, Vue y TypeScript están integrados; RF-01 valida la estructura, RF-02 adapta las filas a DTO inmutables, RF-03 filtra las candidatas y RF-04 las deduplica sin automatizar grupos contradictorios.

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

Actualmente están implementados `app.ts`, la página `Home.vue`, el panel de carga CSV, su composable de validación y los contratos TypeScript de archivo, resultado y fila NACEX. Las demás carpetas se crearán únicamente cuando una funcionalidad concreta las necesite.

`useCsvValidation` actúa como adaptador del límite HTTP: solo entrega al componente resultados con el contrato completo. Mantiene compatibilidad transitoria con respuestas RF-03 durante actualizaciones locales desacompasadas y rechaza respuestas estructuralmente incompletas con un mensaje operativo, evitando fallos de renderizado.

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

- El dominio y el token de la tienda se proporcionarán mediante variables de entorno.
- Ninguna credencial se almacenará en Git, documentación, logs o frontend.
- La versión de Shopify Admin API debe centralizarse en configuración.
- Los parámetros constantes de la URL de NACEX deben centralizarse en configuración.

Los nombres definitivos de variables y la versión inicial de la API se documentarán antes de implementar la integración.

## Despliegue

La primera ejecución funciona en XAMPP mediante `http://localhost/gestor-seguimientos/public`. Apache y PHP 8.3.33 sirven la aplicación, y los recursos frontend se generan con Vite.

La sesión utiliza el driver cifrado `cookie` exclusivamente para mantener CSRF y mensajes operativos entre peticiones. Está prohibido guardar en ella el CSV, datos personales o resultados del análisis. La caché permanece en memoria (`array`) y la cola es síncrona.

Los resultados de validación y análisis se devolverán directamente al frontend y permanecerán únicamente en el estado en memoria de Vue. Se perderán al recargar o cerrar la página. Esta estrategia evita base de datos, archivos de sesión con datos operativos y persistencia en el navegador.

La adaptación posterior a hosting se diseñará cuando se conozcan sus capacidades. No se asumirán versión de PHP, servidor web, acceso por terminal ni mecanismo de despliegue.

## Decisiones todavía necesarias

- Versión inicial de Shopify Admin GraphQL API.
- Estrategia de progreso durante la ejecución del lote.
- Librería visual y sistema de estilos.
- Capacidades y restricciones del hosting definitivo.

No deben implementarse las áreas afectadas hasta que la decisión correspondiente haya sido confirmada.
