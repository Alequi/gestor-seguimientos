# Entorno de desarrollo

## Estado

Entorno local preparado y verificado el 2 de agosto de 2026 para ejecutar la base Laravel desde XAMPP.

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

## Ejecución en XAMPP

Con el servicio Apache iniciado, la compilación de producción se sirve en:

```text
http://localhost/gestor-seguimientos/public
```

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

- `SESSION_DRIVER=array`.
- `CACHE_STORE=array`.
- `QUEUE_CONNECTION=sync`.

Estos valores permiten arrancar sin base de datos. No resuelven todavía el mecanismo definitivo para mantener un análisis entre peticiones; esa decisión continúa pendiente y deberá documentarse antes de implementar el flujo del CSV.

## Adaptación posterior

El hosting definitivo sigue sin estar definido. Antes de adaptarlo deben confirmarse como mínimo su versión de PHP, extensiones disponibles, servidor web, acceso por terminal, posibilidad de ejecutar Composer/npm y estructura de publicación del directorio `public/`.
