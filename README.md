# Recova Rentals – Admin

**Laravel 12 + Filament v4**

Panel de administración para gestionar **entrevistas** y **bloqueos de agenda (Calendar Blocks)**, con **sincronización automática** al **Google Calendar** del dueño.

## Funcionalidades

### Entrevistas (Interviews)

* CRUD de **Interviews** (título, inicio, fin, estado).
* Validaciones:

  * **Fin > Inicio**.
  * **No solapamiento entre entrevistas** (permite “borde con borde”).
  * **No solaparse con Calendar Blocks** (día completo o por horas).
* **Policy**: solo **admin** puede **editar** entrevistas **confirmadas**.
* **Google OAuth** (Socialite) + **refresh automático** de token.
* **Sincronización automática** con Google Calendar:

  * Crear/editar → **upsert** del evento.
  * Cancelar/eliminar → **delete** del evento y limpia `google_event_id`.
* Comando de **backfill/recovery**:

  * `php artisan google:sync-interviews --owner [--since=YYYY-MM-DD] [--cancelled=keep|clear]`.

### Bloqueos de agenda (Calendar Blocks)

* CRUD de **Calendar Blocks** (título, motivo, día completo o franja horaria).
* **Generador masivo** desde el listado:

  * **Por rango de fechas** *o* **Por días de semana (próximas N semanas)**.
  * Campos reactivos (muestra solo lo necesario).
  * Inserción **bulk** sin observers (rápido).
  * Encola **`SyncBlocksRangeJob`** para crear/actualizar eventos en Google.
* **Validación global**: las entrevistas **no pueden** caer dentro de bloques activos.
* Sincronización con Google Calendar del dueño (mismo calendario que entrevistas).

## Requisitos

* **PHP 8.3+**, **Composer 2.x**
* **Node.js 18+** y **npm 9+**
* **MySQL 8+** (o driver compatible)
* Extensiones PHP típicas (mbstring, pdo, openssl, etc.)
* **Colas**:

  * `QUEUE_CONNECTION=database` (dev) o `redis` (prod con Horizon)
  * Migración de jobs y **worker** en ejecución

> En Windows: **Laragon** o **XAMPP** funcionan bien.

## Instalación (primer uso)

```bash
# 1) Clonar
git clone https://github.com/HackeandoBits/recova-rentals-admin.git
cd recova-rentals-admin

# 2) Dependencias PHP
composer install

# 3) Copiar y editar .env
cp .env.example .env
# -> Configurar DB_* (db, user, pass)
# -> APP_URL=http://localhost:8000
# -> SESSION_DOMAIN=localhost
# -> SANCTUM_STATEFUL_DOMAINS=localhost:8000
# -> OWNER_CAL_USER_ID=1
# -> GOOGLE_CLIENT_ID=...
# -> GOOGLE_CLIENT_SECRET=...
# -> GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
# -> QUEUE_CONNECTION=database

# 4) APP_KEY
php artisan key:generate

# 5) Dependencias JS
npm ci   # (o npm install)

# 6) Compilar assets
npm run build   # (en dev: npm run dev)

# 7) Storage público
php artisan storage:link

# 8) Migraciones (+ tabla de colas)
php artisan queue:table
php artisan migrate --seed

# 9) Servidor
php artisan serve --host=localhost --port=8000

# 10) Worker de colas (otra terminal)
php artisan queue:work --queue=google-sync,default --tries=3
```

### Credenciales admin (seeder)

* **Email:** `recovarentals@gmail.com`
* **Password:** `recova123`

> Ajustar en `database/seeders/AdminUserSeeder.php` si cambia.

## Conectar Google Calendar (una sola vez por entorno)

1. Ingresar a **`/admin`** con el usuario del **dueño**.
2. Ir a **`/auth/google/redirect`** y aceptar permisos.
3. Se guardan tokens en `google_tokens`.
   El calendario por defecto es el del **dueño** (`OWNER_CAL_USER_ID`).

## Uso

### Entrevistas

* Crear/editar desde **/admin → Reuniones**.
* La app sincroniza **automáticamente**:

  * **create/update** → upsert evento en Google.
  * **cancelled/delete** → borra evento y limpia `google_event_id`.
* Las entrevistas **fallan** si:

  * Fin ≤ Inicio.
  * Se **solapan** con otra entrevista (borde con borde permitido).
  * Se **solapan** con un **Calendar Block** activo.

### Calendar Blocks

