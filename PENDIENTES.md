# PENDIENTES — ImportaChina

> **Última actualización:** 4 de octubre de 2026 — **punto 8 terminado**. El bug de HU-01
> está corregido y cubierto: el registro público asigna el rol `Cliente` y hay tests que
> **fallan si el bug vuelve**. De paso se completaron los tests de HU-01 y HU-02, que
> antes eran los de Breeze y no assertaban ni un criterio.
>
> **Al volver:** por el **punto 7**. Lo más grande sigue siendo el **documento de
> requerimientos** (§1-2 de la guía 4.1), que no existe.
>
> Apareció un **hallazgo nuevo**: las pantallas de Breeze (login, registro, perfil,
> dashboard) están **en inglés** y no hay carpeta `lang/`. Ver "PUNTO 9".
>
> Lo único del **punto 5** que falta es un trámite externo: las credenciales de AliExpress.
> Estado verificado con `php artisan test`: **211 passing (691 assertions)**.

### ✅ PUNTO 8 — HU-01 no asignaba el rol Cliente (HECHO)

**Era el pendiente más urgente del proyecto.** No estaba en ninguna sesión anterior.

`app/Http/Controllers/Auth/RegisteredUserController.php` creaba al usuario con
`User::create()` pasando solo `name`, `email` y `password`. **No asignaba `role_id`**, así
que la columna quedaba en `NULL`.

Verificado empíricamente (test temporal que registró un usuario por `/register`):

```
[HU-01] role_id tras registro publico = NULL
[HU-01] hasRole(Cliente) = false
[HU-01] isActive = true
```

El criterio de aceptación de **HU-01** decía *"Al registrarme, mi cuenta queda con el rol
Cliente"*. **No se cumplía.** El usuario podía comprar, pero el sistema no lo sabía: para
`User::hasRole('Cliente')` era un usuario **sin rol**.

Por qué no se notó: no había ningún error visible. `role_id` es nullable en la migración, el
panel de administración toleraba el rol vacío (la lista responde 200) y nadie miraba esa
columna para un alta por registro público. Es la misma categoría de bug que el del punto 6:
**el código "parecía" completo y la pantalla no avisaba.**

#### El arreglo

`app/Http/Controllers/RegisteredUserController.php` — `store()`:

```php
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'role_id' => Role::firstOrCreate(['name' => self::ROL_POR_DEFECTO])->id,
    'status' => User::STATUS_ACTIVE,
]);
```

Dos detalles deliberados:

- **`firstOrCreate` y no `->value('id')`.** El `PENDIENTES` viejo proponía
  `Role::where('name', 'Cliente')->value('id')`, pero eso devuelve `null` si el rol no
  existe y volvemos al mismo bug. `firstOrCreate` es el patrón que ya usan
  `RoleSeeder` y `UserFactory::role()`, así que además coincide con el código del proyecto.
- **`'status'` explícito.** La migración ya pone `default('active')`, pero `create()` no
  recarga el modelo, así que el objeto en memoria quedaba con `status = null` — **exactamente
  el bug del punto 3**. `Auth::login()` guarda ese objeto en el guard de inmediato, así que
  el fallo se producía en la misma petición del registro. Ahora hay un test que asserta
  `Auth::user()->isActive()` después del POST, que es donde se nota.

#### Los tests: 30 nuevos, y se verificó que fallan sin el arreglo

Esto es lo importante: **un test que no falla sin el bug no prueba nada.** Se comprobó
comentando el arreglo en el controlador y corriendo la suite.

| Archivo | Antes | Ahora | Qué asserta |
|---|---|---|---|
| `tests/Feature/Auth/RegistrationTest.php` | 2 (Breeze) | **14** | HU-01 completa |
| `tests/Feature/Auth/AuthenticationTest.php` | 4 (Breeze) | **22** | HU-02 completa |

**HU-01** — los dos criterios de la guía 4.3, sobre el efecto real en la base:

- `role_id` no es `null` y el rol es `Cliente`; `hasRole('Cliente')` es `true`.
- **No hay escalamiento:** aunque el POST mande `role_id` de Administrador a mano, el
  registro público siempre deja `Cliente`.
- El rol `Cliente` se **reutiliza**, no se duplica al registrar dos personas.
- `Auth::user()->isActive()` es `true` en memoria (el caso del bug del punto 3).
- Correo **único**, mínimo **8 caracteres** (y que 8 exactos se aceptan), confirmación
  obligatoria, correo inválido rechazado, nombre obligatorio, contraseña hasheada.
- Un Cliente **no** entra a `/admin/*` ni a `/vendedor/*` (403).

**HU-02** — los dos criterios:

- Credenciales correctas → entra, y la página de destino **de verdad responde 200**.
- Credenciales incorrectas / correo inexistente → **el mensaje de error se ve en pantalla**
  (se asserta `__('auth.failed')`, no un literal, para no atar el test al idioma).
- Un usuario **desactivado** no puede entrar aunque la contraseña sea correcta (HU-03 lo
  permite desactivar; ese cierre faltaba y ahora está cubierto).
- **Criterio 2, que antes no se comprobaba:** las 7 páginas privadas de los módulos
  (`/dashboard`, `/carrito`, `/mis-pedidos`, `/profile`, `/vendedor/pedidos`,
  `/admin/usuarios`, `/admin/reportes`) con un `@DataProvider` de ruta + rol:
  - sin sesión → todas rebotan al login;
  - con sesión y el rol correcto → abren con 200;
  - **después de cerrar sesión → vuelven a estar cerradas.**

  La segunda mitad es la que faltaba: los tests de Breeze solo hacían `assertGuest()`
  después del logout, sin comprobar que las páginas Privatizadas quedaran cerradas de verdad.

⚠️ **Los tests siembran `RoleSeeder` en `setUp()`.** Con `RefreshDatabase` los seeders **no**
corren, así que sin roles en la base el registro no podría asignar ninguno. Se sembró
explícitamente para que el escenario sea determinista y no dependa del `firstOrCreate` del
propio código bajo prueba.

Verificado contra la suite: **211 passing (691 assertions)**, antes 181.

### ✅ Bug 2 — Las cuatro guías de la raíz no están commiteadas

`git status` las muestra como `??` (untracked):

```
?? "4.0. Guia principal.txt"
?? "4.1. Guía de desarrollo_ ImportaChina con Laravel, PHP y MySQL.html"
?? "4.2. Guías paso a paso_ Trello y Kanban.html"
?? "4.3. 16 historias de usuario_ ImportaChina.html"
```

Las HTML de **4.2 y 4.3 solo existen ahí**: en `docs/` únicamente están las versiones `.txt`
(`guia del proyecto.txt`, `guias_paso_a_paso_Trello_y_Kanban.txt`, etc.), que son
**contenido distinto**, no las HTML. O sea: **las guías 4.2 y 4.3 no están en GitHub.**

Mientras no se commiteen, el repositorio no respalda el material de la práctica. Hay que
decidir la fuente de verdad (ver punto 7) y **commitear lo que se conserve**.

### ✅ Bug 3 — Este archivo mentía en dos cifras (resuelto)

- Decía que `AccessControlTest` tiene **30 tests**. Tiene **26**. (7 métodos, pero tres de
  ellos llevan `@DataProvider` y se expanden.)
- Decía que **HU-01, HU-02 y HU-09 no tienen pruebas**. HU-01 y HU-02 **sí las tienen**, en
  `tests/Feature/Auth/RegistrationTest.php` (2) y `tests\Feature\Auth\AuthenticationTest.php`
  (4): son las de Breeze y la tabla de cobertura de abajo las omitía.

