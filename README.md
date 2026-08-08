# Gestor de seguimientos NACEX → Shopify

Herramienta interna para importar expediciones desde un CSV de NACEX, localizar los pedidos correspondientes en Shopify y crear sus cumplimientos con número y URL de seguimiento.

## Estado del proyecto

La arquitectura y la base técnica están implementadas. RF-01 permite seleccionar, arrastrar y validar estructuralmente un CSV de NACEX; RF-02 lee sus seis campos como texto; RF-03 conserva las filas `Solicitada`; RF-04 deduplica agencia y albarán, apartando para revisión los grupos contradictorios; RF-05 normaliza las candidatas sin perder sus valores fuente; y RF-06 construye su URL de seguimiento NACEX. Todavía no se consulta ni modifica Shopify.

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
- Aviso seguro y accesible cuando el CSV no contiene expediciones candidatas.
- Feedback accesible con nombre, tamaño, codificación y filas validadas.
- Procesamiento temporal sin guardar el archivo ni sus datos.

## Objetivo del MVP

El flujo previsto es:

1. Cargar un CSV de NACEX.
2. Validar y normalizar sus datos.
3. Procesar únicamente expediciones con estado `Solicitada`.
4. Buscar pedidos candidatos en Shopify mediante nombre, código postal y fecha.
5. Mostrar una vista previa y permitir la revisión del usuario.
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
│   ├── Application/               Casos de uso, contratos y DTO de archivo y fila NACEX
│   ├── Domain/Shipment/            Reglas de dominio de expediciones y estados
│   ├── Http/                      Controladores, requests y middleware
│   └── Infrastructure/Nacex/      Lectura e inspección del CSV
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

No existe carpeta `database/`, modelo Eloquent ni autenticación. RF-01 y RF-02 utilizan `Application` e `Infrastructure`; RF-03 incorpora la regla del estado `Solicitada`; RF-04 añade la identidad temporal de expedición y un caso de uso de deduplicación con conflictos tipados; RF-05 incorpora una normalización de dominio centralizada y candidatas que conservan su DTO fuente; y RF-06 encapsula la construcción de URLs en dominio y las incorpora desde un caso de uso de aplicación.

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

`APP_URL` y `ASSET_URL` deben apuntar a `http://localhost/gestor-seguimientos/public` en XAMPP para que las rutas y los módulos dinámicos de Vite resuelvan la subcarpeta correcta.

Las variables de Shopify y la versión de Admin GraphQL API siguen pendientes. Las futuras credenciales deberán permanecer fuera del código, documentación, logs y archivos versionados.

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
