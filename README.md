# Recova Rentals – Admin

**Laravel 12 + Filament v4**

Panel de administración para gestionar **entrevistas** (citas) de Recova Rentals.

* CRUD de **Interviews** con validación **sin solapes** (fin > inicio + choque de horarios).
* **Policy**: solo **admin** puede **editar** entrevistas **confirmadas**.
* Seeder para crear un usuario **admin** por defecto.
* Panel accesible en **`/admin`**.

---

## Requisitos

* **PHP 8.3+** (recomendado para Laravel 12)
* **Composer 2.x**
* **Node.js 18+** y **npm 9+** (o superior)
* **MySQL/MariaDB** (u otro driver soportado por Laravel)
* Extensiones PHP típicas (mbstring, tokenizer, openssl, pdo, etc.)

> En Windows, Laragon/XAMPP funciona bien.

---

## Primer uso (clonado del repo)

> ⚠️ **No ejecutes comandos de instalación de Filament** (`filament:install`).
> El repo **ya** trae Filament configurado.

```bash
# 1) Clonar
git clone <https://github.com/HackeandoBits/recova-rentals-admin.git>
cd recova-rentals-admin

# 2) Copiar .env y configurar
cp .env.example .env
# Editá DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, etc.

# 3) Dependencias PHP
composer install

# 4) Generar APP_KEY
php artisan key:generate

# 5) Dependencias JS
# si hay package-lock.json:
npm ci
# (si no hubiera, usar: npm install)

# 6) Compilar assets
npm run build
# (en desarrollo podés usar: npm run dev)

# 7) Storage público
php artisan storage:link

# 8) Migraciones + seeders
php artisan migrate --seed
# (o solo el admin: php artisan db:seed --class=AdminUserSeeder)

# 9) Levantar servidor
php artisan serve
# Abrí http://localhost:8000/admin
```

**Credenciales admin (seeder por defecto)**

* Email: `admin@recova.com`
* Password: `recova123`

> Cambiá la contraseña en cuanto entres a Producción.

---

## ¿Qué hay en este proyecto?

### Modelos

* **User**

  * Campo `is_admin:boolean` (migración `add_is_admin_to_users_table`).
* **Interview**

  * `title`, `start_at`, `end_at`, `status: pending|confirmed|cancelled`
  * Casts: `start_at`, `end_at` → `datetime`
  * Índices: `start_at`, `end_at`, `status`.

### Validaciones

* **Sin solapes**

  * Regla: `App\Rules\NoOverlapRule`
  * Aplicada en el **form** de Filament (campo `end_at`) con:

    * chequeo **fin > inicio**, y
    * consulta que impide cruces reales (permite “borde con borde”).
* **A prueba de balas (opcional recomendado)**

  * En `App\Models\Interview::booted()`, un **hook `saving`** repite esas validaciones
    para impedir que entren solapes por seeders/API/Tinker.

### Permisos (Policies)

* `App\Policies\InterviewPolicy`

  * `update`: si `status === 'confirmed'` → **solo admin** puede editar.
  * `viewAny`: debe permitir que admin vea el listado (si lo limitás, el recurso no aparece en el menú).

### Filament (v4)

* Recurso principal:
  `App\Filament\Resources\Interviews\InterviewResource`
* Páginas: List / Create / Edit
* Descubrimiento (en `app/Providers/Filament/AdminPanelProvider.php`):

```php
->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
->discoverResources(in: app_path('Filament/Resources/Interviews'), for: 'App\\Filament\\Resources\\Interviews')
->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
```

> Importante: mantené esa **segunda línea** de `discoverResources` porque el resource vive bajo `...\Resources\Interviews\...`.

---

## Cómo probar la validación **sin solapes**

1. Crear entrevista `10:00–11:00` → **OK**
2. Crear `10:30–11:30` → **DEBE fallar** (“Existe otra reunión que se solapa…”).
3. Crear `11:00–12:00` (borde-borde) → **OK**
4. Marcar una como `cancelled` y volver a usar su franja → **OK**
5. Intentar editar para que se pise con otra → **DEBE fallar**

---

## Problemas frecuentes

* **No aparece “Reuniones” en el menú**

  * Revisá `discoverResources` en `AdminPanelProvider` (ver arriba).
  * `InterviewPolicy::viewAny()` debe permitir ver (p. ej. `return $user->is_admin;` o `true`).
  * Limpiá caches: `php artisan optimize:clear`.

* **El IDE marca clases “unknown” de Filament**

  * Suele ser el índice del IDE.
  * Ejecutá `composer dump-autoload` + `php artisan optimize:clear`, y en VS Code **Reload Window**.

* **`Failed opening required vendor/autoload.php`**

  * Falta `composer install`.

* **`Blueprint::check does not exist` al migrar**

  * Quitá cualquier `->check(...)` en migraciones (usamos validación a nivel app/modelo).

---

## Estructura relevante

```
app/
  Filament/
    Resources/
      Interviews/
        InterviewResource.php
        Pages/
          ListInterviews.php
          CreateInterview.php
          EditInterview.php
        Schemas/
          InterviewForm.php
        Tables/
          InterviewsTable.php
  Models/
    Interview.php
    User.php
  Policies/
    InterviewPolicy.php
  Providers/
    Filament/
      AdminPanelProvider.php
  Rules/
    NoOverlapRule.php
database/
  migrations/
    xxxx_add_is_admin_to_users_table.php
    xxxx_create_interviews_table.php
  seeders/
    AdminUserSeeder.php
    DatabaseSeeder.php
public/
  css/, js/, fonts/           # assets publicados por Filament/Build
```

---

## Convenciones de commit

* Commits atómicos y descriptivos.
* Comitear **`composer.lock`** y **`package-lock.json`**.
* No comitear `.env`, `vendor/`, `node_modules/`, `storage/` (revisar `.gitignore`).

Ejemplo:

```bash
git add -A
git commit -m "feat(admin): Filament resource (Interviews) con validación no-solape y admin-only edit; migraciones + seeder"
git push origin develop
```

---

## Roadmap (futuro)

* **CalendarBlock**: bloquear franjas y sumar su chequeo a `NoOverlapRule`.
* **ManualCharge**: imputar ganancias ligadas a entrevistas.
* **Integraciones**: Google Calendar (`google_event_id`), WhatsApp (notificaciones).
* **Leads** desde la web pública y conversión a `Interview`.

---