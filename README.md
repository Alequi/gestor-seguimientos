# Gestor de seguimientos NACEX → Shopify

Herramienta interna para importar expediciones desde un CSV de NACEX, localizar los pedidos correspondientes en Shopify y crear sus cumplimientos con número y URL de seguimiento.

## Estado del proyecto

El flujo operativo hasta el resultado por pedido está implementado. RF-01 a RF-11 validan, buscan, clasifican, presentan y seleccionan; RF-12 a RF-18 confirman, revalidan cada pedido y crean de forma idempotente el cumplimiento NACEX. RF-19, el informe descargable, sigue pendiente.

## Funcionalidad disponible

- Selección o arrastre de un archivo `.csv`.
- Validación de tipo, extensión y límite de 5 MB.
- Lectura de UTF-8, Windows-1252 e ISO-8859-1.
- Separador obligatorio `;`.
- Comprobación de las seis cabeceras NACEX obligatorias.
- Rechazo de archivos vacíos o con más de 1.000 filas de datos.
- Lectura de cada fila respetando el entrecomillado CSV y los `;` incluidos dentro de campos.
- Conservación de tildes, `ñ`, `ü`, ceros iniciales y albaranes largos sin conversión numérica.
- Transporte temporal de agencia, albarán, fecha, referencia, código postal y estado como cadenas UTF-8.
- Filtrado de `Solicitada` sin distinguir mayúsculas, minúsculas ni espacios sobrantes.
- Conteo separado de filas totales y candidatas antes de deduplicar.
- Deduplicación mediante agencia ajustada a cuatro dígitos y albarán, recortando espacios exteriores solo para comparar.
- Conservación de una única candidata cuando las repeticiones contienen los mismos datos.
- Exclusión segura y aviso de revisión cuando una misma identidad presenta fechas, referencias o códigos postales contradictorios.
- Agencia normalizada a cuatro dígitos y código postal a cinco, siempre como texto.
- Referencia de comparación en minúsculas, sin tildes y con los signos convertidos en espacios, conservando `ñ` y la referencia original.
- URL de seguimiento NACEX preparada con agencia normalizada y albarán textual, conservando ceros iniciales.
- Regla temporal de RF-07, cliente backend y lector paginado de pedidos por fecha con Admin GraphQL `2026-10`, conectados al endpoint CSV en modo de solo lectura.
- Comparación de RF-08 por destinatario de envío, código postal y fecha. La coincidencia inversa permite como máximo dos caracteres adicionales al final del mismo término normalizado.
- Clasificación RF-09 en `MATCHED_SAFE`, `NO_MATCH`, `AMBIGUOUS`, `ALREADY_FULFILLED`, `TRACKING_EXISTS`, `FULFILLMENT_EXISTS`, `PARTIAL_FULFILLMENT` o `ERROR`.
- Precedencia de `TRACKING_EXISTS` sobre `ALREADY_FULFILLED`, bloqueo de cancelados, parciales, múltiples órdenes de cumplimiento y cualquier estado no concluyente.
- Aislamiento de errores por expedición y reutilización temporal de lecturas para filas con la misma fecha.
- Vista previa compacta RF-10 con cabecera fija, desplazamiento horizontal, candidatos ambiguos visibles y etiquetas textuales para cada resultado.
- Enlaces de seguimiento NACEX y apertura del pedido Shopify desde la vista previa, sin acciones de modificación.
- Selección inicial exclusiva de coincidencias seguras, selección y deselección manual, y exclusión reversible de filas.
- Asignación manual de candidatos ambiguos mediante radios seguida de una selección explícita independiente.
- Apertura del pedido en Shopify mediante una URL administrativa validada y sin credenciales.
- Confirmación explícita con recuento de pedidos y notificaciones antes de modificar Shopify.
- Notificación al cliente activada por defecto y solicitada únicamente cuando se crea un cumplimiento nuevo.
- Relectura del CSV y reconstrucción backend del número y la URL de seguimiento.
- Revalidación individual de coincidencia, cancelación, seguimiento, estado y orden de cumplimiento justo antes de escribir.
- Creación del cumplimiento NACEX para todos los artículos restantes de una única orden de cumplimiento válida.
- Resultados independientes `COMPLETED`, `TRACKING_EXISTS`, `ALREADY_FULFILLED`, `SKIPPED_AFTER_REVALIDATION` y `ERROR`.
- Aislamiento de errores y reintento manual con una nueva revalidación, sin detener el resto del lote.
- Aviso seguro y accesible cuando el CSV no contiene expediciones candidatas.
- Feedback accesible con nombre, tamaño, codificación y filas validadas.
- Procesamiento temporal sin guardar el archivo ni sus datos.