Lo que sigue siendo cierto es que **esas pruebas no assertan los criterios de las historias**:
`RegistrationTest` registraba un usuario pero no comprobaba que el correo sea único, que la
contraseña exija 8 caracteres ni que el rol quede en `Cliente`. Justamente por eso el bug del
punto 8 pasó inadvertido: **el test de HU-01 existía y pasaba, y aun así HU-01 no se cumplía.**

👉 **Resuelto el 4 de octubre:** los dos archivos se reescribieron y ahora assertan los
criterios de las historias (14 y 22 tests). Ver el punto 8 arriba. Lo que queda de este
bug es solo la frase de la auditoría: **el hueco real es HU-09**, que sigue sin pruebas.

### ✅ Resultado de la auditoría contra las guías 4.0 a 4.3

**16 de 16 historias cumplen** (el 4 de octubre, con el punto 8 cerrado). El proyecto está en
buen estado; lo que falta es documental.

| Guía | Requisito | Estado verificado |
|---|---|---|
| 4.0 | Tablero público "Gestión - ImportaChina", 5 columnas | ⬜ externo al repo |
| 4.0 | ≥4 historias: 1 Admin, 1 Vendedor, 2 Cliente | ✅ hay 16 con el formato exacto |
| 4.0 | ≥1 historia que dependa de la API | ✅ HU-05 y HU-09 |
| 4.0 | 2 wireframes en Figma | ⬜ externo al repo |
| 4.0 | Claves de la API solo en el servidor | ✅ `config/services.php`, todo por `env()` |
| 4.1 §1 | 12-16 requerimientos funcionales + 4 no funcionales | ⬜ **no existe el documento** |
| 4.1 §1 | Roles: Administrador, Vendedor, Cliente | ✅ son los 3 de `RoleSeeder` |
| 4.1 §2 | 16 historias, ≥2 criterios, puntos 1/2/3/5/8, 45-55 pts | ✅ **52 pts**, ninguno >8 |
| 4.1 §2 | Reparto Admin 4-5 / Vendedor 3 / Cliente 7-8 | ✅ 5 / 3 / 8, exacto |
| 4.1 §3 | Sprint 1-4 con 10-12 / 13-15 / 13-15 / 8-10 pts | ✅ 12 / 15 / 15 / 10, exacto |
| 4.1 §4 | 8-10 wireframes escritorio y móvil | ⬜ externo al repo |
| 4.1 §5 | 11 tablas con esas columnas | ✅ **las 11 exactas**, ver abajo |
| 4.1 §6 | Repo + `main` protegida + rama por historia + PR | ⬜ **todo en `master`, sin ramas ni PR** |
| 4.1 §7 | Modelos con relaciones, controladores delgados, Form Requests | ✅ 11 modelos, 10 Form Requests |
| 4.1 §8 | Servicio que firma, comando artisan, `api_sync_logs`, lotes 20-50 | ✅ implementado y probado |
| 4.1 §9 | CSRF en todos los formularios | ✅ los 3 sin `@csrf` son GET de filtrado |
| 4.1 §9 | `.env` nunca en el repositorio | ✅ en `.gitignore` y sin trackear |
| 4.1 §9 | `APP_DEBUG=false` en producción | ✅ un 404 real **no filtra nada** |
| 4.1 §9 | ≥1 prueba por historia, 12-16 total | ✅ **211** en total; HU-01 y HU-02 ya bien cubiertas, **HU-09 sin pruebas** |
| 4.1 §10 | Publicar con dominio público y migraciones | ✅ Railway vivo, `/catalogo` 200 |
| 4.1 §10 | `php artisan migrate --force` **y `optimize`** | 🟡 migraciones sí, `optimize` no |
| 4.1 §11 | Ceremonias ágiles registradas | ⬜ no hay registro |
| 4.2 | Tablero Trello con las 5 listas y reglas WIP | ⬜ externo, sin `docs/tablero.md` |
| 4.2 | Medir tiempo de ciclo y rendimiento | ⬜ sin datos |
| 4.3 | 16 historias con criterios y puntos | ✅ 52 pts en 4 sprints |

**Lo que la auditoría confirmó como correcto y conviene no romper:**

- **Las 11 tablas de §5 son exactamente las que pide la guía** (`roles`, `users`, `categories`,
  `products`, `product_images`, `carts`, `cart_items`, `orders`, `order_items`, `payments`,
  `api_sync_logs`). `roles` está **normalizado de verdad**: `users.role_id` es una FK, no un
  string. `external_id` es `unique` y `order_items` guarda el precio al momento de la compra.
- **El reparto de sprints coincide al punto** con la tabla de la guía. No hay que tocar nada.
- **Responsive:** 20 de 21 vistas tienen clases `sm:/md:/lg:`.
- **Rutas:** 42 de la app. La guía *sugiere* 20-30, es sugerencia y no requisito; con 42 rutas
  GET/POST/PUT/DELETE de 7 módulos es lo normal.

### ⚠️ Corrección de la última sesión

La suite **no** estaba en 87 passing: daba **29 failed / 58 passed**. Todas las pruebas de
admin y vendedor recibían **403**.

**Causa:** `UserFactory::definition()` no seteaba `status`. La migración sí pone
`default('active')` en la base, pero `create()` no recarga el modelo, así que el objeto en
memoria quedaba con `status = null` → `User::isActive()` daba `false` →
`RoleMiddleware` abortaba con 403.

**Arreglado** en `database/factories/UserFactory.php:33` agregando
`'status' => User::STATUS_ACTIVE`. En ese momento: 87 passing (231 assertions).

**Lección:** el "87 passing" del punto 3 estaba escrito en este archivo pero nunca se
verificó contra la suite. Un número en un `.md` no es evidencia; correr el test sí.

### ⚠️ Segunda corrección: el punto 4 también mentía

El punto 4 decía "los tres controladores estaban vacíos". No era exacto: `UserController`
y `CategoryController` estaban completos, pero **las seis vistas no existían**, y encima
 había un bug de rutas que entregaba modelos **vacíos** en las pantallas de editar.
Detalle en la sección del punto 4.

### ⚠️ Tercera corrección: este archivo vuelve a mentir (3 de octubre, auditoría 4.0-4.3)

La sección "Tests agregados en los puntos 1 a 4" afirmaba dos cosas falsas:

1. `AccessControlTest` "30 tests" → son **26**.
2. "HU-01, HU-02 y HU-09 no tienen pruebas" → HU-01 y HU-02 **sí**, en
   `tests/Feature/Auth/`. La tabla **omitía 18 tests de Breeze** y por eso la cuenta
   cuadtaba sobre papel y mal contra la suite.

**Y el bug más grave está en la misma línea:** el test de HU-01 **existe, pasa, y HU-01
sigue sin cumplirse**. Un test de Breeze que verifica "el usuario se registra" no verifica
"el usuario queda con el rol Cliente". Son cosas distintas.

**Lección (la misma de siempre, agora por tercera vez):** un test que se limita a comprobar
que la pantalla responde 200 **no prueba el criterio de aceptación**. Hay que assertar el
efecto: el `role_id`, el estado, el precio calculado. Y una tabla de cobertura hay que
contarla con `php artisan test`, no de memoria.

---

## Cómo levantar el proyecto

```bash
php artisan serve
```

MySQL local: el de **Laragon** en el puerto **3307** (`C:\laragon\bin\mysql`). Si no
responde, se levanta a mano con:

```powershell
Start-Process "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" `
  -ArgumentList "--defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini" -WindowStyle Hidden
```

Usuarios (en local la contraseña es `password`):

| Email | Rol |
|---|---|
| `admin@importachina.com` | Administrador |
| `vendedor@importachina.com` | Vendedor |
| `cliente@importachina.com` | Cliente |

En **Railway** los tres usan `ImportaChina#2026` (ver "Publicar en Railway").

Datos de demo cargados (14 pedidos + pagos en 30 días) para poder ver el reporte.
Si se borran: `php artisan db:seed --class=DemoSalesSeeder`

