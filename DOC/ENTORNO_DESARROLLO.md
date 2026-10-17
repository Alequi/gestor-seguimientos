# Entorno de desarrollo

## Estado

Entorno local preparado para ejecutar la aplicación completa desde XAMPP. El 17 de octubre de 2026 el usuario confirmó la validación del flujo operativo completo con Shopify y mantuvo este entorno como destino de uso del MVP.

## Versiones instaladas

| Componente | Versión |
|---|---|
| XAMPP | 8.0.30 como distribución base |
| Apache | 2.4.58 |
| PHP | 8.3.33 Thread Safe x64, VS16 |
| Composer | 2.10.2 |
| Node.js | 22.14.0 |
| npm | 10.9.2 |
| Laravel Framework | 13.23.0 |
| Inertia Laravel | 3.0.0 |
| Inertia Vue/Vite | 3.6.1 |
| Vue | 3.5.40 |
| TypeScript | 5.9.3 |
| Vite | 7.3.6 |

PHP tiene habilitadas las extensiones requeridas `curl`, `fileinfo`, `mbstring`, `openssl`, `PDO`, `xml` y `zip`.

## Copias de respaldo

- La instalación anterior completa de PHP 8.0.30 se conserva en `C:\xampp\php-8.0.30-backup-20260802`.
- La carpeta npm global incompleta que interfería con los comandos se conserva en `C:\Users\aleja\AppData\Roaming\npm\node_modules\npm-broken-backup-20260802`.

Estas copias no forman parte del proyecto y no deben eliminarse hasta confirmar que ya no se necesita una reversión del entorno.

## Preparación del proyecto

Desde la raíz del repositorio:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm ci
npm run build
```

No se ejecuta ninguna migración: el proyecto no utiliza base de datos propia. El archivo `.env` es local, está ignorado por Git y no debe compartirse.

Como el repositorio está dentro de `C:\xampp\htdocs` y Apache publica esa carpeta, ignorar `.env` en Git no basta para protegerlo por HTTP. El `.htaccess` de la raíz del proyecto deniega las rutas ajenas a `public/`, además de bloquear expresamente `.env` y sus variantes. Se verificó solo el código HTTP: antes de esta protección la ruta local de `.env` respondía `200`; ahora `.env`, `README.md`, `storage/logs/laravel.log` y `vendor/autoload.php` responden `403`, mientras `public/` sigue respondiendo `200`. No se descargó ni mostró el contenido de `.env`.

## Ejecución en XAMPP

Con el servicio Apache iniciado, la compilación de producción se sirve en:

```text
http://localhost/gestor-seguimientos/public
```

`APP_URL` y `ASSET_URL` deben apuntar a esa URL en el entorno XAMPP. `ASSET_URL` permite que los módulos Vue cargados de forma dinámica resuelvan su JavaScript y CSS desde la subcarpeta correcta. Ambos valores deberán adaptarse al dominio y directorio público del hosting definitivo antes de compilar allí.

En el hosting definitivo, el directorio publicado debe ser `public/`. No se debe confiar únicamente en el `.htaccess` de XAMPP para proteger secretos en otro servidor web; hay que volver a comprobar que `.env`, `storage/`, `vendor/` y la documentación no son accesibles por HTTP antes de cargar credenciales reales.

Durante el desarrollo frontend puede mantenerse Vite activo en otra terminal:

```powershell
npm run dev
```

Como alternativa aislada de Apache, `composer dev` inicia simultáneamente el servidor de Laravel y Vite.

## Comandos de verificación

```powershell
vendor\bin\pint --test
php artisan test
npm run typecheck
npm run build
composer validate --strict --no-check-publish
composer audit --locked
npm audit
```

La verificación inicial confirmó formato PHP correcto, una prueba Feature de la respuesta Inertia, TypeScript sin errores, compilación de producción correcta, respuesta HTTP 200 desde Apache y ausencia de vulnerabilidades conocidas en las auditorías de Composer y npm.

## Configuración provisional sin persistencia

`.env.example` utiliza:

- `SESSION_DRIVER=cookie`, solo para CSRF y mensajes operativos.
- `CACHE_STORE=array`.
- `QUEUE_CONNECTION=sync`.
- `NACEX_CSV_MAX_SIZE_KB=5120`.
- `NACEX_CSV_MAX_ROWS=1000`.

Estos valores permiten arrancar sin base de datos. El CSV y sus resultados no se guardan en la cookie: se devuelven directamente a Vue y permanecen solo en memoria hasta recargar o cerrar la página.

## Adaptación posterior

El hosting definitivo se ha aplazado expresamente y la aplicación se utilizará localmente en XAMPP. Cuando se retome la adaptación deberán confirmarse como mínimo su versión de PHP, extensiones disponibles, servidor web, acceso por terminal, posibilidad de ejecutar Composer/npm y estructura de publicación del directorio `public/`.