* Ingresar a **/admin → Bloques de agenda**.
* **Generar bloqueos** (acción en el listado):

  * **Modo “Rango de fechas”**: seleccioná `Desde/Hasta`, “Día completo” o `Hora inicio/fin`.
  * **Modo “Por días”**: elegí días de semana, cantidad de semanas y si es “Día completo” o con horas.
  * Al confirmar:

    * Inserta **en lote** (rápido).
    * Encola **`SyncBlocksRangeJob`** (cola `google-sync`) para crear/actualizar en Google.
    * Requiere **worker** corriendo.
* Un bloqueo de “Día completo” cubre **00:00:00–23:59:59** del día seleccionado.

## Configuración y archivos clave

### Variables `.env` (relevantes)

```dotenv
APP_URL=http://localhost:8000
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:8000
COOKIE_SECURE=false

OWNER_CAL_USER_ID=1

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

QUEUE_CONNECTION=database
```

### Migraciones

* `create_google_tokens_table`
* `add_google_event_id_to_interviews`
* `create_calendar_blocks_table`  *(incluye índices `starts_at, ends_at` y sync fields)*
* **Opcional (recomendado)**: índice compuesto
  `index(['owner_user_id', 'starts_at', 'ends_at'])`

### Código relevante

* **Tokens:** `app/Models/GoogleToken.php`
* **Entrevistas:** `app/Models/Interview.php`
  (validación dominio: no solape con entrevistas ni con blocks)
* **Bloqueos:** `app/Models/CalendarBlock.php`
* **Filament – Interviews:** `app/Filament/Resources/Interviews/...`
* **Filament – Calendar Blocks:** `app/Filament/Resources/CalendarBlocks/...`
* **OAuth:** `app/Http/Controllers/GoogleAuthController.php`
* **Servicio Google:** `app/Services/GoogleCalendarService.php`
* **Observers (entrevistas):** `app/Observers/InterviewObserver.php`
* **Job de bloques:** `app/Jobs/SyncBlocksRangeJob.php`
* **Comando interviews:** `app/Console/Commands/GoogleSyncInterviews.php`
* **Config dueño:** `config/owner.php`

### Rutas

* `GET /auth/google/redirect` → inicia OAuth
* `GET /auth/google/callback` → callback de Google

## Colas en producción

* **Horizon (Redis)** recomendado:

  ```bash
  composer require laravel/horizon
  php artisan horizon:install
  php artisan migrate
  php artisan horizon
  ```

  Configurar `QUEUE_CONNECTION=redis` y un proceso para `google-sync`.

* **Supervisor** (Linux) o servicio en Windows para mantener `queue:work` activo.

## Cómo probar la validación de solapes

1. **Entrevistas**

   * Crear `10:00–11:00` → **OK**
   * Crear `10:30–11:30` → **FALLA** (solape)
   * Crear `11:00–12:00` → **OK** (borde con borde permitido)
2. **Bloqueos**

   * Crear **bloque all-day** para `YYYY-MM-DD`.
   * Intentar entrevista `13:00–14:00` ese día → **FALLA** (bloquea).
   * Crear bloque `09:00–12:00`; entrevista `12:00–13:00` → **OK**; `11:50–12:10` → **FALLA**.

## Problemas frecuentes

* **No se crean eventos en Google**
  Falta **worker**: `php artisan queue:work --queue=google-sync,default --tries=3`.
* **OAuth vuelve a login**
  Revisá `APP_URL` y **redirect URI** en Google Cloud (idénticos).
* **Eventos duplicados**
  Ocurre si se perdió `google_event_id`. Limpiar calendario de pruebas o usar `--since` en backfill.

## Estructura

```
app/
  Console/Commands/GoogleSyncInterviews.php
  Filament/Resources/{Interviews, CalendarBlocks}/...
  Http/Controllers/GoogleAuthController.php
  Jobs/SyncBlocksRangeJob.php
  Models/{Interview, CalendarBlock, GoogleToken, User}.php
  Observers/InterviewObserver.php
  Providers/AppServiceProvider.php
  Services/GoogleCalendarService.php
config/
  owner.php
database/
  migrations/
    xxxx_create_google_tokens_table.php
    xxxx_add_google_event_id_to_interviews.php
    xxxx_create_calendar_blocks_table.php
  seeders/
    AdminUserSeeder.php
```

## Convenciones de commit

Usar mensajes atómicos: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`.
Comitear `composer.lock` y `package-lock.json`; **no** comitear `.env`.

Ejemplos:

```bash
git add -A
git commit -m "feat(calendar): calendar blocks + bulk generator + async sync to Google"
git commit -m "feat(interviews): overlap validation vs blocks and interviews"
git commit -m "docs: README updated with calendar blocks, queues and usage"
```