---

## Estado de avance

| Punto | Tarea | Estado |
|---|---|---|
| 1 | Arreglar bugs que bloqueaban el sitio | ✅ Hecho |
| 2 | HU-16 reporte de ventas | ✅ Hecho |
| 3 | **HU-12 + HU-13 + HU-15 + rol Vendedor** | ✅ Hecho |
| 4 | CRUD admin (HU-03, HU-06, HU-07) | ✅ **Hecho** |
| 5 | HU-05 API real de AliExpress | ✅ **Hecho** — solo falta el App Key (trámite externo) |
| 6 | HU-04 teléfono y dirección en perfil | ✅ **Hecho** |
| 7 | Mejoras menores | 🟡 A medias — falta lo que dice abajo |
| **8** | **Auditoría 4.0-4.3: HU-01 no asignaba el rol Cliente** | ✅ **Hecho** — corregido y cubierto con 30 tests |
| **9** | **Idioma: pantallas de Breeze en inglés, sin `lang/`** | 🔴 **Pendiente — nuevo** |
| — | **Publicar en Railway** | ✅ **Hecho** — https://importachina-production.up.railway.app |

---

# ✅ PUNTO 1 — Bugs que bloqueaban el sitio (HECHO)

Auditoría inicial: ~55% implementado. Se encontraron **4 bugs fatales** que hacían
que casi nada funcionara, aunque el código "pareciera" completo.

| Archivo | Bug | Efecto |
|---|---|---|
| `app/Http/Middleware/RoleMiddleware.php` | llave `}` sin pareja (línea 33) | **500 en todo `/admin/*`** |
| `resources/views/layouts/navigation.blade.php` | `Auth::user()->name` sin guarda | **500 en `/catalogo` para visitantes** |
| `resources/views/layouts/app.blade.php` | faltaba `@yield('content')` | **6 vistas renderizaban vacío** |
| `app/Http/Controllers/Controller.php` | sin trait `AuthorizesRequests` | **500 al cambiar/eliminar del carrito** y al ver pedidos |

**El último lo detectó el smoke test, no la lectura de código.** HU-10 figuraba como
"implementada" pero era 100% inusable: `CartController::update()` y `destroy()` llaman
`$this->authorize()`, que no existía.

### Lo que se corrigió además

- **Mensajes flash nunca mostrados.** Los controladores enviaban `->with('success')` /
  `->with('error')` pero ninguna vista los renderizaba. Se creó el componente
  `<x-flash-messages />` (`resources/views/components/flash-messages.blade.php`)
  y se insertó en: carrito, checkout, detalle de pedido, historial, catálogo, api-sync.
- **Estados de pedido en crudo.** Mostraban `pendiente` en vez de `Pendiente`.
  Se agregó `Order::statusLabel()` y se aplicó en `orders/index` y `orders/show`.
- **Navegación.** Se reemplazó el link único "Dashboard" por: Catálogo, Carrito,
  Mis pedidos, y (solo admin) Reportes + Sincronización API. Con fallback de
  Iniciar sesión / Registrarse para visitantes.
- **Encoding:** se verificó byte a byte que todo es UTF-8 válido. El texto "raro"
  que se veía era la consola de PowerShell, **no un bug del código**.

---

# ✅ PUNTO 2 — HU-16 Reporte de ventas (HECHO)

### Archivos creados

| Archivo | Rol |
|---|---|
| `app/Http/Controllers/Admin/ReportController.php` | Lógica del reporte |
| `app/Http/Requests/SalesReportRequest.php` | Validación del rango de fechas |
| `resources/views/admin/reports/index.blade.php` | Vista |
| `database/seeders/DemoSalesSeeder.php` | Datos de demo |
| `tests/Feature/SalesReportTest.php` | 7 tests |

Ruta: `GET /admin/reportes` → `admin.reports.index`, dentro del grupo
`role:Administrador`.

### Criterios de aceptación de HU-16

- ✅ "Puedo elegir fecha inicial y final y ver el total vendido."
  → KPIs: Total vendido, Pedidos vendidos, Ticket promedio, Unidades vendidas.
- ✅ "Veo los 5 productos más vendidos del periodo."
  → Tabla con imagen, unidades e ingresos, ranking por unidades.

### Decisiones de diseño

- Cuentan como venta solo pedidos **Pagado / Enviado / Entregado**
  (`Order::SOLD_STATUSES`). Pendiente **no** cuenta.
- Rango por defecto: últimos 30 días.
- Extra: gráfico de barras por día (CSS puro, sin dependencias).
- Validación en FormRequest (guía 4.1 §7), no en el controlador.
- `Order` ganó: `SOLD_STATUSES`, `isSold()`, `statusLabel()`.

---

# ✅ PUNTO 3 — HU-12, HU-13, HU-15 + rol Vendedor (HECHO)

Se optó por la opción **(a)** sugerida: grupo nuevo `/vendedor`, separado del panel
`/admin`. Con un matiz: el middleware es `role:Administrador,Vendedor`, porque si fuera
`role:Vendedor` el Administrador se quedaba **sin** gestión de pedidos (el resource
`admin/pedidos` estaba vacío y se eliminó).

### Archivos creados

| Archivo | Rol |
|---|---|
| `app/Http/Controllers/Vendedor/OrderController.php` | Lista, detalle y cambio de estado |
| `app/Http/Controllers/Vendedor/PaymentController.php` | Registro de pago (HU-13) |
| `app/Http/Requests/UpdateOrderStatusRequest.php` | Valida el estado contra `Order::STATUSES` |
| `app/Http/Requests/RegisterPaymentRequest.php` | Valida método, monto y fecha |
| `resources/views/vendedor/pedidos/index.blade.php` | Lista con filtros |
| `resources/views/vendedor/pedidos/show.blade.php` | Detalle (HU-15) |
| `tests/Feature/SellerOrderTest.php` | 21 tests |

### Archivos eliminados

| Archivo | Por qué |
|---|---|
| `app/Http/Controllers/Admin/OrderController.php` | 7 métodos con cuerpo `//`; nunca hizo nada |
| `app/Http/Controllers/PaymentController.php` | Reemplazado por `Vendedor\PaymentController` |

### Rutas

| Método | URI | Nombre |
|---|---|---|
| GET | `/vendedor/pedidos` | `vendedor.orders.index` |
| GET | `/vendedor/pedidos/{order}` | `vendedor.orders.show` |
| PUT | `/vendedor/pedidos/{order}/estado` | `vendedor.orders.status` |
| POST | `/vendedor/pedidos/{order}/pago` | `vendedor.payments.store` |

### Hole de seguridad cerrado

`POST /pedidos/{order}/pago` (nombre `payments.store`) **ya no existe**. Estaba en el
grupo `auth` sin filtro de rol, así que un cliente podía marcar su propio pedido como
pagado sin intervención del vendedor — contradiciendo HU-12 y HU-13. Ahora el pago solo
lo registra quien tiene rol Vendedor o Administrador. Hay un test que falla si alguien
recrea esa ruta (`test_la_ruta_de_pago_para_clientes_ya_no_existe`).

### Criterios de aceptación cumplidos

- ✅ **HU-12** — lista con cliente, total y estado; cambio entre Pendiente / Pagado /
  Enviado / Entregado (validados por FormRequest, no a mano).
- ✅ **HU-13** — registra método, monto y fecha; **el pedido pasa a Pagado** dentro de una
  transacción. El monto por defecto es el total del pedido.
- ✅ **HU-15** — detalle con productos, cantidades, dirección de envío, nombre y email
  del cliente. El botón "Volver a la lista" arrastra los filtros de la query string.

### Extras

