# E-Service API

API RESTful para una plataforma de servicios profesionales (iService) que conecta clientes con profesionales verificados. Gestiona solicitudes de servicio, pagos, chat en tiempo real y calificaciones.

---

## Stack Tecnológico

| Tecnología | Versión | Rol |
|---|---|---|
| PHP | ^7.3 | Lenguaje principal |
| Laravel | ^8.0 | Framework backend |
| Laravel Passport | 10.4 | Autenticación OAuth 2.0 |
| MySQL | 5.7 / 8.x | Base de datos principal |
| Eloquent ORM | (incluido en Laravel 8) | ORM y migraciones |
| Guzzle HTTP | ^7.0.1 | Cliente HTTP para APIs externas |
| Laravel Mix | ^5.0.1 | Bundling de assets (Webpack) |
| PHPUnit | ^9.3 | Testing |
| Node.js | >=12.x | Build assets (Laravel Mix) |
| Wompi | API v1 | Pasarela de pagos (Colombia) |

---

## Arquitectura

```
E-service-API/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     # Controladores REST
│   │   └── Middleware/          # Auth, roles (admin, professional, client, active)
│   ├── Models/                  # Modelos Eloquent
│   ├── Services/                # Lógica de negocio (Wompi, pagos)
│   └── Guards/                  # PassportTokenGuard personalizado
├── config/
│   ├── database.php             # Configuración MySQL
│   └── wompi.php                # Credenciales pasarela de pagos
├── database/
│   ├── migrations/              # 47+ migraciones
│   └── seeders/                 # Datos iniciales
├── routes/
│   └── api.php                  # Todas las rutas REST
└── storage/app/public/
    └── documents/               # Documentos e imágenes subidas
```

---

## Base de Datos

**Motor**: MySQL con charset `utf8mb4` / collation `utf8mb4_unicode_ci`

### Tablas principales

| Tabla | Descripción |
|---|---|
| `users` | Usuarios del sistema (admin, client, professional) |
| `professionals` | Perfil extendido de profesionales |
| `categories` | Categorías de servicios |
| `services` | Servicios con precios y comisiones |
| `professional_services` | Relación profesional <-> servicio |
| `professional_categories` | Especialidades del profesional |
| `service_requests` | Solicitudes de servicio de clientes |
| `request_professionals` | Asignación de profesional a solicitud |
| `payments` | Pagos procesados vía Wompi |
| `payouts` | Desembolsos a profesionales |
| `professional_payment_infos` | Datos bancarios del profesional |
| `chat_messages` | Mensajes de chat por solicitud |
| `work_evidences` | Fotos/evidencias del trabajo realizado |
| `ratings` | Calificaciones cliente <-> profesional |
| `notifications` | Notificaciones push para usuarios |
| `support_tickets` | Tickets de soporte al cliente |
| `cities` | Catálogo de ciudades |
| `settings` | Configuración del sistema |
| `admin_logs` | Auditoría de acciones administrativas |
| `password_resets` | Tokens de restablecimiento de contraseña |

### Estados de `service_requests`

```
payment_pending -> pending -> accepted -> in_progress -> completed
                                       -> cancelled
```

---

## Autenticación

**Mecanismo**: Laravel Passport (OAuth 2.0 — Bearer Tokens)

- `POST /api/register` devuelve `access_token`
- `POST /api/login` devuelve `access_token`
- Todas las rutas protegidas requieren: `Authorization: Bearer {token}`

### Roles y Middleware

| Middleware | Rol |
|---|---|
| `auth:api` | Usuario autenticado (cualquier rol) |
| `admin` | Administrador o Sub-administrador |
| `professional` | Profesional verificado |
| `client` | Cliente registrado |
| `active` | Cuenta activa (no bloqueada) |

---

## API Endpoints

### Autenticación (público)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/register` | Registro de usuario |
| POST | `/api/login` | Inicio de sesión |
| GET | `/api/categories` | Listar categorías activas |
| GET | `/api/services` | Listar servicios activos |
| GET | `/api/professionals` | Listar profesionales aprobados |
| GET | `/api/cities` | Listar ciudades |
| GET | `/api/cities/{id}` | Detalle de ciudad |
| GET | `/api/geocode` | Proxy geocodificación (Photon/Nominatim) |
| POST | `/api/wompi/webhook` | Webhook pagos Wompi |
| POST | `/api/wompi/payouts-webhook` | Webhook desembolsos Wompi |

