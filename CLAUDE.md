# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Stack

**Laravel 12 + React 19 + Inertia.js v2** (based on `laravel/react-starter-kit`), built out into a task management / workspace app ("Raqtan TMS"). The frontend is TypeScript/React rendered through Inertia — there is no separate API layer. Tailwind v4 + shadcn/ui (Radix), bundled with Vite. PHP routes reach JS via Ziggy's global `route()`. Tests use Pest 3.

**Database: MySQL only.** MySQL 5.7 (Laragon), database `project_management` on `127.0.0.1:3306`. The test suite runs on MySQL too, against a separate `project_management_test` database, so engine-specific behaviour — reserved words like `group`, JSON functions, index key limits — is exercised rather than hidden behind SQLite's leniency. CI spins up a `mysql:5.7` service container.

The `sqlite` connection has been **removed** from `config/database.php`, and `default` is now `mysql`. That connection used to fall back to `env('DB_DATABASE')` for its file path, so anything run with `--database=sqlite` silently created a stray SQLite file named after the MySQL database in the repo root. Selecting it now fails loudly instead.

Bear MySQL 5.7 in mind when writing queries: no CTEs, no window functions. JSON functions are available (5.7.8+).

`composer.json` requires PHP `^8.2`; CI runs PHP 8.4 and Node 22.

## Commands

Run from the repo root (`C:\laragon\www\project-management`).

### Day-to-day dev
- `composer dev` — runs `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan schedule:work` and `npm run dev` concurrently. Canonical way to start the full stack; the scheduler is included so due-date and overdue notifications fire in development without any extra step.
- `npm run dev` — Vite dev server only.
- `npm run build` / `npm run build:ssr` — production build; the `:ssr` variant also builds the SSR bundle from `resources/js/ssr.jsx`.

### Scheduled work
The reminder jobs (`tasks:due-tomorrow`, `tasks:due-today`, `tasks:overdue`) are registered in `routes/console.php` and run automatically under `composer dev`. On a server they need one cron entry:

```
* * * * * cd /path/to/project-management && php artisan schedule:run >> /dev/null 2>&1
```

`php artisan schedule:list` shows what is registered. `php artisan tasks:remind` and `php artisan tasks:overdue` trigger them immediately for testing.

### Lint / format
- `vendor/bin/pint` — Laravel Pint (PHP formatter).
- `npm run format` / `npm run format:check` — Prettier over `resources/`.
- `npm run lint` — ESLint with `--fix`.

CI (`.github/workflows/lint.yml`) runs Pint → `npm run format` → `npm run lint` in that order; match it when reproducing failures.

### Tests
- `./vendor/bin/pest` — full suite (what CI runs). `php artisan test` also works.
- `./vendor/bin/pest --filter=<name>` — single test by name/regex.
- `./vendor/bin/pest tests/Feature/DashboardTest.php` — single file.

`phpunit.xml` forces `DB_CONNECTION=mysql` against `project_management_test` (so tests never touch the dev database), plus `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`. Create that database once before the first run:

```
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS project_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```
**Two people running the suite at once will collide.** `RefreshDatabase` runs `migrate:fresh`, so a second run against the same database hits `Base table … doesn't exist` or an InnoDB deadlock — failures that look like regressions and are not. Give a concurrent run its own database:

```
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS pms_test_mine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_DATABASE=pms_test_mine ./vendor/bin/pest
```

The shell variable wins because `phpunit.xml` sets `<env>` without `force="true"`. (`pest -d DB_DATABASE=…` does **not** work — `-d` is a PHP ini directive.)

 `tests/Pest.php` applies `RefreshDatabase` to `tests/Feature` only — anything touching Eloquent belongs under `Feature/`.

Task Management has a feature suite under `tests/Feature/Tasks/` (defect regressions, security/permission boundaries, workflow + links + bulk + notifications, reporting correctness, per-person chains in `TaskChainTest`, and the manager-only lifecycle in `TaskLifecycleTest`). `TaskHelpers` seeds permissions, roles and workflows and builds users with an exact permission set. Other modules still have no tests.

### Reset / seed
`DatabaseSeeder` is the **production** seed and contains no sample content: `RolePermissionSeeder` → `DepartmentSeeder` → `TeamSeeder` → `OrganizationSeeder` → `WorkflowSeeder` → `PermissionSchemeSeeder`, in that order (permissions before the roles that reference them, departments and teams before the people assigned to them). Every one is idempotent, so `php artisan db:seed` is safe to re-run after a release to pick up new permissions.