- Filtros combinables: estado, rango de fechas y búsqueda por nombre/email del cliente.
- Contadores por estado en las tarjetas de arriba de la lista.
- `Order` ganó: `STATUSES`, `CLOSED_STATUSES`, `isClosed()`, `nextStatuses()`,
  `statusBadgeClass()`.
- Un pedido **Entregado** ya no ofrece el formulario de pago.
- `AccessControlTest` ahora cubre por separado las rutas de `/admin` y las de `/vendedor`,
  y verifica que un Vendedor recibe 403 en el panel de administrador.

---

# ✅ PUNTO 4 — CRUD admin: usuarios, productos, categorías (HECHO)

Las tres secciones del panel ya funcionan de punta a punta. Lo que había era una capa de PHP
a medias **más un bug de rutas que rompía el binding de modelos en las tres secciones**.

## Archivos creados

| Archivo | Rol |
|---|---|
| `resources/views/admin/users/index.blade.php` | Listado con filtros + paginación |
| `resources/views/admin/users/form.blade.php` | Alta/edición con rol, estado y contraseña |
| `resources/views/admin/categories/index.blade.php` | Listado con nº de productos |
| `resources/views/admin/categories/form.blade.php` | Alta/edición (HU-06) |
| `resources/views/admin/productos/index.blade.php` | Listado con filtros y nº de imágenes |
| `resources/views/admin/productos/form.blade.php` | Alta/edición con precio de venta calculado en vivo |
| `app/Http/Requests/UpdatePaymentRequest.php` | Valida la corrección de un pago |
| `tests/Feature/AdminUserManagementTest.php` | HU-03 (14 tests) |
| `tests/Feature/AdminCategoryManagementTest.php` | HU-06 (10 tests) |
| `tests/Feature/AdminProductManagementTest.php` | HU-07 (14 tests) |
| `tests/Feature/PaymentEditVoidTest.php` | Editar/anular pago (8 tests) |
| `tests/Feature/ProductDetailTest.php` | HU-08 galería (6 tests) |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `routes/web.php` | `except('show')` + `parameters()` en los 3 resources; rutas de editar/anular pago |
| `app/Http/Controllers/Admin/ProductController.php` | Era un stub de 7 métodos con cuerpo `//` |
| `app/Http/Controllers/Vendedor/PaymentController.php` | `update()` y `void()` |
| `app/Models/Payment.php` | Estados `completed`/`voided` y etiquetas |
| `app/Http/Controllers/CatalogController.php` | Carga `images` para la galería |
| `database/factories/PaymentFactory.php` | Estaba vacía, con cuerpo `//` |
| `resources/views/layouts/navigation.blade.php` | Links Productos / Categorías / Usuarios (desktop y móvil) |
| `resources/views/catalog/show.blade.php` | Galería, stock y enlace a AliExpress |
| `resources/views/vendedor/pedidos/show.blade.php` | Editar y anular cada pago |

## 🐛 El bug que casi nadie iba a encontrar

`Route::resource('usuarios', ...)` genera el parámetro **`{usuario}`**, pero el controlador
pide `User $user`. El *implicit route binding* de Laravel resuelve por **nombre del
parámetro**, no por tipo, así que no había coincidencia: el controlador recibía un
`new User()` **vacío**.

Cómo se Kaleighó: el test `test_el_administrador_editar_un_usuario_y_cambiar_su_rol`
devolvía 200 pero la página decía "Nuevo usuario" y todos los campos venían en blanco.
El bug estaba en los **tres** resources y también rompía `ProductRequest`, que hace
`$this->route('product')?->id` para el `Rule::unique(...)->ignore($productId)`: sin
binding, la regla de unicidad de `external_id` era inútil al editar.

Arreglo (`routes/web.php:44-56`):

```php
Route::resource('usuarios', UserController::class)
    ->names('users')
    ->parameters(['usuarios' => 'user'])   // URI en español, parámetro en inglés
    ->except('show');
```

**Lección:** los `Route::resource` con nombre en español necesitan `parameters()`
explícito. Sin eso, editar o borrar cualquier registro del panel no da error visible:
entrega un modelo vacío y el formulario se abre en blanco.

## Otros bugs corregidos

- **`Route::resource` generaba `show`** y ni `UserController` ni `CategoryController` lo
  tienen → `GET /admin/usuarios/5` daba error de método. Ahora los tres usan `except('show')`.
- **`sale_price` dead code.** `calculateSalePrice()`, `syncSalePrice()` y `profit()` existían
  pero nadie los llamaba: el `ProductRequest` derivado y el `ProductController` los usan ahora.
  Hay un test que manda `sale_price=9999` y verifica que se ignora.
- **Pagos:** el detalle mostraba `completed` en crudo y no había forma de corregir un monto mal
  cargado. Ahora cada pago tiene Editar (monto/método/fecha) y Anular. Anular **no borra**:
  deja el pago con estado `voided`, y si era el único vigente el pedido vuelve a Pendiente.
- **`DemoSalesSeeder` escribe `method => 'qr'`** pero `METHODS` no lo tenía: se agregaba
  `qr` a `PaymentController::METHODS` para que no quede sin etiqueta.

## Criterios de aceptación cumplidos

- ✅ **HU-03** — alta, edición, filtro por nombre/email/rol/estado, asignación de rol,
      desactivación y borrado. Guards: no desautorizarse ni borrarse a uno mismo; con
      pedidos se desactiva en vez de eliminarse.
- ✅ **HU-06** — nombre único, slug regenerado y con desempate (`hogar`, `hogar-2`),
      y no se puede borrar una categoría con productos asignados.
- ✅ **HU-07** — el administrador escribe costo y margen; el precio de venta se calcula
      siempre con `Product::syncSalePrice()`. En el formulario se ve en vivo mientras se
      escribe (Alpine), pero **no se puede enviar**.

## Extras del punto

- Filtros combinables en las tres listas + paginación con `withQueryString`.
- El listado de productos muestra cuántas imágenes tiene cada uno.
- **HU-08 cerrado:** el detalle de producto ahora arma galería con `product_images` +
  `image_url`, muestra stock y avisa "Sin stock por el momento" sin botón de carrito.
- Confirmación con `confirm()` antes de cada borrado o anulación.

---

# ✅ PUNTO 5 — HU-05: API real de AliExpress

## Qué se implementó

El cliente y el importador ya son reales. Solo falta poner las credenciales.

| Tarea | Estado |
|---|---|
| Firmar la petición (md5 / hmac-md5) | ✅ `AliExpressService::sign()` |
| Llamar a `aliexpress.affiliate.product.query` | ✅ POST form-urlencoded a `api-sg.aliexpress.com/sync` |
| Importar en lotes de 20 a 50 | ✅ `--limit` acotado entre 20 y 50, con paginación |
| No duplicar por `external_id` | ✅ `Product::firstOrNew()` + `syncSalePrice()` |
| Registrar cada ejecución (`success` / `failed`) | ✅ `ApiSyncLog` con el mensaje real de la API |
| Verificar título, imagen y precio de costo | ✅ `tests/Feature/AliExpressSyncTest.php` |
| Quitar el margen hardcodeado | ✅ `ALIEXPRESS_MARGIN_PCT` + `Product::syncSalePrice()` |

**Ya no hay productos falsos a mano.** Se eliminaron los 3 productos escritos a mano
del comando y la constante `AE_TEST_001..003`.

## Detalles de la integración

- **Firma:** se descartan parámetros vacíos y la propia clave `sign`, se ordenan las
  claves por ASCII y se concatena `clave+valor` sin separador.
  `md5` → `md5(secret + cadena + secret)`; `hmac` → `HMAC-MD5(cadena, secret)`.
  El hexadecimal va en **mayúsculas**. Verificado contra el vector de la documentación
  (`bar2`/`foo1`/`foo_bar3`/`foobar4` con secreto `secret`).