## Objetivo del MVP

El flujo previsto es:

1. Cargar un CSV de NACEX.
2. Validar y normalizar sus datos.
3. Procesar únicamente expediciones con estado `Solicitada`.
4. Buscar pedidos candidatos en Shopify mediante nombre, código postal y fecha.
5. Mostrar una vista previa, seleccionar coincidencias seguras y revisar o asignar manualmente los casos ambiguos.
6. Revalidar cada pedido seleccionado.
7. Crear el cumplimiento con el seguimiento de NACEX.
8. Mostrar y descargar los resultados de la sesión.

## Principios del producto

- Shopify es la fuente de verdad.
- No se utiliza una base de datos propia.
- La seguridad prevalece sobre el porcentaje de automatización.
- Las coincidencias ambiguas requieren revisión y asignación manual.
- Los cambios en Shopify se revalidan inmediatamente antes de ejecutarse.
- Un CSV puede contener como máximo 1.000 filas y utiliza `;` como separador.
- La herramienta es interna y no incorpora autenticación en el MVP.
- Cada fila se procesa de forma independiente para que un error no detenga el lote.
- Durante el desarrollo no se asumirán requisitos ni decisiones ausentes: si falta información o contexto, el trabajo se detendrá para consultarlo con el usuario.

## Documentación

Este repositorio utiliza documentación viva. El punto de entrada obligatorio es [DOC/INDEX.md](DOC/INDEX.md).

Documentos principales:

- [PRD del producto](PRD_integracion_NACEX_Shopify_sin_BBDD.md)
- [Requisitos funcionales](requisitos_funcionales_nacex_shopify.md)
- [Arquitectura y estructura](DOC/ARQUITECTURA.md)
- [Frontend y diseño de interacción](DOC/FRONTEND.md)
- [Entorno de desarrollo](DOC/ENTORNO_DESARROLLO.md)
- [Importación de CSV NACEX](DOC/IMPORTACION_CSV.md)
- [Búsqueda de pedidos en Shopify](DOC/BUSQUEDA_PEDIDOS.md)
- [Confirmación y cumplimientos Shopify](DOC/CUMPLIMIENTOS.md)
- [Instrucciones para agentes](AGENTS.md)

## Stack aprobado

- PHP 8.3 o superior.
- Laravel 13.
- Inertia 3.
- Vue 3 con TypeScript y componentes `.vue` estándar.
- Shopify Admin GraphQL API.

No se utilizarán Filament, SSR ni una base de datos propia en el MVP. La aplicación atenderá a una única tienda Shopify.

El diseño y la implementación del frontend utilizarán obligatoriamente la skill `$emil-design-eng`. Sus criterios de pulido, interacción, movimiento, rendimiento y accesibilidad están recogidos en [DOC/FRONTEND.md](DOC/FRONTEND.md).

Todo cambio o nueva funcionalidad debe actualizar la documentación relacionada, el índice y este README cuando corresponda. Un cambio sin documentación se considera incompleto.

## Estructura actual