### Cuenta (autenticado)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/profile` | Perfil del usuario autenticado |
| POST | `/api/logout` | Cerrar sesión (revoca token) |
| PUT | `/api/account/settings` | Actualizar configuración de cuenta |
| GET | `/api/heartbeat` | Health check |

### Cliente (`/api/client/*`)

| Método | Ruta | Descripción |
|---|---|---|
| GET/PUT | `/api/client/profile` | Ver/actualizar perfil |
| PUT | `/api/client/password` | Cambiar contraseña |
| GET | `/api/client/notifications` | Notificaciones |
| POST | `/api/client/notifications/read-all` | Marcar todas como leídas |
| GET | `/api/client/unread-counts` | Conteo de no leídos |
| GET | `/api/client/balance` | Saldo de cuenta |
| GET | `/api/client/favorites` | Profesionales favoritos |
| GET | `/api/client/featured-professionals` | Profesionales destacados |
| GET | `/api/client/professionals-available` | Profesionales disponibles para servicio |
| POST | `/api/client/service-request` | Crear solicitud de servicio |
| GET | `/api/client/service-request/{id}/status` | Estado de solicitud |
| GET | `/api/client/requests` | Historial de solicitudes |
| DELETE | `/api/client/requests/{id}` | Cancelar solicitud |
| POST | `/api/client/requests/{id}/generate-code` | Generar código de finalización |
| GET | `/api/client/requests/{id}/evidences` | Ver evidencias |
| POST | `/api/client/requests/{id}/rate` | Calificar profesional |
| GET | `/api/client/requests/{id}/my-rating` | Ver calificación enviada |
| GET | `/api/client/requests/{id}/document/{doc}` | Descargar documento de capacitación |
| POST | `/api/client/payment/send-otp` | Enviar OTP para pago |
| POST | `/api/client/payment/verify-otp` | Verificar OTP |
| GET | `/api/client/payment/acceptance-token` | Token de aceptación Wompi |
| POST | `/api/client/payment/init` | Iniciar pago |
| POST | `/api/client/payment/confirm` | Confirmar pago |
| GET | `/api/client/payment/status` | Estado del pago |
| POST | `/api/client/payment/charge-saved-card` | Cobrar tarjeta guardada |
| GET | `/api/client/support` | Listar tickets de soporte |
| POST | `/api/client/support` | Crear ticket de soporte |

### Profesional (`/api/professional/*`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/professional/dashboard` | Dashboard del profesional |
| GET | `/api/professional/earnings` | Reporte de ganancias |
| POST | `/api/professional/profile` | Crear/actualizar perfil profesional |
| GET/POST | `/api/professional/payment-info` | Info bancaria para pagos |
| GET | `/api/professional/banks` | Bancos soportados |
| GET | `/api/professional/requests/available` | Solicitudes disponibles |
| POST | `/api/professional/requests/{id}/accept` | Aceptar solicitud |
| GET | `/api/professional/requests/accepted` | Solicitudes aceptadas |
| GET | `/api/professional/requests/{id}/evidences` | Ver evidencias |
| POST | `/api/professional/requests/{id}/evidences` | Subir evidencia fotográfica |
| POST | `/api/professional/requests/{id}/complete` | Completar servicio |
| POST | `/api/professional/requests/{id}/verify-code` | Verificar código de finalización |
| GET | `/api/professional/requests/{id}/document/{doc}` | Generar documento de capacitación |
| POST | `/api/professional/requests/{id}/rate-client` | Calificar cliente |
| GET | `/api/professional/requests/{id}/my-rating` | Ver calificación enviada |

### Chat (`/api/chat/*`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/chat/unreads` | Mensajes no leídos |
| GET | `/api/chat/{requestId}` | Mensajes de una solicitud |
| POST | `/api/chat/{requestId}` | Enviar mensaje |