- **`timestamp`:** la API exige la hora de China (`Asia/Shanghai`), no la del servidor.
- **Respuesta real:** `aliexpress_affiliate_product_query_response.products.product`
  con `total_results`. El código anterior buscaba una ruta que no existe; ahora también
  tolera `products` como lista directa y `resp_result`.
- **Errores:** `error_response` se convierte en `AliExpressApiException` con
  `code`, `msg` y `sub_msg` reales (por ejemplo `20002 Insufficient isv permissions`).
- **Precio:** se guarda **tal cual lo devuelve la API (USD)**, sin conversión.
  ⚠️ La UI muestra muchos importes con la etiqueta `Bs`: revisar etiquetas/moneda antes de publicar.
- **Categorías:** se crean o reutilizan por `first_level_category_id` (`external_category_id`).
- **Imágenes:** `product_main_image_url` va a `products.image_url` y las secundarias a
  `product_images` (acepta lista o string separado por comas).
- **Stock:** la API no manda stock, así que un producto nuevo entra con 100 y en las
  corridas siguientes **conserva el stock que ajustó el administrador**.
- **Seguridad:** `app_key` y `app_secret` solo se leen del servidor; la vista nunca los expone.

## Variables de entorno

```
ALIEXPRESS_APP_KEY=
ALIEXPRESS_APP_SECRET=
ALIEXPRESS_TRACKING_ID=
ALIEXPRESS_BASE_URL=https://api-sg.aliexpress.com/sync
ALIEXPRESS_SIGN_METHOD=md5          # o hmac
ALIEXPRESS_DEFAULT_KEYWORD="bluetooth earbuds"
ALIEXPRESS_MARGIN_PCT=30
```

## Pruebas

27 pruebas en `tests/Feature/AliExpressSyncTest.php` con `Http::fake()`, sin
necesitar credenciales reales: firma (md5, hmac, orden, vacíos), payload, timestamp,
parseo, paginación, no duplicación, categorías, galería, errores de la API, paneles.

```
php artisan test --filter=AliExpressSyncTest    # 27 passing (99 assertions)
```

## Bloqueo externo (única cosa pendiente)

**Hace falta registrarse en developers.aliexpress.com** para obtener App Key y App Secret.
La aprobación puede tardar **varios días**. Sin esas dos variables no se puede hacer
la llamada real; el panel muestra el aviso "Faltan las credenciales de la API" y el
comando sale con código 1 sin tocar la base.

Para probar de punta a punta apenas llegue la aprobación:

```
php artisan app:sync-aliexpress-products --keyword="auriculares bluetooth" --limit=20
```

Si la API responde `20002 Insufficient isv permissions`, la app existe pero todavía
no tiene aprobado el método `aliexpress.affiliate.product.query`: eso se pide en el
panel de la app, no es un bug del código.

---

# ✅ PUNTO 6 — HU-04: teléfono y dirección de envío en el perfil (HECHO)

Criterio: "Puedo cambiar mi nombre, **teléfono** y **dirección**."

## Archivos creados

| Archivo | Rol |
|---|---|
| `database/migrations/2026_10_03_090000_add_profile_fields_to_users_table.php` | Columnas `phone` (string 30) y `address` (text), nullable |
| `app/Http/Requests/CheckoutRequest.php` | Valida la dirección al confirmar la compra |
| `tests/Feature/ProfileContactInfoTest.php` | HU-04 completa (11 tests) |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/User.php` | `phone` y `address` al `$fillable` |
| `app/Http/Requests/ProfileUpdateRequest.php` | Reglas + `messages()` + `profileData()` |
| `app/Http/Controllers/ProfileController.php` | Usa `profileData()` en vez de `validated()` |
| `resources/views/profile/partials/update-profile-information-form.blade.php` | Input de teléfono (`type="tel"`) y textarea de dirección |
| `resources/views/cart/index.blade.php` | Formulario "Datos de envío" prellenado con el perfil |
| `app/Http/Controllers/OrderController.php` | `store()` recibe `CheckoutRequest` |
| `resources/views/vendedor/pedidos/show.blade.php` | Muestra el teléfono del cliente |
| `database/factories/UserFactory.php` | Estado `conDireccion()` |

## 🐛 El bug que encontró este punto

**El checkout nunca pedía la dirección.** El formulario de "Confirmar compra" en
`cart/index.blade.php` era un `<form>` con **solo el botón** — ningún input. El controlador
hacía `'shipping_address' => $request->shipping_address`, así que **siempre llegaba `null`**:
todos los pedidos se guardaban sin dirección. No daba ningún error visible; el vendedor
veía "No especificada" en el detalle y no había forma de saber por qué.

Los tests de `PurchaseFlowTest` no lo detectaban porque **ellos sí mandaban el campo a mano**,
saltándose el formulario real. La lección es la de siempre: un test que arma el payload a
mano no prueba lo que el usuario hace en pantalla.

## Reglas de validación

- `phone`: `nullable`, `string`, `max:30`, `regex:/^[0-9+\-\s()]+$/`. Un campo vacío se
  guarda como `null`, no como `""` (`ConvertEmptyStringsToNull` ya lo hace Laravel, y
  `profileData()` aplica `trim`).
- `address`: `nullable`, `string`, `max:500`.
- Los dos son **opcionales**: no obligan a los usuarios ya registrados a completarlos.

## Checkout

- `POST /checkout` ahora valida con `CheckoutRequest` (`shipping_address` **required**), y si
  falta devuelve el error al carrito en vez de crear un pedido sin dirección.
- El carrito muestra un bloque "Datos de envío" con la dirección del perfil ya escrita, que
  se puede cambiar solo para ese pedido.
- La dirección del pedido se guarda en `orders.shipping_address`; **no** sobrescribe la del
  perfil, así que un pedido a otra casa no arruina el resto.
- El detalle del vendedor muestra nombre, email, **teléfono** y dirección.

## Criterios de aceptación cumplidos

- ✅ **HU-04** — el cliente cambia nombre, teléfono y dirección desde `/profile`; los datos
      persisten, se vuelven a mostrar al volver, y se usan como valor por defecto al comprar.
- ✅ **HU-14 (refuerzo)** — el checkout exige dirección, así que el vendedor ya no recibe
      pedidos sin destino.

---

# ✅ Publicar en Railway — HECHO

**URL:** https://importachina-production.up.railway.app
Proyecto `exciting-possibility`, servicio `importachina`, región `sfo`.

Estado verificado con peticiones reales: `/catalogo` 200, login de los 3 usuarios,
`/admin/usuarios` y `/admin/reportes` 200 como Administrador.

## ⚠️ Lo que faltaba y se hizo

| Qué | Estado |
|---|---|
| Código commiteado | ✅ 6 commits en `master` (antes: 95 archivos sin commitear) |
| Plugin MySQL | ✅ `mysql:9`, volumen `mysql-volume` (500 MB), db `railway` |
| Dominio público | ✅ `importachina-production.up.railway.app` (antes el servicio no tenía ninguno) |
| Variables del panel | ✅ 25 variables (ver abajo) |
| Migraciones + seed en cada deploy | ✅ en el `Procfile` |
| Contraseñas de `password` | ✅ cambiadas por `ImportaChina#2026` |

### El bug que costó un despliegue

**Railway construye con `railpack`, no con `nixpacks`, y `railpack` ignora
`nixpacks.toml`.** La fase `release` que se había escrito ahí nunca corrió: el
despliegue daba `SUCCESS` y el sitio devolvía **500** con
`Table 'railway.sessions' doesn't exist`.

Se ve en los logs: el build hace `railpack` + `php artisan config:cache`, que es el
proveedor de Laravel de railpack, no los comandos de `nixpacks.toml`.

