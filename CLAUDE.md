# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Stack

This is the **Laravel 12 + React 19 + Inertia.js v2** starter kit (`laravel/react-starter-kit`), used as the base for a project management app. The frontend is TypeScript/React rendered through Inertia (no separate API layer), styled with Tailwind v4 + shadcn/ui (Radix primitives), and bundled with Vite. Routes from PHP are exposed to JS via Ziggy. Tests use Pest 3. Default DB is SQLite (`database/database.sqlite`).

PHP requirement: `^8.2`. Node 22 is used in CI.

## Commands

Run from the repo root (`C:\laragon\www\project-management`).

### Day-to-day dev
- `composer dev` — runs `php artisan serve`, `php artisan queue:listen --tries=1`, and `npm run dev` concurrently. This is the canonical way to start the full stack locally.
- `npm run dev` — Vite dev server only (HMR for `resources/js`, `resources/css`).
- `npm run build` — production build.
- `npm run build:ssr` — build client + SSR bundle (entry: `resources/js/ssr.jsx`).
- `php artisan serve` — Laravel dev server only.

### Lint / format
- `vendor/bin/pint` — Laravel Pint (PHP formatter). CI runs this.
- `npm run lint` — ESLint with `--fix`.
- `npm run format` / `npm run format:check` — Prettier over `resources/`.

CI (`.github/workflows/lint.yml`) runs Pint, then `npm run format`, then `npm run lint` — match that order if reproducing failures locally.

### Tests
- `./vendor/bin/pest` — full Pest suite (this is what CI runs).
- `./vendor/bin/pest --filter=<name>` — single test by name/regex.
- `./vendor/bin/pest tests/Feature/Auth/LoginTest.php` — single file.
- `php artisan test` — Laravel wrapper around Pest, also works.

`phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` during tests, with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`. Feature tests automatically get `RefreshDatabase` via `tests/Pest.php` — Unit tests do not.

### First-time setup
After `composer install` and `npm install`: `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate`.

## Architecture

### Inertia bridge (the thing to internalize first)
Controllers return `Inertia::render('page-name', [...props])` instead of Blade views. The single Blade template is `resources/views/app.blade.php`; everything else is React. The page name maps to a file under `resources/js/pages/` — e.g. `Inertia::render('settings/profile')` resolves to `resources/js/pages/settings/profile.tsx` (see the glob in `resources/js/app.tsx`). Adding a new page means creating both the route/controller and the matching `.tsx` file under `resources/js/pages/`.

Shared props are injected by `app/Http/Middleware/HandleInertiaRequests.php::share()` and are available to every page via `usePage<SharedData>()`. Currently shared: `name`, `quote`, `auth.user`. The TypeScript shape lives in `resources/js/types/index.ts` (`SharedData`, `Auth`, `User`, `NavItem`, `BreadcrumbItem`) — keep these in sync when changing what the middleware shares.

`HandleInertiaRequests` and `AddLinkHeadersForPreloadedAssets` are appended to the `web` middleware group in `bootstrap/app.php`.

### Routes → controllers
Routes are split across three files, all loaded from `routes/web.php`:
- `routes/web.php` — public/home + authenticated `dashboard`.
- `routes/auth.php` — full auth flow (register, login, password reset, email verification, confirm password, logout). Controllers under `app/Http/Controllers/Auth/`.
- `routes/settings.php` — profile, password, appearance. Controllers under `app/Http/Controllers/Settings/`.

Form Requests live under `app/Http/Requests/Auth/` and `app/Http/Requests/Settings/`.

### Ziggy
Routes are exposed to React as a global `route()` helper (declared in `resources/js/app.tsx`). Use `route('profile.edit')` etc. instead of hardcoded URLs. After adding/renaming a named route, the helper picks it up via Ziggy's auto-injected config.

### Frontend layout
- `resources/js/app.tsx` — Inertia client bootstrap, theme init.
- `resources/js/ssr.jsx` — SSR entry (used only by `build:ssr`).
- `resources/js/layouts/` — three top-level layouts: `app/` (header and sidebar variants), `auth/` (card/simple/split), and `settings/` (nested under the app layout). Pages compose these by wrapping their content.
- `resources/js/components/ui/` — shadcn/ui components (Radix-backed). Configured by `components.json`; add new ones with `npx shadcn@latest add <name>`. Non-UI app components live one level up in `resources/js/components/`.
- `resources/js/hooks/` — `use-appearance` (theme), `use-mobile`, `use-mobile-navigation`, `use-initials`.
- Path alias: `@/*` → `resources/js/*` (configured in `tsconfig.json` and shadcn aliases).

### Modules (modular monolith)
Domain code lives under `app/Modules/<ModuleName>/` with its own `Http/Controllers`, `Http/Requests`, `Http/Middleware`, `Models`, `Services`, `Mail`, and `routes.php`. Routes are wired in by including the module's `routes.php` from `routes/web.php`. The first module is `UserManagement` — every later module (Projects, Tasks, Teams, Reports) follows the same shape.

`App\Modules\UserManagement\Services\PermissionRegistry` is the single source of truth for permissions. Add a new module's permissions there, then re-run `RolePermissionSeeder` to sync them.

### Auth & RBAC
Login is two-step: `POST /login` validates credentials, generates a 6-digit code, mails it via `TwoFactorCodeMail` (logged to `storage/logs/laravel.log` in dev because `MAIL_MAILER=log`), and redirects to `/two-factor-challenge`. The challenge page verifies the code and only then calls `Auth::login`. Codes expire after 10 minutes; resend has a 30s cooldown.

Roles are stored in `roles` and joined to users via `role_user`. Permissions live in `permissions` (slug like `users.create`) and join to roles via `permission_role`. Three system roles: `admin` (always full access — `User::hasPermission` short-circuits), `manager`, `employee`. Middleware aliases `role` and `permission` are registered in `bootstrap/app.php`; route-level guards look like `->middleware('permission:users.create')`. The shared Inertia payload exposes `auth.user.permissions`, `is_admin`, `is_manager`, `is_employee`, and `primary_role` so the React side can hide/show UI via `usePermissions()` (`@/hooks/use-permissions`).

### Default seeded users
`php artisan migrate:fresh --seed` creates: `admin@raqtan.com` (Admin), `manager@raqtan.com` (Manager), `employee@raqtan.com` (Employee) — all with password `123456789` — plus 8 sample employees. `RolePermissionSeeder` is idempotent (`updateOrCreate`).

## Conventions worth respecting

- **Pint over hand-formatting PHP** — CI will rewrite anything Pint touches.
- **Don't bypass Inertia** — adding a JSON API endpoint for a feature that has a React page is almost always wrong here; return props from the controller and read them with `usePage()`.
- **Page filenames are lowercase-kebab** (`forgot-password.tsx`, `verify-email.tsx`) and the string passed to `Inertia::render()` matches the path under `pages/` exactly (case-sensitive on Linux CI even if it works on Windows locally).
- **Feature tests get a fresh DB per test** via `RefreshDatabase` (configured globally in `tests/Pest.php`); Unit tests do not — put anything that hits Eloquent under `tests/Feature/`.
- **CI branches are `main` and `develop`** — both lint and test workflows run on push/PR to either.