### Administración (`/api/admin/*`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/admin/stats` | Estadísticas del dashboard |
| GET/POST | `/api/admin/users` | Listar / gestionar usuarios |
| GET/PUT | `/api/admin/users/{user}` | Detalle / actualizar usuario |
| PATCH | `/api/admin/users/{user}/toggle-status` | Activar/desactivar usuario |
| PATCH | `/api/admin/users/{user}/verify-professional` | Verificar profesional |
| POST | `/api/admin/users/bulk` | Operaciones masivas |
| GET | `/api/admin/users/export` | Exportar usuarios |
| GET | `/api/admin/users/specialties` | Especialidades por profesional |
| GET/POST | `/api/admin/categories` | Categorías |
| PUT/DELETE | `/api/admin/categories/{category}` | Editar/eliminar categoría |
| POST | `/api/admin/services` | Crear servicio |
| PUT/DELETE | `/api/admin/services/{service}` | Editar/eliminar servicio |
| GET/POST | `/api/admin/sub-admins` | Sub-administradores |
| PUT/DELETE | `/api/admin/sub-admins/{user}` | Gestionar sub-admin |
| GET | `/api/admin/reports` | Reportes del sistema |
| GET/PUT | `/api/admin/settings` | Configuración del sistema |
| GET | `/api/admin/support` | Tickets de soporte |
| GET | `/api/admin/support/{id}` | Detalle de ticket |
| PATCH | `/api/admin/support/{id}/reply` | Responder ticket |
| PATCH | `/api/admin/support/{id}/status` | Cambiar estado de ticket |
| GET | `/api/admin/logs` | Logs de auditoría |
| GET | `/api/admin/payments` | Listado de pagos |
| GET | `/api/admin/payments/stats` | Estadísticas de pagos |
| GET | `/api/admin/payments/pending-payouts` | Pagos pendientes de desembolso |
| POST | `/api/admin/payments/{id}/simulate` | Simular pago (solo DEV) |
| GET | `/api/admin/payouts` | Listado de desembolsos |
| GET | `/api/admin/payouts/{id}` | Detalle de desembolso |
| POST | `/api/admin/payouts/{id}/disburse` | Ejecutar desembolso a profesional |
| GET | `/api/admin/live-services/summary` | Resumen de servicios en vivo |
| GET | `/api/admin/live-services/requests` | Solicitudes activas |
| GET | `/api/admin/live-services/connected-users` | Usuarios conectados |
| GET | `/api/admin/live-services/chats` | Chats activos |
| GET | `/api/admin/live-services/incidents` | Incidentes |
| GET | `/api/admin/live-services/chat/{requestId}/messages` | Mensajes de solicitud |
| GET | `/api/admin/live-services/requests/{requestId}/available-professionals` | Profesionales disponibles para reasignación |
| POST | `/api/admin/live-services/requests/{requestId}/reassign` | Reasignar profesional |

---

## Cómo Levantar el Proyecto

### Requisitos previos

- PHP >= 7.3
- Composer
- MySQL 5.7 o 8.x
- Node.js >= 12.x y npm

### 1. Clonar e instalar dependencias

```bash
git clone <url-del-repositorio>
cd E-service-API

# Instalar dependencias PHP
composer install

# Instalar dependencias Node (solo para assets)
npm install
```

### 2. Configurar entorno

```bash
# Copiar archivo de entorno
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

Editar `.env` con los valores correctos:

```env
APP_NAME="E-Service API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eservice_db
DB_USERNAME=root
DB_PASSWORD=tu_password

# Pasarela de pagos Wompi
WOMPI_PUBLIC_KEY=
WOMPI_PRIVATE_KEY=
WOMPI_INTEGRITY_KEY=
WOMPI_ENV=sandbox

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
```

### 3. Base de datos y migraciones

```bash
# Crear la base de datos primero en MySQL, luego ejecutar:
php artisan migrate

# (Opcional) Cargar datos iniciales
php artisan db:seed
```

### 4. Configurar Laravel Passport

```bash
php artisan passport:install
```

### 5. Storage

```bash
php artisan storage:link
```

### 6. Levantar el servidor

```bash
php artisan serve
# La API estará disponible en: http://localhost:8000/api
```

### 7. Compilar assets (opcional)

```bash
npm run dev
# o en modo watch:
npm run watch
```

---

## Variables de Entorno Clave

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_KEY` | Clave de cifrado (generada con `artisan key:generate`) | `base64:...` |
| `DB_DATABASE` | Nombre de la base de datos | `eservice_db` |
| `DB_USERNAME` | Usuario MySQL | `root` |
| `DB_PASSWORD` | Contraseña MySQL | `secret` |
| `WOMPI_PUBLIC_KEY` | Clave pública Wompi | `pub_stagtest_...` |
| `WOMPI_PRIVATE_KEY` | Clave privada Wompi | `prv_stagtest_...` |
| `WOMPI_INTEGRITY_KEY` | Clave de integridad webhooks | `stagtest_integrity_...` |
| `WOMPI_ENV` | Entorno Wompi | `sandbox` o `production` |
| `MAIL_HOST` | Servidor SMTP | `smtp.mailtrap.io` |

---

## Testing