Arreglo: las migraciones se corren desde el **`Procfile`**, que railpack sí respeta:

```
release: php artisan migrate --force --seed
web: php artisan migrate --force --seed && php artisan serve --host=0.0.0.0 --port=$PORT
```

Se dejan las dos líneas: `release` para cuando el proyecto use nixpacks, y la del
comando `web` para railpack. `migrate` es idempotente, así que correrlo en cada
arranque es seguro. Y `--seed` no pisa las contraseñas: `DatabaseSeeder` usa
`firstOrCreate`, así que las de `ImportaChina#2026` sobreviven a cada despliegue.

### Variables del panel

| Variable | Valor |
|---|---|
| `APP_NAME` | `ImportaChina` |
| `APP_ENV` | `production` |
| `APP_KEY` | generado con `php artisan key:generate --show` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://importachina-production.up.railway.app` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` |
| `APP_FAKER_LOCALE` | `es_ES` |
| `LOG_CHANNEL` | `stderr` — con `single` los logs se pierden |
| `LOG_LEVEL` | `info` |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` |
| `BCRYPT_ROUNDS` | `10` |
| `MAIL_MAILER` | `log` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` | `mysql.railway.internal` / `3306` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | los del plugin MySQL |
| `ALIEXPRESS_*` | los 7, vacíos hasta que aprueben la app |

> Las variables se escribieron a mano en vez de referenciar el plugin. El plugin expone
> `MYSQLHOST`, `MYSQLUSER`… y un `DATABASE_URL` de referencia, pero no `DB_*` en formato
> Laravel. **No hay que subirlas al repo**: `.env` está en `.gitignore` y el `.env.example`
> las tiene vacías.

### Credenciales de prueba

| Email | Contraseña |
|---|---|
| `admin@importachina.com` | `ImportaChina#2026` |
| `vendedor@importachina.com` | `ImportaChina#2026` |
| `cliente@importachina.com` | `ImportaChina#2026` |

Cambiadas desde el propio panel (`/admin/usuarios/{id}/edit`), o sea usando HU-03.
Verificado que la vieja `password` ya no entra.

### Datos de demo

Como la base de Railway arranca vacía, el catálogo no tenía productos y el reporte de
ventas daba `0.00`: el sitio funcionaba pero se veía roto. `DemoSalesSeeder` ya generaba
todo (8 productos, 14 pedidos con pagos repartidos en 30 días), así que se encadenó al
arranque en el `Procfile`.

**Antes no se podía**: el seeder creaba 14 pedidos cada vez que corría, y como corre en cada
despliegue, tres arranques daban 42 pedidos y el reporte quedaría inflado. Por eso ahora
arranca con un guard:

```php
if (Order::exists()) {
    $this->command?->info('Ya hay pedidos cargados: no se generan ventas de demo.');
    return;
}
```

Verificado en producción: el primer despliegue logarithmó *"Ventas de demo generadas: 14
pedidos"* y el redespliego *"Ya hay pedidos cargados"*, con las cifras del reporte idénticas
antes y después.

- Si se borran los pedidos a mano, el siguiente despliegue los vuelve a generar.
- Un pedido real de un cliente también bloquea el seeder, así que los datos falsos nunca se
  mezclan con los verdaderos (`test_no_toca_los_pedidos_que_ya_creo_un_cliente`).
- Los precios que genera la factory no son de AliExpress: son los de `ProductFactory`.

### Utilidades

- `railway logs --deployment <id>` — logs del arranque (donde se ve el `migrate`).
- `railway logs --build <id>` — logs del build.
- `railway variables --kv` — ver las variables (imprime secretos en claro).
- `railway up --detach` — desplegar sin esperar.
- El **dominio TCP público** del MySQL (`mysql-production-3ee8.up.railway.app`) **no**
  responde en ningún puerto: no se puede conectar MySQL de Railway desde la máquina.
  Para hablar con esa base hay que usar `railway shell` / `railway connect`.
- `railway ssh` quedó sin usar: necesita la clave en el agente de SSH y se colgaba.

---

---

# ⬜ PUNTO 9 — Las pantallas de Breeze están en inglés (nuevo, 4 de octubre)

**Apareció al escribir los tests de HU-02**, cuando el test empezó a assertar el mensaje de
error del login y la página salió con "Log in", "Remember me", "Forgot your password?".

## Qué pasa

**No existe la carpeta `lang/`.** El framework solo trae `en`
(`vendor/laravel/framework/src/Illuminate/Translation/lang/` tiene un único directorio, `en`),
así que con `APP_LOCALE=es` sin carpeta `es` **todo cae al inglés**.

Las blades de Breeze usan claves literales en inglés, `__('Log in')`, `__('Email')`. Sin
archivo de traducción, `__()` devuelve la clave tal cual → **inglés en pantalla**.

Verificado en la consola:

```
php artisan tinker --execute="echo __('auth.failed');"
→ These credentials do not match our records.
```

## Dónde se ve

| Pantalla | Texto en inglés | Historia afectada |
|---|---|---|
| `/login` | Log in, Remember me, Forgot your password?, Email, Password | **HU-02** |
| `/register` | Register, Confirm Password, Already registered? | **HU-01** |
| `/forgot-password` | todo en inglés | HU-02 |
| `/reset-password` | todo en inglés | HU-02 |
| `/confirm-password` | todo en inglés | — |
| `/verify-email` | todo en inglés | — |
| `/profile` | Profile Information, Save, Delete Account, Current Password… | **HU-04** |
| `/dashboard` | Dashboard, "You're logged in!" | — |
| `layouts/navigation` | **Profile**, **Log Out** (el resto ya está en español) | — |

**Las tres historias de criterio de aceptación de esta auditoría están en inglés justo en las
pantallas donde el usuario se registra, entra y edita su perfil.** Es lo primero que ve
alguien que abre el sitio.

⚠️ Lo que **no** está afectado: los módulos propios (catálogo, carrito, pedidos, admin,
vendedor) tienen el texto **escrito a mano en español**, sin `__()`. Y los 10 Form Requests
del proyecto sí tienen `messages()` en español. Lo único que sale en inglés son los mensajes
que **no** pasan por un Form Request propio: `auth.failed`, `validation.required`,
`passwords.*`, la paginación y las claves de las blades de Breeze.

## Por qué NO lo arreglé en el punto 8

Porque es otro bug de comportamiento, no parte de HU-01, y meterse habría convertido el
punto en dos cambios sin revisar. También cambia archivos que **no son de HU-01** (perfil,
dashboard, navegación), así que merece su propio punto y su propia decisión.

## Opciones

| Opción | Qué implica | Riesgo |
|---|---|---|
| **A. `lang/es/` completo** | `php artisan lang:publish`, traducir `auth`, `validation`, `passwords` y `pagination`; y cambiar las claves de las blades de Breeze por claves reales (`__('Log in')` → `__('auth.login')`) | Bajo. Es lo estándar de Laravel. Lo más correcto. |
| **B. Solo las blades** | Cambiar los `__('...')` de las 6 vistas de auth y las 4 de perfil por texto español sin `__()`. Los `auth.failed` y las validaciones seguirían en inglés | Medio. Quedan mensajes en inglés y se pierde la capacidad de traducir |
| **C. Dejarlo así** | Nada | El sitio se ve a medio traducir en las pantallas de entrada. **Es lo que está hoy** |

**Recomendación: A.** Es lo que un docente revisaría primero, y `lang/es/` también arregla
los mensajes de validación que hoy salen en inglés.

> Nota sobre el test de HU-02: el assert es `$follow->assertSee(__('auth.failed'))` y **no** un
> literal en español, a propósito. El criterio dice "veo un **mensaje de error claro**", no
> "veo esta frase": así el test sigue siendo válido si el idioma cambia y **no hay que
> editarlo** cuando se traduzca. Verificaba que el mensaje se ve, nada más.

