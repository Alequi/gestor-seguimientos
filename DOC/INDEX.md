# Índice de documentación viva

Este archivo es la puerta de entrada obligatoria a la documentación del proyecto.

Antes de iniciar cualquier cambio o nueva funcionalidad, se debe leer este índice, consultar la documentación relacionada y, solo después, revisar el código. Al terminar, se debe actualizar el documento existente o crear uno nuevo y enlazarlo aquí.

## Documentos de producto

| Documento | Descripción | Estado |
|---|---|---|
| [README del proyecto](../README.md) | Entrada general al repositorio: estado, alcance, estructura, configuración y uso. Debe mantenerse actualizado durante todo el desarrollo. | Vivo |
| [PRD — Integración NACEX con Shopify](../PRD_integracion_NACEX_Shopify_sin_BBDD.md) | Visión, alcance, reglas de negocio, flujo, restricciones y criterios de aceptación del MVP. | Vigente |
| [Requisitos funcionales](../requisitos_funcionales_nacex_shopify.md) | Requisitos funcionales RF-01 a RF-20 y flujo funcional resumido. | Vigente |

## Documentación técnica y funcional

| Documento | Descripción | Estado |
|---|---|---|
| [Arquitectura y estructura](ARQUITECTURA.md) | Stack aprobado, separación de responsabilidades, estructura prevista y normas de Clean Code. | Vigente |
| [Frontend y diseño de interacción](FRONTEND.md) | Criterios obligatorios de interfaz, componentes, movimiento, rendimiento y accesibilidad basados en `$emil-design-eng`. | Vigente |
| [Entorno de desarrollo](ENTORNO_DESARROLLO.md) | Versiones instaladas, preparación de XAMPP, puesta en marcha, verificaciones y copias de respaldo. | Vigente |
| [Importación de CSV NACEX](IMPORTACION_CSV.md) | Contrato, validaciones, lectura textual, filtrado, deduplicación segura, conflictos, interfaz y límites de la carga de archivos NACEX (RF-01 a RF-04). | Vigente |

## Decisiones confirmadas posteriores a los documentos de producto

Estas decisiones deben incorporarse al documento temático correspondiente cuando se cree o actualice:

- Las coincidencias ambiguas pueden asignarse manualmente después de ser revisadas.
- Una asignación manual debe revalidarse antes de modificar Shopify.
- `TRACKING_EXISTS` prevalece sobre `ALREADY_FULFILLED` cuando ambos estados sean aplicables.
- La herramienta interna no tendrá autenticación en el MVP.
- Los CSV tendrán un máximo de 1.000 filas.
- El separador esperado es punto y coma (`;`).
- Está prohibido asumir requisitos, datos, contexto o decisiones no documentadas. Si falta información, el desarrollo debe detenerse y solicitar una decisión al usuario.
- El stack aprobado es Laravel 13 sobre PHP 8.3 o superior, Inertia 3, Vue 3 y TypeScript.
- Vue utilizará componentes `.vue` estándar; se prohíbe mezclar PHP o Blade con los componentes Vue.
- No se utilizarán Filament ni SSR en el MVP.
- Shopify se integrará mediante Admin GraphQL API para una única tienda.
- El despliegue inicial será XAMPP y posteriormente se adaptará a un hosting compatible.
- La estructura y el código deben seguir `DOC/ARQUITECTURA.md`; el código spaghetti y la mezcla de responsabilidades están prohibidos.
- Todo trabajo de frontend debe utilizar la skill `$emil-design-eng` y seguir `DOC/FRONTEND.md`.
- El entorno inicial utiliza PHP 8.3.33 Thread Safe x64, Composer 2.10.2, Node.js 22.14.0 y npm 10.9.2.
- La sesión cifrada en cookie se utiliza únicamente para CSRF y mensajes operativos; nunca contendrá el CSV, datos de clientes ni resultados del análisis.
- Los datos devueltos por la validación o el análisis permanecerán solo en memoria en Vue y se perderán al recargar la página.
- RF-04 compara expediciones mediante agencia ajustada a cuatro dígitos y albarán, recortando solo espacios exteriores. Si una misma identidad presenta fechas, referencias o códigos postales contradictorios, todo el grupo queda fuera del procesamiento automático y se señala para revisión.

## Decisiones pendientes

- Definición exacta de compatibilidad entre la fecha del pedido y la fecha de alta de NACEX.
- Diferencia máxima admitida para la coincidencia inversa entre referencia y nombre de Shopify.

No debe implementarse ningún comportamiento dependiente de estas decisiones hasta que el usuario las confirme. Esto es independiente del comportamiento funcional de la aplicación, que enviará a revisión manual los casos dudosos durante su ejecución.

## Norma de mantenimiento

- Actualiza un documento existente cuando ya cubra el área modificada.
- Crea un documento nuevo únicamente cuando introduzca un área claramente distinta.
- Todo documento nuevo debe enlazarse desde este índice.
- Revisa el `README.md` en cada cambio y actualízalo siempre que afecte al estado, funcionalidades, instalación, configuración, uso o estructura del proyecto.
- No se considera terminado un cambio de código sin su correspondiente actualización documental y la revisión del README.
- Si falta información o hay más de una interpretación posible, detén el desarrollo y pregunta; no elijas una opción por defecto.
- No incluyas secretos, credenciales, datos personales ni muestras reales de clientes.