```bash
# Correr todos los tests
php artisan test

# O directamente con PHPUnit
./vendor/bin/phpunit
```

---

## Changelog — Features y Fixes Implementados

### Sistema de Usuarios y Roles
- Sistema multi-rol: `admin`, `client`, `professional`
- Middleware de roles por ruta (`admin`, `professional`, `client`, `active`)
- Campo `is_active` para activar/desactivar cuentas
- Sub-administradores con permisos granulares
- OTP de autenticación para operaciones sensibles
- Campos `phone`, `city`, `last_seen_at` agregados a usuarios

### Autenticación OAuth 2.0
- Integración completa de Laravel Passport
- Guard personalizado `PassportTokenGuard`
- Login, registro y logout con revocación de token

### Gestión de Profesionales
- Perfil profesional extendido con documentos (DNI, tarjeta profesional, título)
- Flujo de verificación/aprobación por admin (`pending` -> `approved`)
- Foto de perfil
- Vinculación con categorías y servicios múltiples (`professional_categories`, `professional_services`)
- Ubicación por ciudad y datos de geolocalización
- Timestamp de verificación (`verified_at`)

### Catálogo de Servicios y Categorías
- CRUD completo de categorías y servicios vía admin
- Servicios con precio base y breakdown de comisiones
- Relación profesional <-> servicio <-> categoría

### Solicitudes de Servicio
- Flujo completo: creación -> asignación -> ejecución -> finalización
- Código de finalización de 6 dígitos verificado entre cliente y profesional
- Evidencias fotográficas del trabajo realizado
- Campos de empresa, descripción y dirección opcionales
- Coordenadas GPS (lat/lng) para la solicitud
- Participantes en la solicitud
- Historial de solicitudes por cliente y profesional

### Pasarela de Pagos (Wompi)
- Inicialización y confirmación de pagos con Wompi Checkout
- Webhook seguro con validación de firma HMAC-SHA256
- Estado `payment_pending` integrado al ciclo de solicitud
- Sistema de desembolsos/payouts a profesionales
- Datos bancarios del profesional (`professional_payment_infos`)
- Listado de bancos soportados
- Simulación de pagos para entorno de desarrollo (`/api/admin/payments/{id}/simulate`)
- OTP para operaciones de pago
- Token de aceptación Wompi

### Chat en Tiempo Real
- Mensajería por solicitud entre cliente y profesional
- Conteo de mensajes no leídos
- Monitoreo de chats activos desde admin

### Sistema de Calificaciones
- Calificación mutua: cliente califica profesional y profesional califica cliente
- Historial de calificaciones por solicitud

### Notificaciones
- Tabla de notificaciones push para usuarios
- Conteo de no leídos
- Marcado masivo como leído
- Emails transaccionales: servicio aceptado y completado

### Soporte al Cliente
- Creación y listado de tickets desde el cliente
- Gestión de tickets desde admin (respuesta y cambio de estado)

### Panel de Administración
- Dashboard con estadísticas generales
- Gestión completa de usuarios con exportación CSV
- Verificación de profesionales
- Gestión de pagos y desembolsos
- Logs de auditoría de acciones administrativas
- Reportes del sistema
- Configuración global del sistema (`settings`)
- Monitoreo de servicios en vivo (solicitudes activas, usuarios conectados, incidentes)
- Reasignación de profesionales desde admin

### Ciudades y Geolocalización
- Catálogo de ciudades
- Proxy de geocodificación a APIs externas (Photon/Nominatim)
- Coordenadas GPS en solicitudes de servicio

### Generación de Documentos
- Documentos de capacitación en formato Word (descargable por cliente y profesional)

### Localización
- Timezone: `America/Bogota`
- Idioma: Español (`es`)

---

## Integraciones Externas

| Servicio | Propósito |
|---|---|
| **Wompi** | Procesamiento de pagos y desembolsos (Colombia) |
| **Photon / Nominatim** | Geocodificación de direcciones |
| **SMTP** | Envío de emails transaccionales |

---

## Estructura de Respuesta API

Todas las respuestas siguen el formato:

```json
{
  "success": true,
  "message": "Descripción de la operación",
  "data": { }
}
```

En caso de error:

```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": { }
}
```

---

## Notas de Seguridad

- Los webhooks de Wompi son validados con firma HMAC-SHA256
- Los tokens de Passport se revocan en logout
- Las rutas admin están protegidas con middleware de rol
- Los documentos subidos se almacenan fuera del directorio público
- CORS configurado via `fruitcake/laravel-cors`
