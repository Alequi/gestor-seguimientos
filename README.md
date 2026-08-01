# Gestor de seguimientos NACEX → Shopify

Herramienta interna para importar expediciones desde un CSV de NACEX, localizar los pedidos correspondientes en Shopify y crear sus cumplimientos con número y URL de seguimiento.

## Estado del proyecto

La arquitectura y la base técnica están implementadas. Laravel 13, Inertia 3, Vue 3 y TypeScript funcionan en XAMPP; el desarrollo funcional del flujo NACEX → Shopify todavía no ha comenzado.

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
│   └── Http/Middleware/           Integración HTTP de Inertia
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

No existe carpeta `database/`, modelo Eloquent ni autenticación. Las capas `Domain`, `Application` e `Infrastructure` se crearán cuando el primer caso de uso las necesite; no se mantienen carpetas vacías ni abstracciones especulativas.

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

No debe ejecutarse `php artisan migrate`: la aplicación no utiliza base de datos propia. La guía detallada y las copias de respaldo del entorno están en [DOC/ENTORNO_DESARROLLO.md](DOC/ENTORNO_DESARROLLO.md).

Después se adaptará el despliegue a un hosting cuyas capacidades todavía están pendientes de concretar.

## Configuración

`.env.example` contiene únicamente valores locales sin secretos y utiliza sesiones/caché en memoria y cola síncrona. El `.env` real está ignorado por Git.

Las variables de Shopify, la versión de Admin GraphQL API y el mecanismo definitivo para mantener resultados temporales siguen pendientes. Las futuras credenciales deberán permanecer fuera del código, documentación, logs y archivos versionados.

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