---

# ⬜ PUNTO 7 — Pendientes menores

> **Corregido por la auditoría del 3 de octubre:** el ítem de tests tenía un error (HU-01 y
> HU-02 sí tienen pruebas, aunque incompletas) y el de §10 estaba obsoleto (ya se publicó).
> Leer las notas de arriba antes de tacklearlo.
>
> **Actualizado el 4 de octubre:** el ítem de tests ya no tiene lo de HU-01/HU-02 (hecho en
> el punto 8). Queda **solo HU-09**.

- [ ] **Tests faltantes:**
      - **HU-09** (buscar y filtrar catálogo): **sin pruebas**. Es el **único hueco real** que
        queda. `CatalogController@index` implementa la búsqueda por `title like` y el filtro
        por `category.slug`, y `catalog/index.blade.php` tiene el formulario, pero ningún test
        lo ejercita.
      - ✅ **HU-01 y HU-02**: hechos en el punto 8 (14 y 22 tests). Ya no es un pendiente.
      La guía 4.1 §9 pide 12-16 pruebas, una por historia; con 211 tests la cifra global se
      cumple de sobra, el detalle por historia es lo que queda. (HU-08 ya tiene:
      `ProductDetailTest`.)
- [ ] **§1-2 de la guía 4.1:** no existe documento de requerimientos (12-16 funcionales
      + 4 no funcionales). Se necesita para la nota. **Es el pendiente documental más
      grande**: la guía lo pide en la primera sección.
      Lo no funcional ya está cumplido y se puede documentar con lo verificado: seguridad
      (CSRF, `.env` fuera del repo, `APP_DEBUG=false`, claves por `env()`), rendimiento
      (catálogo servido desde la base, no se consulta la API en cada visita), usabilidad
      (Form Requests con mensajes en español, rutas con nombre) y responsive (20 de 21
      vistas con `sm:/md:/lg:`).
      ⚠️ Con el punto 9 resuelto, el no funcional de **usabilidad** mejora: hoy las pantallas
      de login, registro y perfil están en inglés.
- [ ] **§6 de la guía 4.1:** no hay rama por historia ni Pull Request.
      Todo está en `master`. La guía pide proteger `main` y ramas tipo `us-07-carrito`.
      ⚠️ Corrección: el texto viejo decía "sin commitear, 95 archivos" — **eso ya se
      resolvió**, el proyecto está commiteado (10 commits) y `git status` solo muestra
      `PENDIENTES.md` y las 4 guías sin trackear.
- [x] **§10:** **hecho.** Publicado en Railway y verificado en vivo.
- [ ] **§10, detalle menor:** el `Procfile` corre `migrate` pero **no `php artisan optimize`**,
      que la guía pide explícitamente. railpack hace `config:cache` en el build, pero no
      `route:cache` ni `view:cache`. Bajo impacto, pero es lo que pide la guía.
- [ ] **§11:** sin registro de ceremonias ágiles (planificación, diaria, revisión, retrospectiva).
- [ ] **4.2 / configuración:** el tablero Trello "Gestión - ImportaChina" y los wireframes
      de Figma son **externos al repo**. No hay ni un archivo que los respalde.
      Conviene dejar un `docs/tablero.md` con el estado de las 16 historias para la nota.
      ✅ Con el punto 8 cerrado, las 16 historias están en Hecho (antes 15 y media).
- [ ] **`resources/views/welcome.blade.php`** (82 KB) es el splash de Laravel, no se usa.
- [ ] **`docs/` existe pero está desatendido, y las guías de la raíz no están commiteadas.**
      Contiene `16_historias_de_usuario_ImportaChina.txt`, `guia del proyecto.txt`,
      `guias_paso_a_paso_Trello_y_Kanban.txt`, `configuracion del proyecto, user y stories.txt`
      y una copia de la guía 4.1 en `.html`.
      Las 4 guías de la raíz (`4.0` a `4.3`) están **sin trackear** — ver "Bug 2" más arriba.
      Decidir la fuente de verdad, **commitear 4.2 y 4.3** (solo existen en la raíz) y borrar
      lo demás.

### Lo que exige `configuracion del proyecto, user y stories.txt`

| Requisito del `.txt` | Estado |
|---|---|
| Tablero **público** "Gestión - ImportaChina" con las 5 columnas | ⬜ externo al repo |
| ≥4 historias: 1 Administrador, 1 Vendedor, 2 Cliente | ✅ hay 16 |
| ≥1 historia que dependa de los datos de la API (HU-09, HU-05) | ✅ |
| 2 wireframes en Figma: catálogo con buscador/filtros + detalle con botón de carrito | ⬜ externo al repo |
| Las claves de la API en el servidor, nunca en el navegador | ✅ `config/services.php` |
| Formato de nota `Como [rol], quiero [acción] para [beneficio]` | ✅ las 16 de la guía 4.3 |

### ⬜ Pregunta abierta: el "acceso como invitado" de la docente

La docente dijo que hacía falta "una opción para que ella entre a mirar como invitado".
**Revisadas las 3 guías HTML y el `.txt`, la palabra "invitado" no aparece en ninguna.**
Lo que sí aparece:

| Dónde | Qué dice |
|---|---|
| Guía 4.1 §1 | "**Roles: Administrador, Vendedor y Cliente**" — solo 3, ninguno invitado |
| HU-03 | "asignarles un rol (Administrador, Vendedor o Cliente)" |
| Guía 4.2 §3 | "elige **Público** si el docente pidió un tablero público (**cualquiera con el enlace podrá verlo**)" |
| Guía 4.2 §7 | "**Invita al equipo y al docente**" — Compartir del tablero Trello |
| `.txt` línea 38 | "Crea un tablero **público** ... llamado 'Gestión - ImportaChina'" |

**Hipótesis más probable:** se refiere al **tablero Trello público**, no al sistema web.
No requiere código: es cambiar la visibilidad del tablero a "Público".
**Pendiente de confirmar con ella antes de implementar nada.**

Si resultara que sí es dentro del sistema web, las opciones son:

| Opción | Qué implica |
|---|---|
| A. Cuenta con credenciales | Un usuario más en el seeder (ej. `docente@importachina.com`). Lo más simple. |
| B. Rol `Invitado` de solo lectura | Cuarto rol. Entra a panel, pedidos y reportes, pero sin botones de guardar/borrar. Hay que decidir si cuenta como los 3 roles de la guía. |
| C. Botón "Entrar como invitado" en el login | Entra sin contraseña, solo lectura. Rápido de mostrar, menos realista. |

**Nota:** el catálogo `/catalogo` **ya es público** (se entra sin credenciales), así que "mirar
la tienda sin iniciar sesión" hoy ya funciona.

---

## Tests agregados en los puntos 1 a 4

