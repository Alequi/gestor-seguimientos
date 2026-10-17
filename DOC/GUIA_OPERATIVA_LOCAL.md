# Guía operativa local

## Estado y alcance

Esta guía corresponde a la versión estable local `v1.0.0` del gestor de seguimientos NACEX → Shopify. La aplicación se utiliza mediante XAMPP en el equipo configurado y no requiere base de datos, migraciones, cola externa ni hosting remoto.

El flujo completo, los permisos de Shopify y la presentación en escritorio y móvil fueron validados por el usuario antes de cerrar esta versión. La etiqueta Git `v1.0.0` identifica el código y la documentación de este punto estable; no contiene el `.env`, credenciales ni datos operativos.

## Arranque diario

1. Abre el panel de XAMPP.
2. Inicia Apache. MySQL no es necesario para esta aplicación.
3. Comprueba que Apache permanece en ejecución y sin errores.
4. Abre `http://localhost/gestor-seguimientos/public`.
5. Confirma que aparece la pantalla “Prepara los seguimientos con seguridad”.

La compilación de producción ya se sirve desde `public/build`; no es necesario iniciar Vite para el uso cotidiano.

## Flujo operativo

1. Selecciona o arrastra el CSV exportado desde NACEX.
2. Pulsa **Analizar CSV** y espera a que finalice la consulta de solo lectura.
3. Revisa coincidencias, conflictos y casos ambiguos. Asigna manualmente un candidato solo cuando hayas comprobado el pedido.
4. Ajusta la selección y pulsa **Completar pedidos**.
5. Comprueba el recuento y decide si Shopify debe solicitar la notificación al cliente.
6. Pulsa **Confirmar y completar** una sola vez. Cada pedido se revalidará antes de cualquier modificación.
7. Revisa los resultados. Antes de reintentar un error, comprueba el estado actual del pedido en Shopify.
8. Descarga el informe CSV y guárdalo donde corresponda si necesitas conservarlo. La aplicación no mantiene una copia.

Recargar o cerrar la página elimina el CSV, las selecciones y los resultados que solo existen en memoria. Un error en una fila no detiene las demás.

## Cierre diario

1. Descarga el informe si necesitas conservar el resultado de la sesión.
2. Cierra la página de la aplicación.
3. Detén Apache desde XAMPP cuando ninguna otra aplicación local lo necesite.

No hay una base de datos que cerrar, exportar o respaldar.

## Comprobaciones ante un problema

- Si la página no abre, confirma que Apache está iniciado y utiliza la URL completa terminada en `/public`.
- Si los recursos visuales no cargan, comprueba que existe `public/build/manifest.json` y que `APP_URL` y `ASSET_URL` apuntan a la URL local documentada.
- Si Shopify rechaza la conexión, no reintentes repetidamente una modificación. Comprueba la configuración local, la instalación de la aplicación y sus permisos sin copiar credenciales a chats, capturas o incidencias.
- Si una ejecución devuelve una respuesta incierta, revisa primero el pedido en Shopify. La revalidación y la detección de seguimiento protegen el reintento, pero Shopify continúa siendo la fuente de verdad.
- Los mensajes compartidos para soporte no deben contener nombres, códigos postales, albaranes reales, tokens ni el contenido del `.env`.

## Actualización de la aplicación

Antes de actualizar:

1. Confirma que no hay una ejecución en curso.
2. Conserva una copia protegida del `.env` fuera del directorio publicado y fuera del repositorio.
3. Comprueba qué versión o commit se va a instalar y revisa sus notas.

Para actualizar el código de `main`, desde la raíz del proyecto:

```powershell
git pull --ff-only origin main
composer install
npm ci
npm run build
php artisan test
npm run typecheck
```

No ejecutes `php artisan migrate`: el proyecto no utiliza base de datos. Después de actualizar, abre la aplicación y realiza una comprobación controlada antes de utilizar un CSV operativo.

## Copia segura

Git y la etiqueta `v1.0.0` protegen el código versionado, pero no el entorno local. Mantén por separado:

- una copia del `.env` en una ubicación privada, cifrada o con acceso restringido, situada fuera de `C:\xampp\htdocs`;
- una referencia a la versión instalada, inicialmente `v1.0.0`;
- las copias de PHP y npm ya enumeradas en `DOC/ENTORNO_DESARROLLO.md` mientras sigan siendo necesarias.

No añadas el `.env` a Git, archivos comprimidos compartidos, documentación ni servicios sin control de acceso. Los informes descargados pueden contener datos personales y deben almacenarse conforme al procedimiento interno correspondiente; nunca se incorporan al repositorio.

## Recuperación de la versión estable

Antes de recuperar una versión anterior, conserva cualquier cambio local que debas mantener y detén toda ejecución. La etiqueta estable se puede inspeccionar con:

```powershell
git show --stat v1.0.0
```

Para restaurar el servicio, utiliza una copia limpia del repositorio situada en `v1.0.0`, reinstala las dependencias con los archivos bloqueados del proyecto, recompila el frontend y recupera el `.env` desde su copia protegida. No sustituyas ni borres a ciegas el directorio actual si contiene cambios no guardados.

Después de recuperar:

1. Confirma `APP_URL`, `ASSET_URL` y la configuración Shopify sin exponer sus valores.
2. Ejecuta las verificaciones documentadas en `DOC/ENTORNO_DESARROLLO.md`.
3. Comprueba la respuesta local y realiza una prueba controlada antes del uso operativo.

## Límites vigentes

- Uso local mediante XAMPP.
- Sin autenticación en el MVP interno.
- Sin base de datos ni historial recuperable desde la aplicación.
- Sin progreso detallado por fila para lotes, porque el volumen previsto es corto.
- Sin adaptación a hosting hasta que se defina expresamente ese nuevo alcance.