```text
.
├── .agents/                       Documentación operativa para agentes
├── app/                           Backend Laravel
│   ├── Application/               Casos de uso, contratos y DTO de NACEX y lectura Shopify
│   ├── Domain/Shipment/            Reglas de dominio de expediciones y estados
│   ├── Domain/Matching/            Reglas temporales, comparación y estados de análisis
│   ├── Http/                      Controladores, requests y middleware
│   ├── Infrastructure/Nacex/      Lectura e inspección del CSV
│   └── Infrastructure/Shopify/    Autenticación, transporte y lectura GraphQL
├── bootstrap/                     Arranque de Laravel
├── config/                        Configuración del framework
├── DOC/                           Documentación viva e índice
├── public/                        Punto de entrada público y build frontend
├── resources/
│   ├── css/                       Estilos frontend
│   ├── js/                        Vue, TypeScript y páginas Inertia
│   └── views/                     Documento raíz de Inertia
├── routes/                        Rutas web y de consola
├── storage/                       Archivos temporales y logs locales
├── tests/                         Pruebas automatizadas
├── AGENTS.md                      Normas obligatorias de desarrollo
├── composer.json                  Dependencias y scripts PHP
├── package.json                   Dependencias y scripts frontend
├── PRD_integracion_...md          Definición del producto
├── requisitos_funcionales_...md  Requisitos funcionales
├── vite.config.ts                 Integración Vite/Vue/Inertia sin SSR
└── README.md                      Entrada general al proyecto
```

No existe carpeta `database/`, modelo Eloquent ni autenticación de usuarios. RF-01 a RF-11 cubren preparación, lectura, matching y selección; RF-12 a RF-18 revalidan y ejecutan mediante un contrato de cumplimiento separado. Los controladores permanecen delgados, el CSV se vuelve a preparar en backend y los resultados siguen solo en memoria.

## Instalación y ejecución

Requisitos locales verificados:

- PHP 8.3.33 con `curl`, `fileinfo`, `mbstring`, `openssl`, `PDO`, `xml` y `zip`.
- Composer 2.10.2.
- Node.js 22.14.0 y npm 10.9.2.
- Apache de XAMPP.

Preparación inicial desde la raíz:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm ci
npm run build
```

Con Apache iniciado, la aplicación queda disponible en:

```text
http://localhost/gestor-seguimientos/public
```

Uso operativo:

1. Carga y analiza el CSV de NACEX.
2. Revisa las coincidencias seguras y asigna manualmente cualquier caso ambiguo que quieras incluir.
3. Ajusta la selección y pulsa **Completar pedidos**.
4. Comprueba el recuento, decide si notificar y pulsa **Confirmar y completar**.
5. Revisa el resultado de cada fila; reintenta únicamente los errores después de comprobar Shopify.

La confirmación realiza cambios reales en la tienda configurada. Antes del primer uso, verifica en una tienda u orden de prueba que la aplicación instalada dispone de los scopes de escritura indicados en Configuración.

Para trabajar con recarga de recursos frontend:

```powershell
npm run dev
```

En XAMPP, el código PHP y `public/build` deben actualizarse juntos. Si se copia únicamente un build nuevo sobre un backend anterior, la interfaz conserva una compatibilidad defensiva con la respuesta RF-03, pero RF-04 solo estará activo cuando ambas capas correspondan a la misma versión.

No debe ejecutarse `php artisan migrate`: la aplicación no utiliza base de datos propia. La guía detallada y las copias de respaldo del entorno están en [DOC/ENTORNO_DESARROLLO.md](DOC/ENTORNO_DESARROLLO.md).

Después se adaptará el despliegue a un hosting cuyas capacidades todavía están pendientes de concretar.

## Configuración

`.env.example` contiene únicamente valores locales sin secretos. Usa una sesión cifrada en cookie solo para CSRF, caché en memoria y cola síncrona. El `.env` real está ignorado por Git.

Los límites compartidos por RF-01 a RF-06 se centralizan en:

```dotenv
NACEX_CSV_MAX_SIZE_KB=5120
NACEX_CSV_MAX_ROWS=1000
```

El formato del seguimiento NACEX y sus parámetros constantes se mantienen en `config/nacex.php`; no requieren secretos ni variables de entorno.

La ventana temporal de RF-07 también está en `config/nacex.php`: desde 30 días antes hasta 7 días después de `Fecha alta`, incluidos ambos límites, comparando la fecha del pedido en `Europe/Madrid`. El CSV de referencia utiliza `DD/MM/AAAA`. La regla está preparada, probada y se usa en el análisis backend del CSV.

El límite de coincidencia inversa de RF-08 está en `config/nacex.php`: dos caracteres Unicode adicionales como máximo, sin una palabra nueva, después de normalizar ambos nombres. Siguen siendo obligatorios el mismo código postal, la fecha compatible y un único candidato. RF-09 comprueba además estado, cumplimientos, órdenes de cumplimiento y seguimientos existentes antes de devolver `MATCHED_SAFE`.

`APP_URL` y `ASSET_URL` deben apuntar a `http://localhost/gestor-seguimientos/public` en XAMPP para que las rutas y los módulos dinámicos de Vite resuelvan la subcarpeta correcta.