| Archivo | Cubre | Tests |
|---|---|---|
| `tests/Feature/SalesReportTest.php` | HU-16 completa + autorización | 7 |
| `tests/Feature/SellerOrderTest.php` | HU-12, HU-13, HU-15 + filtros + hole de pago | 21 |
| `tests/Feature/PurchaseFlowTest.php` | HU-10, HU-11, HU-14 + regresión de `authorize()` | 8 |
| `tests/Feature/AccessControlTest.php` | regresión del RoleMiddleware + catálogo público | 26 |
| `tests/Feature/AdminUserManagementTest.php` | HU-03 + guard de autodesactivación | 14 |
| `tests/Feature/AdminCategoryManagementTest.php` | HU-06 + slug único | 10 |
| `tests/Feature/AdminProductManagementTest.php` | HU-07 + precio derivado | 14 |
| `tests/Feature/PaymentEditVoidTest.php` | editar y anular pagos | 8 |
| `tests/Feature/ProductDetailTest.php` | HU-08 galería + stock | 6 |
| `tests/Feature/AliExpressSyncTest.php` | HU-05 completa con `Http::fake()` | 27 |
| `tests/Feature/ProfileContactInfoTest.php` | HU-04 teléfono/dirección + checkout | 11 |
| **Subtotal de los puntos 1 a 6** | | **152** |
| `tests/Feature/ProfileTest.php` | Breeze: editar nombre y email | 5 |
| `tests/Feature/DemoSalesSeederTest.php` | el seeder de demo no pisa datos reales | 4 |
| `tests/Feature/Auth/RegistrationTest.php` | **HU-01 completa** (reescrito en el punto 8) | **14** |
| `tests/Feature/Auth/AuthenticationTest.php` | **HU-02 completa** (reescrito en el punto 8) | **22** |
| `tests/Feature/Auth/PasswordUpdateTest.php` | Breeze | 2 |
| `tests/Feature/Auth/PasswordResetTest.php` | Breeze | 4 |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Breeze | 3 |
| `tests/Feature/Auth/EmailVerificationTest.php` | Breeze | 3 |
| `tests/Feature/ExampleTest.php` + `tests/Unit/ExampleTest.php` | los que trae Laravel | 2 |
| **Subtotal de Breeze y demás** | | **59** |

**Total: 211 passing (691 assertions).** Verificado con `php artisan test` el 4 de octubre
de 2026, después del punto 8. Antes eran 181 passing (590 assertions).

> `AccessControlTest` tiene **7 métodos** pero corre **26 tests**: tres llevan `@DataProvider`
> (uno con 7 rutas de admin y 7 de vendedor) y se expanden. No confundir métodos con tests.
> Es lo mismo que hace `AuthenticationTest` con `rutasPrivadas` (7 rutas × 2 tests).
>
> ✅ **Las pruebas de HU-01 y HU-02 ya no son las de Breeze**: se reescribieron en el punto 8
> y assertan los criterios de las historias (el rol `Cliente`, correo único, mínimo de 8
> caracteres, el mensaje de error visible y que las páginas privadas queden cerradas tras el
> logout).
>
> 🚨 **HU-09 sigue sin pruebas:** nadie ejercita la búsqueda por palabra clave ni el filtro por
> categoría de `CatalogController@index`. Es el único hueco real que queda.
>
> **Usa atributos `#[DataProvider]`, no `@dataProvider` en doc-comment** (como ya hace
> `AccessControlTest`): PHPUnit 12 deprecó el doc-comment y avisa por cada método.

**Los de `AccessControlTest` y `PurchaseFlowTest` son tests de regresión**: están para fallar
si alguien vuelve a romper el middleware o el trait `AuthorizesRequests`.

Factories que se rellenaron (estaban vacías, con cuerpo `//`):
`Order`, `OrderItem`, `Product`, `Category`, `Cart`, `CartItem`, `Payment`.
`UserFactory` ganó estados `admin()`, `vendedor()`, `cliente()`.

**Ojo con `UserFactory`:** además de los estados, `definition()` tiene que setear
`'status' => User::STATUS_ACTIVE`. Si falta, el modelo creado en memoria queda con
`status = null` aunque la fila en la base tenga `'active'`, y `RoleMiddleware` aborta con
403. Es el bug que rompía todo, detallado en el encabezado. No borrarlo.

---

## Comandos útiles

```bash
php artisan test                                     # debe dar 211 passing (691 assertions)
php artisan test --filter=SellerOrderTest            # HU-12/13/15
php artisan test --filter=SalesReportTest           # solo HU-16
php artisan test --filter=ProfileContactInfoTest     # HU-04 teléfono/dirección
php artisan test --filter=AliExpressSyncTest        # HU-05 con Http::fake()
php artisan test --filter=Admin                    # HU-03, HU-06, HU-07
php artisan test --filter=RegistrationTest          # HU-01 (14)
php artisan test --filter=AuthenticationTest        # HU-02 (22)
php artisan test --filter=Auth                     # HU-01, HU-02 y el resto de Breeze
php artisan route:list --path=admin                  # ver las 3 secciones del punto 4
php artisan route:list --path=vendedor              # pedidos + pagos
php artisan db:seed --class=DemoSalesSeeder         # recargar datos de demo
php artisan migrate:fresh --seed                    # base de datos limpia

# Ojo: --filter=<Clase> cuenta TESTS, no metodos. AccessControlTest tiene 7 metodos
# y corre 26 tests porque tres llevan #[DataProvider]. AuthenticationTest tiene
# 8 metodos y corre 22 tests por el mismo motivo.

# Pint está instalado. Correrlo SOLO sobre los archivos tocados,
# si no reformatea 20 archivos preexistentes y el diff se ensucia:
php vendor\bin\pint app/.../X.php tests/...
```

## Checklist para retomar

**Orden sugerido:** ya no queda ningún bug de comportamiento. Sigue por el **documento de
requerimientos** (§1-2 de la guía 4.1), que es lo único grande que bloquea la nota, y
decide si el **punto 9** (idioma) entra antes que el paperwork.

- [x] **Punto 8 (el bug de HU-01):** hecho el 4 de octubre. `RegisteredUserController::store()`
      ahora asigna `role_id` con `Role::firstOrCreate(['name' => 'Cliente'])->id` y `status`
      explícito. `RegistrationTest` (14 tests) y `AuthenticationTest` (22) assertan los
      criterios de HU-01 y HU-02, y se **verificó que fallan** commenting el arreglo.
      Con `RefreshDatabase` los roles no se siembran: los tests lo hacen en `setUp()`.
- [ ] **Bug 2:** commitear las guías `4.0`-`4.3` de la raíz. Hoy están sin trackear y
      **4.2 y 4.3 no existen en ningún otro lado**: no están en GitHub.
- [ ] **Punto 5 (HU-05):** el código ya está terminado y probado con `Http::fake()`.
      Solo falta rellenar `ALIEXPRESS_APP_KEY` / `ALIEXPRESS_APP_SECRET` cuando se apruebe
      la app en `developers.aliexpress.com` (el trámite tarda días), y correr
      `php artisan app:sync-aliexpress-products --limit=20` para confirmar contra la API real.
- [x] **Punto 6 (HU-04):** hecho. Migración `phone` / `address`, inputs en el perfil,
      `CheckoutRequest` y la dirección del perfil como valor por defecto en el carrito.
- [x] **Railway:** hecho y verificado en vivo. Plugin MySQL, 25 variables, dominio,
      migraciones en el `Procfile` y contraseñas cambiadas.
- [x] **Auditoría 4.0-4.3:** hecha el 3 de octubre de 2026. Las 11 tablas y los 52 puntos
      del backlog coinciden con la guía.
- [ ] **Punto 9 (idioma):** las pantallas de Breeze están en inglés y no hay `lang/`.
      Ver la sección de arriba. Abarca login, registro, perfil y dashboard — o sea HU-01,
      HU-02 y HU-04.
- [ ] **Punto 7, lo que bloquea la nota:**
      - [ ] documento de requerimientos (§1-2 de la guía 4.1) — **lo más grande**
      - [ ] tests de HU-09 (el único hueco real que queda)
      - [ ] ramas por historia y Pull Requests (§6)
      - [ ] registro de ceremonias ágiles (§11)
      - [ ] `docs/tablero.md` respaldando el tablero Trello y los wireframes
      - [ ] `php artisan optimize` en el `Procfile` (§10)
- [ ] **Punto 10 (decidir):** `resources/views/welcome.blade.php` (82 KB, splash de Laravel
      sin usar) y los duplicados de `docs/`.
- [ ] **Pregunta para la docente:** confirmar si el "acceso como invitado" es el tablero
      Trello público o una cuarta persona en el sistema web. Ver la sección de arriba.