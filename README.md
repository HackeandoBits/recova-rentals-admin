# Recova Rentals – Admin

**Laravel 12 + Filament v4**

Panel de administración para gestionar **entrevistas** de Recova Rentals, con **sincronización automática** con **Google Calendar** del dueño.

## Funcionalidades

* CRUD de **Interviews** (título, inicio, fin, estado).
* Validaciones:

  * **Fin > Inicio**.
  * **No solapamiento** (permite “borde con borde”).
* **Policy**: solo **admin** puede **editar** entrevistas **confirmadas**.
* **Google OAuth** (Socialite) + **refresh automático** de token.
* **Sincronización automática** con Google Calendar:

  * Crear/editar → **upsert** del evento.
  * Cancelar/eliminar → **delete** del evento y limpia `google_event_id`.
* Comando de **backfill/recovery**:

  * `php artisan google:sync-interviews --owner [--since=YYYY-MM-DD] [--cancelled=keep|clear]`.

## Requisitos

* **PHP 8.3+**, **Composer 2.x**
* **Node.js 18+** y **npm 9+**
* **MySQL 8+** (o driver compatible)
* Extensiones PHP típicas (mbstring, pdo, openssl, etc.)

> En Windows, **Laragon** o **XAMPP** funcionan bien.

## Instalación (primer uso)

```bash
# 1) Clonar
git clone <https://github.com/HackeandoBits/recova-rentals-admin.git>
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

# 4) Generar APP_KEY
php artisan key:generate

# 5) Dependencias JS
npm ci     # (o npm install)

# 6) Compilar assets
npm run build   # (en dev: npm run dev)

# 7) Storage público
php artisan storage:link

# 8) Migraciones + seeders
php artisan migrate --seed

# 9) Levantar servidor en localhost:8000
php artisan serve --host=localhost --port=8000
```

### Credenciales admin (seeder)

* **Email:** `recovarentals@gmail.com`
* **Password:** `recova123`

> Ajustar en `database/seeders/AdminUserSeeder.php` si cambia.

## Conectar Google Calendar (una sola vez por entorno)

1. Loguearse en **`/admin`** con el usuario del **dueño**.
2. Visitar **`/auth/google/redirect`** y aceptar permisos.
3. Volverá al dashboard; se guardan tokens en `google_tokens`.

> El calendario usado por defecto es el **del dueño** (`OWNER_CAL_USER_ID`).

## Uso

* Crear/editar entrevistas desde el panel **Filament** (`/admin`).
* El sistema sincroniza automáticamente en el Google Calendar del dueño:

  * **create/update** → crea o actualiza el evento.
  * **status = cancelled** o **delete** → elimina el evento y limpia `google_event_id`.

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
```

### Migraciones

* `create_google_tokens_table`
* `add_google_event_id_to_interviews`  ← enlaza entrevista ↔ evento Google

### Código relevante

* **Tokens:** `app/Models/GoogleToken.php`
* **Entrevistas:** `app/Models/Interview.php` (incluye `google_event_id`)
* **OAuth:** `app/Http/Controllers/GoogleAuthController.php`
* **Servicio Google:** `app/Services/GoogleCalendarService.php`
* **Observer:** `app/Observers/InterviewObserver.php` (registrado en `AppServiceProvider`)
* **Comando backfill:** `app/Console/Commands/GoogleSyncInterviews.php`
* **Config dueño:** `config/owner.php` (usa `OWNER_CAL_USER_ID`)

### Rutas

* `GET /auth/google/redirect` → inicia OAuth
* `GET /auth/google/callback` → callback de Google
  *(No se exponen rutas de “sync manual”; usar el comando Artisan)*

## Comando de mantenimiento (opcional)

Para re-sincronizar/recuperar:

```bash
# usar calendario del dueño (OWNER_CAL_USER_ID)
php artisan google:sync-interviews --owner

# sólo entrevistas desde una fecha
php artisan google:sync-interviews --owner --since=2025-11-01

# borrar en Google eventos de entrevistas canceladas
php artisan google:sync-interviews --owner --cancelled=clear
```

## Cómo probar la validación de solapes

1. Crear `10:00–11:00` → **OK**
2. Crear `10:30–11:30` → **DEBE fallar** (se solapa)
3. Crear `11:00–12:00` → **OK** (borde con borde permitido)
4. Marcar una como `cancelled` y reusar su franja → **OK**

## Problemas frecuentes

* **/auth/google/callback redirige a login**
  Asegurá que usás `http://localhost:8000` y que el **redirect URI** en Google Cloud coincide exactamente.
* **No aparece evento en Calendar**
  Revisá que el **dueño** esté conectado (existe fila en `google_tokens`) y que la entrevista NO esté `cancelled`.
* **Duplicados en Calendar**
  Ocurre si perdiste `google_event_id` (p.ej. `migrate:fresh`) y los eventos existían en Google. Usá calendario de pruebas o limpiá manualmente antes del backfill.

## Estructura

```
app/
  Console/Commands/GoogleSyncInterviews.php
  Filament/Resources/Interviews/...
  Http/Controllers/GoogleAuthController.php
  Models/{Interview, GoogleToken, User}.php
  Observers/InterviewObserver.php
  Providers/AppServiceProvider.php
  Services/GoogleCalendarService.php
config/
  owner.php
database/
  migrations/
    xxxx_create_google_tokens_table.php
    xxxx_add_google_event_id_to_interviews.php
  seeders/
    AdminUserSeeder.php
```

## Convenciones de commit

Usar mensajes atómicos: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`.
Comitear `composer.lock` y `package-lock.json`; **no** comitear `.env`.

Ejemplos:

```bash
git add -A
git commit -m "feat(calendar): OAuth + refresh + autosync de entrevistas con Google Calendar"
git commit -m "db(interviews): add google_event_id para vincular eventos"
git commit -m "docs: README con setup y comandos de backfill"
```