La versión inicial de Shopify Admin GraphQL API es `2026-10`. El usuario confirmó una aplicación existente en Dev Dashboard e instalada en una tienda de la misma organización. El backend prepara el flujo de credenciales de cliente y utiliza estas variables:

```dotenv
SHOPIFY_SHOP=
SHOPIFY_CLIENT_ID=
SHOPIFY_CLIENT_SECRET=
```

`SHOPIFY_SHOP` requiere solo el subdominio anterior a `.myshopify.com`, sin protocolo ni ruta. Los valores reales se guardan únicamente en el `.env` local ignorado por Git o como variables del entorno de ejecución; no se deben compartir por chat ni añadir a documentación o archivos versionados. El usuario ya los configuró localmente y se comprobó su presencia sin mostrarlos. El token obtenido se mantiene solo en memoria hasta poco antes de su caducidad. El cliente omite el campo GraphQL `variables` cuando está vacío, porque Shopify rechaza una lista vacía. El lector recupera todas las páginas de pedidos dentro de la ventana de fechas y falla si la respuesta es incompleta. La ejecución revalida el pedido exacto y utiliza `fulfillmentCreate`, ambas operaciones validadas contra el esquema `2026-10`.

Para cerrar pedidos, la aplicación instalada debe tener `read_orders` y los scopes de lectura y escritura de órdenes de cumplimiento aplicables: `read_assigned_fulfillment_orders`/`write_assigned_fulfillment_orders`, `read_merchant_managed_fulfillment_orders`/`write_merchant_managed_fulfillment_orders` o `read_third_party_fulfillment_orders`/`write_third_party_fulfillment_orders`, según el modelo de la tienda. Si se cambian scopes, la instalación o autorización de la aplicación debe actualizarse antes de operar.

La conexión se comprobó con la consulta de solo lectura `shop { id }` a Shopify Admin GraphQL `2026-10`, sin mostrar el identificador ni el token. Esta prueba confirma autenticación y acceso básico a la API; **no confirma todavía los permisos reales de lectura y escritura de cumplimientos**. Las pruebas de desarrollo no mutan la tienda.

En XAMPP, un `.htaccess` en la raíz bloquea por HTTP `.env` y todas las rutas del proyecto salvo `public/`; se comprobaron respuestas `403` para `.env` y `200` para la aplicación. En otro servidor, debe publicarse solo `public/` y volver a verificar esta protección antes de configurar secretos.

## Desarrollo

Antes de modificar el proyecto debe seguirse este orden:

```text
DOC/INDEX.md → documentación relacionada → código → actualización documental → README.md → verificación
```

Antes de modificar código también debe confirmarse que toda la información necesaria está definida. Una ambigüedad o un dato ausente obliga a detener el desarrollo y preguntar; no se aplican supuestos ni valores elegidos por conveniencia.

El proyecto seguirá Clean Code y separación estricta de responsabilidades. PHP/Laravel, infraestructura, casos de uso y componentes Vue deben mantenerse en sus capas correspondientes. Los componentes `.vue` utilizarán el estándar oficial de Vue, pero no incluirán PHP ni Blade.

Comprobaciones disponibles:

```powershell
vendor\bin\pint --test
php artisan test
npm run typecheck
npm run build
composer validate --strict --no-check-publish
composer audit --locked
npm audit
```

No se deben realizar operaciones contra una tienda real, despliegues, commits o pushes salvo petición explícita.
