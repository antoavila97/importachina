# Tablero de historias de usuario — ImportaChina

> **16 historias · 52 puntos · 4 sprints · 100 % hechas.**
> Fuentes: [`16_historias_de_usuario_ImportaChina.txt`](./16_historias_de_usuario_ImportaChina.txt)
> (criterios completos), `4.3. 16 historias de usuario_ ImportaChina.html` (raíz) y
> [`../PENDIENTES.md`](../PENDIENTES.md) (estado real y decisiones).
> Verificado el **8 de octubre de 2026** con `php artisan test` → **240 passing (822 assertions)**.

## Sprint 1 — Autenticación y roles (4 historias, 12 puntos)

| HU | Rol | Pts | Historia | Criterios clave | Estado | Tests |
|---|---|---|---|---|---|---|
| HU-01 | Cliente | 3 | Registrarme con correo y contraseña | correo único, mínimo 8 caracteres; la cuenta nace con rol Cliente | ✅ Hecho | `RegistrationTest`, `AuthenticationTest` |
| HU-02 | Cliente | 2 | Iniciar y cerrar sesión | entro con credenciales correctas; con malas veo mensaje claro; cerrado no entro a páginas privadas | ✅ Hecho | `AuthenticationTest`, `PasswordResetTest`, `PasswordConfirmationTest` |
| HU-03 | Administrador | 5 | Gestionar usuarios y asignar roles | listar/crear/editar/desactivar; Vendedor y Cliente no entran al panel de admin | ✅ Hecho | `AdminUserManagementTest`, `AccessControlTest`, `AuthenticationTest` |
| HU-04 | Cliente | 2 | Editar mi perfil y dirección de envío | cambio nombre, teléfono y dirección; se guardan y se muestran | ✅ Hecho | `ProfileTest`, `ProfileContactInfoTest` |

## Sprint 2 — Catálogo y consumo de la API (5 historias, 15 puntos)

| HU | Rol | Pts | Historia | Criterios clave | Estado | Tests |
|---|---|---|---|---|---|---|
| HU-05 | Administrador | 5 | Sincronizar productos desde la API de AliExpress | 20-50 productos con título, imagen y precio de costo; los repetidos no se duplican; cada sync queda registrada | ✅ Hecho — **modo demostración** (ver nota) | `AliExpressSyncTest`, `AliExpressDemoModeTest` |
| HU-06 | Administrador | 2 | CRUD de categorías | nombre único; no se elimina una categoría con productos | ✅ Hecho | `AdminCategoryManagementTest` |
| HU-07 | Administrador | 2 | Definir margen de ganancia y precio de venta | precio = costo + margen; los cambios se reflejan en el catálogo | ✅ Hecho | `AdminProductManagementTest`, `AliExpressSyncTest` |
| HU-08 | Cliente | 3 | Ver el catálogo y el detalle de cada producto | catálogo con imagen, título, precio y paginación; detalle con descripción, galería y botón de carrito | ✅ Hecho | `CatalogPageTest`, `ProductDetailTest` |
| HU-09 | Cliente | 3 | Buscar por palabra clave y filtrar por categoría | la búsqueda encuentra por título; se combina con categoría y se limpia | ✅ Hecho | `CatalogPageTest` |

## Sprint 3 — Carrito, pedidos y pagos (4 historias, 15 puntos)

| HU | Rol | Pts | Historia | Criterios clave | Estado | Tests |
|---|---|---|---|---|---|---|
| HU-10 | Cliente | 5 | Agregar productos al carrito y cambiar cantidades | agregar, cambiar cantidad, quitar; subtotales y total actualizados | ✅ Hecho | `PurchaseFlowTest` |
| HU-11 | Cliente | 5 | Confirmar la compra con mi dirección de envío | se crea el pedido en Pendiente y el carrito queda vacío; el precio queda congelado | ✅ Hecho | `PurchaseFlowTest` |
| HU-12 | Vendedor | 3 | Ver pedidos y cambiar su estado | veo cliente, total y estado; cambio entre Pendiente, Pagado, Enviado y Entregado | ✅ Hecho | `SellerOrderTest` |
| HU-13 | Vendedor | 2 | Registrar el pago de un pedido | método, monto y fecha; al registrar, el pedido pasa a Pagado | ✅ Hecho | `SellerOrderTest`, `PaymentEditVoidTest` |

## Sprint 4 — Reportes y publicación (3 historias, 10 puntos)

| HU | Rol | Pts | Historia | Criterios clave | Estado | Tests |
|---|---|---|---|---|---|---|
| HU-14 | Cliente | 3 | Ver mi historial de pedidos y su estado | solo veo mis pedidos; cada uno muestra fecha, productos, total y estado | ✅ Hecho | `PurchaseFlowTest` |
| HU-15 | Vendedor | 2 | Ver el detalle completo de un pedido | productos, cantidades, dirección y contacto; vuelvo a la lista sin perder filtros | ✅ Hecho | `SellerOrderTest` |
| HU-16 | Administrador | 5 | Reporte de ventas por rango de fechas y más vendidos | elijo fecha inicial y final, veo el total vendido y los 5 productos más vendidos | ✅ Hecho | `SalesReportTest` |

**Total: 16 historias · 52 puntos · 16 de 16 hechas.**

## Notas

- **HU-05 (la única con asterisco):** la API de AliExpress **no se puede usar desde Bolivia**
  (pide verificar un número de celular y Bolivia no figura entre los países soportados). Se
  conservó el código real y se agregó el **modo demostración** (`ALIEXPRESS_DEMO=true`), que
  corre la misma importación contra un catálogo local con la forma exacta de la respuesta de
  la API. Detalle en `PENDIENTES.md`, "PUNTO 5".
- **Criterios que exceden a una sola HU:** la búsqueda y el filtro por categoría (HU-09) y el
  placeholder de imágenes se prueban dentro de `CatalogPageTest`; los permisos por rol
  (`AccessControlTest`) cubren a la vez HU-03 y los candados de los paneles de HU-12/HU-16.
- **Pendiente que no es de ninguna HU en particular:** el **punto 9** — quedan 7 vistas de
  Breeze en inglés (`welcome`, `dashboard`, `verify-email`, `forgot/reset/confirm-password`
  y los 3 partials de perfil). El login, el registro, la navegación y los mensajes de
  validación ya están en español (`lang/es/`).
- **Proceso de ramas:** `master` está protegido; de aquí en adelante el trabajo entra por
  rama + Pull Request (§6 de la guía 4.1). No se crearon ramas retrospectivas para estas 16
  historias: los commits históricos viven todos en `master` y esa decisión está documentada
  en `PENDIENTES.md`.