`OrganizationSeeder` seeds the real organisation — one Super Admin (`asif@bargoventures.com`) plus the DTT / HR / IT roster, all `@bargoventures.com`. Credentials come from `config/rbac.php` (`SUPER_ADMIN_*`, `SEED_DEFAULT_PASSWORD`), and a password is only ever applied when the account is **created**, so re-seeding never resets a live password. It also deletes accounts on the domains listed in `RBAC_PURGE_DOMAINS` (default `raqtan.com`, the superseded demo accounts); accounts an admin created through the UI are untouched.

Sample projects, tasks, threads, notifications and activity live in `DemoSeeder` and are deliberately **not** part of `db:seed`:

```
php artisan db:seed --class=Database\Seeders\DemoSeeder
```

`tests/Feature/Rbac/` locks the seeded org and its scoping — who exists, what they hold, and what they must not hold.

### First-time setup
After `composer install` / `npm install`: `cp .env.example .env` (it already ships the MySQL block below), `php artisan key:generate`, create the database, then `php artisan migrate --seed`.

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_management
DB_USERNAME=root
DB_PASSWORD=
```

Create the schema (`mysql -u root -e "CREATE DATABASE project_management"` or via Laragon/HeidiSQL), then `php artisan migrate --seed`. Verify with `php artisan db:show`.

## Architecture

### Inertia bridge (internalize this first)
Controllers return `Inertia::render('page-name', [...props])` instead of Blade views. The only Blade template is `resources/views/app.blade.php`. The page name maps to a file under `resources/js/pages/` — `Inertia::render('tasks/index')` resolves to `resources/js/pages/tasks/index.tsx` (glob in `resources/js/app.tsx`). A new page means creating both the route/controller and the matching `.tsx`.

Shared props come from `app/Http/Middleware/HandleInertiaRequests.php::share()`: `name`, `auth.user` (a hand-built array — id, profile fields, `initials`, `roles[]`, `primary_role`, `permissions[]`, `is_admin` / `is_manager` / `is_employee`), `flash.status`, `flash.error`, and lazily `notifications.unread`. The TS shapes live in `resources/js/types/index.ts` (`SharedData`, `AuthUser`, `NavItem`, `RoleSlug`, …) — **keep them in sync when changing what the middleware shares**.

### Modular monolith
Domain code lives in `app/Modules/<Module>/` with its own `Http/Controllers`, `Http/Requests`, `Models`, `Services`, sometimes `Http/Middleware` / `Mail`, and a `routes.php`. Every module's `routes.php` is `require`d at the bottom of `routes/web.php` — adding a module means adding that one line.

Current modules: `UserManagement`, `ProjectManagement`, `TaskManagement`, `Workflow`, `Teams`, `MeetingManagement`, `Okrs`, `Feedback`, `Communication`, `NotificationCenter`, `Reporting`.

There is deliberately **no finance/expense module**. Expense submission and approval, along with project budgets and currency, were removed wholesale — see `database/migrations/2026_09_08_000010_drop_expense_management.php`. Nothing in the app tracks money.

Cross-module coupling is by direct import (e.g. `TaskService` injects `NotificationCenter\Services\NotificationService`; task models reference `ProjectManagement\Models\Project`). There is no event bus or boundary enforcement — import across modules directly, but keep the dependency direction sane: feature modules depend on `NotificationCenter` / `UserManagement`, not the reverse.

### Task workflow engine (read this before touching statuses)
Task statuses are **no longer a PHP constant**. They live in `workflow_statuses`, grouped into a `workflow`, and a project points at one via `projects.workflow_id`. `tasks.status` stores the status *key* (denormalised so board grouping and filters never need a join).

- `App\Modules\Workflow\Services\WorkflowService` resolves a project's statuses and decides whether a transition is legal. It memoises per request.
- `workflow_transitions` rows constrain movement. **An empty transition set means unrestricted** — you only add rows when you want to restrict.
- Every status change writes a `task_status_history` row carrying the duration of the previous status; cycle time and aging reports are computed from it.
- `Task::STATUSES` still exists but is only a pre-seed fallback. Resolve statuses through `WorkflowService`, and on the client read the `statuses` prop rather than a hardcoded map.

Seeded by `WorkflowSeeder`: **Software delivery** is the default and only pipeline - To do -> Dev In progress -> Pushed for QA -> QA In progress -> Review -> Ready for deployment -> Deployed. Four edges are reason-gated via `requires_comment` + `comment_label` (QA pass/fail, and Review sending work back to dev or QA).

Workflows are edited in the UI at `/workflows` (permission `workflows.manage`). `WorkflowService` memoises the graph per request; model events on `Workflow`, `WorkflowStatus`, `WorkflowTransition` and `WorkflowAssignment` call `WorkflowService::flushCache()` so an admin's edit applies to the very next status change.

#### Per-person and per-team chains

A workflow can also be assigned to a **person** or a **team** (`workflow_assignments`, one row per subject, edited from the People and Teams panels on `/workflows/{workflow}/edit`). The table ships **empty**, which is what keeps everyone on the same stages today — an assignment is opt-in, one person or one team at a time.

An assigned chain **only subtracts**. The project's workflow still defines the graph, the rules and the board columns; `WorkflowService::chainPermits()` then filters the stages that person may move work *into*. Two consequences worth internalising:

- The filter lives inside `resolveTransition()`, which every caller goes through — the status menu, board drag-and-drop, the bulk endpoint and `TaskService`'s write validation. They cannot disagree about what a move means.
- A chain can never *add* a stage: the target must exist in the project's workflow too, so the effective set is the intersection. A chain naming a stage the project lacks simply offers nothing.

Resolution order is the person's own assignment, then the oldest assignment among the teams they belong to (`WorkflowService::chainFor()`), then no narrowing. Board columns, the initial status and reporting are untouched — everyone still sees the same board, they are just offered different moves on it.

### Task events and policies
Task mutations dispatch domain events (`TaskCreated`, `TaskUpdated`, `TaskStatusChanged`, `TaskAssigned`, `TaskCommented`) wired in `App\Providers\TaskEventServiceProvider`. Listeners own the side effects:
- `RecordTaskActivity` — audit rows + status history.
- `SendTaskNotifications` — every task notification, with the recipient rules (assignee + reporter + watchers + team, minus the actor) in one place.

Add a new side effect by adding a listener, not by editing the service.

**Authorisation for tasks goes through `TaskPolicy`**, registered in the same provider. Route middleware is a coarse first gate; the policy combines the permission with an object-scope check so a user can never mutate a task they cannot see. Use `$this->authorize(...)` / `$user->can(...)`, not ad-hoc permission checks.

**Taking work off the board is a manager's call.** `delete`, `archive` and `viewTrash` also require `TaskPolicy::manageLifecycle()` — the `manager` role, with Super Admin passing through `before()`. The permission alone is deliberately not enough: a responsibility bundle or a direct grant can carry `tasks.delete`, and none of those should imply the authority to remove somebody else's task. Archive, unarchive, delete, restore and the bulk equivalents all funnel through those two abilities, and `tasks.index` ships a `can.lifecycle` prop so the bulk bar and the trash link are hidden rather than offered and then refused. A test needing this authority builds its actor with `TaskHelpers::managerWith()`.

### Project permission schemes
Workspace roles set the ceiling; a **permission scheme** decides who, inside one project, may actually use a permission. `ProjectPermissionResolver::allows()` (in `UserManagement/Services`) applies two gates in order: the user's role must hold the permission, then a grant in the project's scheme must match them. Only `ProjectPermissionResolver::GOVERNED` permissions consult a scheme, and **a permission with no grants in the scheme is unrestricted** - schemes narrow, they never escalate.

Grant types: `everyone`, `project_owner`, `any_member`, `project_role` (owner/lead/member/viewer), `assignee`, `reporter`, `team`, `user`, `workspace_role`. A project with no scheme falls back to the default one. `PermissionSchemeSeeder` ships **Open** (the default, fully permissive - so adopting schemes is opt-in) and **Delivery lead**. Edited at `/permission-schemes`; the resolver's caches are flushed by model events and by project membership sync.

`TaskPolicy` and `ProjectPolicy` both route their capability checks through the resolver, passing the task so assignee/reporter grants can resolve.

### Sprints and estimation
`sprints` belong to a project; `tasks.sprint_id` and `tasks.story_points` carry the planning data. A project runs **at most one active sprint** — `SprintService::start()` enforces it, and refuses to start an empty sprint. Completing one asks where unfinished work goes (backlog or the next sprint); finished work stays with the sprint that finished it.

Burndown cannot be reconstructed from `tasks` alone (re-pointing or moving a task erases its own history), so `sprint_snapshots` holds one row per sprint per day. `SprintService::snapshot()` is idempotent; the scheduler runs `sprints:snapshot` nightly at 23:50, and `start()` takes a day-zero reading so a short sprint still has a curve. Velocity reads *committed* from the first snapshot, not from current scope, so work pulled in mid-sprint cannot flatter it.

Screens: `/projects/{project}/backlog` and `/projects/{project}/sprint-report`.

### Removed features
There is deliberately **no calendar, no timeline, no custom fields and no template system.** Each was removed wholesale rather than left disabled:

- Custom fields (`custom_fields`, `custom_field_values`) and the timeline view — see `database/migrations/2026_09_09_000004_drop_custom_fields_and_timeline.php`. A task carries only its built-in fields; nothing reads a user-defined one.
- The month calendar, task templates, recurring work and project templates — see `database/migrations/2026_09_09_000003_drop_templates_and_calendar.php`. Nothing reuses a saved task or project shape, and no task is created on a schedule.
- Expenses, project budgets and currency — see `database/migrations/2026_09_08_000010_drop_expense_management.php`. Nothing tracks money.

`tasks.start_date` and `tasks.due_date` survived the timeline: they are ordinary task fields the form, the exports and the sort options still use. The shared task filter keys remain `project` (**by slug**) and `assignee` — not `project_id`/`assignee_id`.

### Rich text
`components/rich-text.tsx` provides `RichText` (renderer) and `RichTextEditor` (textarea + toolbar), used for task descriptions and comments. It renders a small Markdown subset **to React elements, never to an HTML string** — no `dangerouslySetInnerHTML` anywhere — so it is XSS-safe by construction. Stored values stay plain text, so nothing needed migrating and the text degrades readably wherever it is not rendered.

### Search
`app/Modules/Search` answers one question of every entity the user can see (tasks, projects, comments, people, teams, meetings, objectives, feedback). Each source is a separate small query — they share nothing but a title, and the visibility rules differ per entity — and **each is responsible for its own scoping**: a search result must never be how someone discovers a record they cannot open. A section the user lacks permission for is not offered at all.

`/search` is the results page; `/search/quick` is the command palette's data source and is the one place in the app that answers JSON rather than an Inertia page (⌘K, `components/command-palette.tsx`). Matching is `LIKE '%term%'` deliberately, not FULLTEXT — InnoDB defers full-text index updates to commit, so `MATCH … AGAINST` returns nothing for rows written inside the open transaction every test runs in. Adopting it would make search behave differently under test than in production, and trade substring matching for word-prefix matching.

### Reporting extras
`/reports/flow` is the cumulative flow diagram. `task_status_history` records the status each task moved *to*, so a task's stage on a past day is its last transition on or before that day; with no window functions on 5.7, `CumulativeFlowService` walks each task's transitions once in PHP and fills a day-by-stage grid.

`/reports/export/tasks` streams CSV (UTF-8 BOM + `fputcsv`, so Excel opens it without an import dialog) and `/reports/print` renders a layout-free page the browser turns into a PDF — better output than a server-side renderer, and it stays selectable. Both honour the current filters and the caller's visibility.

### Sub-tasks
Nesting is bounded at `SubtaskRollupService::MAX_DEPTH` levels **including the root**, and `canNest()` refuses any move that would make a task its own ancestor. With no recursive CTE on 5.7, `descendants()` is a bounded breadth-first walk — at most MAX_DEPTH queries however wide the tree.

When the last sub-task lands, the parent's owner is **notified, not auto-transitioned** — closing someone else's task without asking is how people stop trusting a board.

### Comments
`task_comments.is_internal` marks a note visible only to people who can edit the task, and it is **filtered server-side in `TaskController::commentTree()`** — a payload the client is trusted to conceal is not a permission. Internal notes never fire mention notifications. Every edit writes the replaced text to `comment_revisions`.

`MentionParser` resolves `@token` against the local part of an email, a whole name, or the start of a name word, with a 3-character minimum (it previously matched `name LIKE %token%` with no floor, so `@a` notified ten arbitrary people). `@team-slug` mentions everyone on that team.

### Automation rules
`app/Modules/Automation` implements "when -> if -> do". `RunAutomationRules` listens to the five task events and flattens each into a context array; `AutomationEngine` matches conditions (ANDed; an empty set always matches) and executes actions in order.

Two invariants the engine exists to protect: **a broken rule never breaks a task** (every rule and action is wrapped; failures become `automation_runs` rows) and **rules never loop** (`MAX_DEPTH`, plus a per-chain record of which rule already ran on which task). Use `AutomationEngine::withoutRules(fn () => ...)` in seeders or tests that must not fire rules; `AutomationEngine::reset()` runs in `tests/Pest.php`.

Actions go through `TaskService` where a person would, so history, notifications and workflow rules all still apply - which is why an automation moving a task along an illegal transition records a failed run rather than forcing it.

### Controller → Service → Model pattern
Controllers stay thin: validate with a Form Request, delegate writes to the module's `Service`, then `Inertia::render()` or `redirect()`. Services own the transactional work and consistently do three things together (`TaskManagement/Services/TaskService.php` is the reference implementation):

1. wrap in `DB::transaction()`,
2. write an audit row with `Activity::logFor($action, $subjectType, $subjectId, [...])` — see **Audit trail** below,
3. push in-app notifications via `NotificationService::push($userIds, $payload, $actorId)` — the actor is automatically excluded, and `link` is built with `route(..., false)` to get a relative path.

Follow all three when adding a mutating operation; the activity log page and the notification bell both depend on it.

### Audit trail
`activities` is the single audit log for the whole application — every module writes to it, and there is no second one. Three rules keep it trustworthy, all enforced in `UserManagement/Models/Activity.php`:

- **Append-only.** `updating` and `deleting` throw. History cannot be rewritten by a user or by a bug. A decommissioned module's rows are purged with the query builder inside a migration (see `drop_expense_management`), which is a reviewed schema change rather than something a request can reach.
- **Indexed by subject, not by JSON.** `subject_type` + `subject_id` carry what `properties->task_id` used to, so "everything that happened to this task" is an index lookup on `activities_subject_idx` instead of a scan of every row in the module. **Never** query a timeline with `whereJsonContains`.
- **Deterministically ordered.** Several rows routinely share a `created_at` (one transaction writing an update, a status change and an assignment), so every reader goes through `chronological()`, which breaks the tie on `id`. Ordering by `created_at` alone can repeat or skip a row at a page boundary.

Three writers, picked by what you have:

| Helper | Use for |
| --- | --- |
| `Activity::logFor($action, $type, $id, [...])` | An action against a subject — the normal case. |
| `Activity::logChanges($action, $type, $id, $changes, [...])` | A field diff. **Writes nothing when `$changes` is empty**, which is the guard against logging saves that changed nothing. |
| `Activity::log($action, [...])` | Only when there is genuinely no single subject (a bulk operation, an export). |

Subjects are `Activity::SUBJECT_TASK`, `SUBJECT_PROJECT`, `SUBJECT_TEAM`. Reading: `forSubject($type, $id)`, or `forProjectTimeline($projectId, $taskIdsQuery)` for a project's own events plus its tasks' — pass a *query*, not an array, so a large project never materialises its ids.

Two conventions that keep the log meaningful rather than merely large:

- **Status is never in a diff.** A stage change gets its own row (`task.completed`, `task.reopened`, `task.status-changed`, and the `project.*` equivalents) and is excluded from the `TRACKED` field list, so a move is never reported twice. Completion and reopening are named because they are the two moments worth finding.
- **The actor is passed, not inferred.** `Activity::log()` takes `user_id` from the caller and only falls back to `auth()->id()`. A queued job, console command or automation rule acts on someone's behalf without being logged in, and would otherwise be recorded as nobody.

`task_status_history` is a separate, narrower table and is *not* a competing log: it stores the duration of each status interval, which is what the cycle-time and aging reports are computed from. `RecordTaskActivity` writes both.

Covered by `tests/Feature/Audit/AuditTrailTest.php` — completeness, structured before/after, ordering under a frozen clock, no-op suppression, immutability, transaction rollback, and an `EXPLAIN` assertion that the timeline still uses its index.

### Auth & RBAC
**Self-registration is removed.** Users are created by admins through the User Management module. `routes/auth.php` covers login, two-factor challenge, password reset, and confirm-password only.

Login is two-step: `POST /login` validates credentials, generates a 6-digit code, mails it via `TwoFactorCodeMail` (goes to `storage/logs/laravel.log` in dev because `MAIL_MAILER=log`), and redirects to `/two-factor-challenge`. The challenge page verifies the code and only then calls `Auth::login`. Codes expire after 10 minutes; resend is throttled (`throttle:6,1` plus a 30s cooldown).

Roles live in `roles`, joined to users via `role_user`; permissions in `permissions` (slug like `tasks.create`), joined to roles via `permission_role` **and** directly to users via `permission_user`. A user's effective set is the union of the two — `User::permissions()`.

**Roles stay deliberately few**, and adding one should be a last resort. Three system roles: `admin` (displayed as *Super Admin*; full access — `User::hasPermission()` short-circuits), `manager` (workspace-wide delivery management, no security-critical administration), `employee` (the base role everyone holds). A narrower responsibility — "backend & UI lead", "product manager" — is a **direct grant**, not a new role: define the bundle in `App\Modules\UserManagement\Services\ResponsibilityRegistry` and attach it with `User::syncDirectPermissionsBySlug()`.

Departments (`departments`, referenced by `users.department_id`) are DTT / HR / IT. Department membership carries **no permissions** — it is what distinguishes an HR employee from a DTT one, nothing more. Scope comes from project membership, task assignment and team leadership.

`App\Modules\UserManagement\Services\PermissionRegistry` is the single source of truth for permission slugs, grouped by module. Add a module's permissions there, then re-run `RolePermissionSeeder` — it also **deletes** rows for slugs no longer in the registry, so a removed module cannot leave a dangling grant. Note that not every module has its own permission group — `Communication` and `NotificationCenter` piggyback on the project/task permissions.

Guarding routes: middleware aliases `role` and `permission` are registered in `bootstrap/app.php`, but module route files use the class form — `->middleware(EnsurePermission::class.':tasks.view')`. Match the surrounding file's style.

### Row-level visibility
Permission checks gate *routes*; `scopeVisibleTo(User)` gates *rows*. `Project::visibleTo()` returns everything for admins and anyone holding `projects.view-all`, otherwise only projects the user owns or is a member of. `Task::visibleTo()` is the same idea via `tasks.view-all`, assignee, reporter, team or project membership. Always apply the scope in index/show queries — permission middleware alone does not scope data.

Both scopes also widen for a **team lead** (`teams.lead_id`, or a `lead` row on `team_user`): they see their teams' tasks and the projects those teams work in, via `User::ledTeamIds()` / `ledTeamMemberIds()`. That is how a lead reaches their team's work without being handed a workspace-wide `*.view-all`.

`Project` overrides `getRouteKeyName()` to `slug`, so project route model binding is by slug, not id. Tasks bind by id.

### Frontend
- `resources/js/lib/navigation.ts` — `buildSidebarSections(user)` builds the entire sidebar, filtering each item by its `permission`. A module's nav entry goes here, not in the sidebar component.
- `resources/js/hooks/use-permissions.ts` — `usePermissions()` returns `{ user, can, canAny, is, primaryRole }`; `can()` short-circuits true for admins. Use it to hide UI, never as the only guard.
- `resources/js/layouts/` — `app/` (header and sidebar variants), `auth/` (card/simple/split), `settings/` (nested inside the app layout). Pages compose these by wrapping their content.
- `resources/js/components/ui/` — shadcn/ui (Radix-backed), configured by `components.json`; add with `npx shadcn@latest add <name>`. Domain components (`task-kanban`, `project-form`, `comment-thread`, `notification-bell`, `charts/`) live one level up in `components/`.
- `resources/js/lib/` — shared display helpers: `projects.ts`, `tasks.ts`, `utils.ts` (`cn`, `formatBytes`).
- Path alias `@/*` → `resources/js/*` (`tsconfig.json` + shadcn aliases).

### File attachments
Task attachments and project attachments each store `disk` + `path` on the row and read back through `Storage::disk($row->disk)`. Attachment models delete the underlying file in a model `deleting` hook — don't bypass the model when removing rows.

## Conventions worth respecting

- **Pint over hand-formatting PHP** — CI rewrites anything Pint touches.
- **Don't bypass Inertia** — adding a JSON endpoint for a feature that has a React page is almost always wrong; return props from the controller and read them with `usePage()`.
- **Page filenames are lowercase-kebab** (`two-factor-challenge.tsx`, `forgot-password.tsx`), and the string passed to `Inertia::render()` matches the path under `pages/` exactly (case-sensitive on Linux CI even when Windows lets it slide).
- **Use Ziggy's `route()` in React**, not hardcoded URLs; named routes are picked up automatically.
- **CI branches are `main` and `develop`** — lint and test workflows run on push/PR to both.
