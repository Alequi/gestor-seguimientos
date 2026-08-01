# Gestor de seguimientos NACEX → Shopify

Herramienta interna para importar expediciones desde un CSV de NACEX, localizar los pedidos correspondientes en Shopify y crear sus cumplimientos con número y URL de seguimiento.

## Estado del proyecto

El proyecto se encuentra en fase de arquitectura. El stack y la estructura base ya están aprobados, pero la implementación todavía no ha comenzado.

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
├── DOC/                           Documentación viva e índice
├── AGENTS.md                      Normas obligatorias de desarrollo
├── DOC/ARQUITECTURA.md            Stack y estructura aprobados
├── DOC/FRONTEND.md                Diseño e interacción del frontend
├── PRD_integracion_...md          Definición del producto
├── requisitos_funcionales_...md  Requisitos funcionales
└── README.md                      Entrada general al proyecto
```

## Instalación y ejecución

La primera ejecución se realizará en XAMPP. Antes de crear el proyecto deben actualizarse PHP 8.0.30 a una versión compatible con Laravel 13 y el entorno npm, que actualmente no funciona correctamente.

Después se adaptará el despliegue a un hosting cuyas capacidades todavía están pendientes de concretar.

## Configuración

Pendiente de definir. Las futuras credenciales de Shopify deberán mantenerse fuera del código y de los archivos versionados.

## Desarrollo

Antes de modificar el proyecto debe seguirse este orden:

```text
DOC/INDEX.md → documentación relacionada → código → actualización documental → README.md → verificación
```

Antes de modificar código también debe confirmarse que toda la información necesaria está definida. Una ambigüedad o un dato ausente obliga a detener el desarrollo y preguntar; no se aplican supuestos ni valores elegidos por conveniencia.

El proyecto seguirá Clean Code y separación estricta de responsabilidades. PHP/Laravel, infraestructura, casos de uso y componentes Vue deben mantenerse en sus capas correspondientes. Los componentes `.vue` utilizarán el estándar oficial de Vue, pero no incluirán PHP ni Blade.

No se deben realizar operaciones contra una tienda real, despliegues, commits o pushes salvo petición explícita.
