# ImportaChina

Aplicación web para gestionar un catálogo de productos importados desde AliExpress: catálogo, carrito, pedidos, pagos, usuarios con roles y reportes con exportación a PDF.

- **Backend:** Laravel 12 / PHP 8.3
- **Base de datos:** SQLite por defecto (MySQL opcional)
- **Frontend:** Blade + Tailwind CSS + Vite
- **Tablero:** [Gestión - ImportaChina](https://github.com/users/antoavila97/projects/3/views/1) (público)
- **Documentación:** [`docs/`](docs/) y [`PENDIENTES.md`](PENDIENTES.md)

## Requisitos

- PHP **8.2+** (recomendado 8.3) con las extensiones `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl` y `dom`.
- [Composer](https://getcomposer.org/) 2.x.
- [Node.js](https://nodejs.org/) **20.19+** o **22.12+** y npm.
- Git.

En Windows podés usar Laragon o XAMPP; en Linux/macOS, el PHP de tu distribución o Homebrew.

## Instalación rápida (SQLite, sin instalar MySQL)

```bash
git clone https://github.com/antoavila97/importachina.git
cd importachina
composer setup
php artisan serve
```

Abrí <http://127.0.0.1:8000>.

`composer setup` hace todo:

1. Instala las dependencias de PHP y Node.
2. Copia `.env.example` a `.env` si todavía no existe.
3. Genera la `APP_KEY`.
4. Crea `database/database.sqlite`.
5. Ejecuta las migraciones y los seeders.
6. Carga el catálogo y las ventas de demostración.
7. Compila los assets con Vite en `public/build`.

## Usuarios de prueba (instalación local)

| Email | Contraseña | Rol |
|---|---|---|
| `admin@importachina.com` | `password` | Administrador |
| `vendedor@importachina.com` | `password` | Vendedor |
| `cliente@importachina.com` | `password` | Cliente |

> En el despliegue de producción las mismas cuentas usan `ImportaChina#2026`.
> Cambiá la contraseña desde **Admin → Usuarios** si vas a publicar el sitio.

## Instalación manual

Si preferís hacer los pasos uno por uno:

```bash
git clone https://github.com/antoavila97/importachina.git
cd importachina

composer install
cp .env.example .env                 # Windows CMD: copy .env.example .env
php artisan key:generate

php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"

php artisan migrate --seed
php artisan db:seed --class=DemoSalesSeeder --force

npm ci
npm run build

php artisan serve
```

### Usar MySQL en vez de SQLite

Editá `.env` **antes** de migrar:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=importachina
DB_USERNAME=root
DB_PASSWORD=
```

Creá la base `importachina` en MySQL y después ejecutá:

```bash
php artisan migrate --seed
php artisan db:seed --class=DemoSalesSeeder --force
```

### Reiniciar la base local

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSalesSeeder --force
```

## Comandos útiles

```bash
php artisan test          # 282 pruebas
php artisan serve         # servidor en http://127.0.0.1:8000
npm run dev               # Vite con recarga en caliente
npm run build             # compila CSS/JS para producción
php artisan migrate       # aplica migraciones nuevas
```

## Problemas comunes

- **`could not find driver`**: falta `pdo_sqlite` (o `pdo_mysql` si usás MySQL). Habilitala en `php.ini`.
- **`No application encryption key has been specified`**: ejecutá `php artisan key:generate`.
- **`Vite manifest not found`**: ejecutá `npm ci && npm run build`.
- **La base SQLite no existe**: ejecutá `php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"` y volvé a migrar.
- **Cambiaste el puerto**: actualizá `APP_URL` en `.env` (por defecto `http://localhost:8000`).

## Estructura del proyecto

```
app/                 Controladores, modelos, middleware y servicios
database/migrations  Esquema de la base
database/seeders     Roles, usuarios, catálogo y ventas de demo
docs/                Requerimientos, historias de usuario y ceremonies
resources/views      Vistas Blade
routes/web.php       Rutas de la aplicación
tests/               Pruebas de funcionalidad
```

## Despliegue

- **Railway:** <https://importachina-production.up.railway.app>
- El despliegue usa las variables de entorno del panel; `.env` **no** se sube al repositorio.
- El arranque ejecuta `php artisan migrate --force --seed` y `php artisan optimize`.

## Gestión del proyecto

- Tablero principal: <https://github.com/users/antoavila97/projects/3/views/1>
- Copia del tablero en la cuenta de ella: <https://github.com/users/zabalapereiraa1-ui/projects/3/views/1>
- Historias de usuario: [`docs/tablero.md`](docs/tablero.md) y [`docs/16_historias_de_usuario_ImportaChina.txt`](docs/16_historias_de_usuario_ImportaChina.txt)
