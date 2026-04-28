# System Audit & Gap Analysis vs Fellow + Jira

> Generated **2026-04-28**. Codebase: Laravel 12 + React 19 + Inertia.js v2 modular monolith. Branch: `main`.

This document inventories everything currently shipped in the project-management application, compares feature parity against Fellow.app (meeting/OKR platform) and Atlassian Jira (issue tracker / agile PM), and proposes a phased roadmap to close the gaps.

---

## 1. Module Inventory (current system)

The application is structured as a modular monolith. Each module lives under `app/Modules/<Name>` with its own `Models`, `Http/Controllers`, `Http/Requests`, `Http/Middleware`, `Services`, optional `Mail`, and a `routes.php` included from `routes/web.php`.

### 1.1 UserManagement
- **Models**: `Role`, `Permission`, `Activity`, `TwoFactorCode`
- **Controllers**: `UserController` (CRUD), `RoleController` (CRUD + permission sync + rename), `AuthenticatedSessionController` (login + 2FA), `ActivityLogController`
- **Services**: `PermissionRegistry` (44 permission slugs across 10 modules), `TwoFactorService` (6-digit email OTP, 10-minute TTL, 30s resend cooldown), `UserService`
- **Auth**: Two-step login — credentials → email OTP → `Auth::login`. Roles: `admin`, `manager`, `employee`. Admin bypass in `User::hasPermission`.
- **Routes**: ~36 endpoints under `/users`, `/roles`, `/activity`. Granular permission middleware.

### 1.2 ProjectManagement
- **Models**: `Project` (status, priority, color, dates, budget, currency, progress, owner, members pivot), `Milestone` (project_id, title, due_date, completed_at, position)
- **Service**: `ProjectService` — `create` (with member sync, milestone replacement), `update`, `delete`, `toggleMilestone` (auto-recomputes progress %). Emits `project.added` notifications.
- **Multi-currency**: SAR, PKR, USD.

### 1.3 TaskManagement
- **Models**: `Task` (subtasks via `parent_task_id`, status/priority/position, soft deletes, **`estimate_minutes`** + **`timeLogs` relation**), `TaskComment` (threaded + mentions), `TaskAttachment`, **`TimeLog`** (task_id, user_id, minutes, started_at, note)
- **Service**: `TaskService` — `create`, `update`, `changeStatus` (column resequence), `delete`, `attachFile`/`downloadAttachment`/`deleteAttachment`, `addComment`, **`logTime`**, **`deleteTimeLog`**
- **Routes**: 18 endpoints — CRUD, status, comments, attachments, time logs

### 1.4 NotificationCenter
- `Notification` model with groups (tasks, projects, expenses, mentions, deadlines, system) and unread tracking.
- `NotificationService::push(userIds, payload, actorId)` consumed by Task and Project services.

### 1.5 Communication
- `ProjectComment` (threaded + mentions), `MentionParser` service (extracts and dispatches mention notifications).

### 1.6 Teams
- `Team` model with lead, member pivot (role), color, soft deletes. Full CRUD.

### 1.7 ExpenseManagement
- `Expense` model — auto-reference (`EXP-XXXXXXXX`), category taxonomy (travel/meals/supplies/software/services/hardware/subscriptions/other), approval workflow (pending → approved/rejected), receipt file, multi-currency.
- `ExpenseService` — `create`, `decide`, `delete`.

### 1.8 Project & Task attachments
File uploads live with the entity they belong to: `ProjectAttachment` is owned by ProjectManagement, `TaskAttachment` by TaskManagement. There is no standalone "Files" module or global file browser — files are reached from the project or task they're attached to. Permissions reuse `projects.update` / ownership and `tasks.update` / ownership respectively.

### 1.9 Reporting
- Basic `ReportController` with index + export (no chart engine, no saved widgets).

### 1.10 Auth scaffolding (Laravel default)
- Settings: profile, password, appearance.
- Email verification, password reset, confirm-password.

---

## 2. Capability Matrix — Current vs Jira vs Fellow

Status legend: ✅ shipped · ⚠️ partial · ❌ missing

| Capability | Current | Jira | Fellow | Priority |
|---|---|---|---|---|
| Tasks, subtasks, assignees | ✅ | ✅ | — | — |
| Comments, mentions, threads | ✅ | ✅ | ✅ | — |
| Attachments | ✅ | ✅ | ✅ | — |
| Activity log / audit | ✅ | ✅ | ✅ | — |
| Notifications (in-app) | ✅ | ✅ | ✅ | — |
| Permissions (RBAC) | ✅ granular | ✅ | ✅ | — |
| 2FA | ✅ email OTP | ✅ TOTP/SMS | ✅ | Med — add TOTP |
| Projects, members, milestones | ✅ | ✅ | — | — |
| **Estimates & time tracking** | ✅ shipped 2026-04-28 | ✅ | — | — |
| **Watchers / followers** | ❌ | ✅ | ✅ | **P0** |
| **Task dependencies (blocks/blocked-by)** | ❌ | ✅ | — | **P0** |
| **Labels / tags (polymorphic)** | ❌ | ✅ | ✅ | **P0** |
| **Sprints + story points** | ❌ | ✅ | — | **P1** |
| **Epics + roadmap** | ❌ | ✅ | — | P1 |
| **Custom workflows / status transitions** | ❌ hardcoded | ✅ | — | P1 |
| **Custom fields** | ❌ | ✅ | — | P2 |
| **Kanban / Scrum boards** | ⚠️ filter-only | ✅ | — | P1 |
| **Backlog management** | ❌ | ✅ | — | P1 |
| **Burndown / velocity charts** | ❌ | ✅ | — | P2 |
| **Saved filters / JQL-equivalent** | ⚠️ basic | ✅ | — | P2 |
| **Releases / versions** | ❌ | ✅ | — | P2 |
| **Webhooks** | ❌ | ✅ | ✅ | P2 |
| **Email-to-task** | ❌ | ✅ | ✅ | P3 |
| **Meetings + agendas** | ✅ shipped 2026-04-28 | — | ✅ | — |
| **Action items from meetings** | ✅ shipped 2026-04-28 (with convert-to-task) | — | ✅ | — |
| **Recurring meetings** | ✅ shipped 2026-04-28 (daily/weekly/biweekly/monthly) | — | ✅ | — |
| **1:1 templates + history** | ✅ shipped 2026-04-28 (templates + series view) | — | ✅ | — |
| **OKRs / Goals / KPIs** | ✅ shipped 2026-04-28 (Objective + KR + KrUpdate) | ⚠️ via Atlassian Goals | ✅ | — |
| **Calendar sync (Google/Outlook)** | ❌ excluded by user | ⚠️ | ✅ | — |
| **Meeting notes + AI summaries** | ⚠️ notes shipped, summary column exists, AI hookup deferred | — | ✅ | P3 |
| **360 / peer feedback** | ✅ shipped 2026-04-28 (cycles, questions, requests, responses) | — | ✅ | — |
| **Slack / Teams integration** | ❌ deferred — needs OAuth credentials | ✅ | ✅ | P2 |
| **Public REST API + tokens** | ❌ | ✅ | ✅ | P1 |
| **Dashboards (configurable widgets)** | ⚠️ basic | ✅ | ✅ | P1 |
| **Global search (cross-entity)** | ❌ | ✅ | ✅ | P1 |
| **Portfolio / program view** | ❌ | ✅ | — | P2 |
| **Templates (project/task/meeting)** | ❌ | ✅ | ✅ | P1 |

---

## 3. Phased Roadmap

### Phase 1A — Jira agile-core foundations (small, mergeable slices)

| # | Slice | Status |
|---|---|---|
| 1A.1 | **Time tracking** — `estimate_minutes` on tasks, `time_logs` table, log/delete UI on task show, progress bar | **✅ shipped 2026-04-28** |
| 1A.2 | **Watchers** — polymorphic `watchers` table on Task/Project, watch button + auto-watch rules, "Watching" filter | pending |
| 1A.3 | **Labels** — polymorphic `labels` + `labelables` tables, color/slug, multi-attach UI, label filter chips | pending |
| 1A.4 | **Task dependencies** — `task_links` (blocks/blocked-by/relates-to), cycle detection on store, badge on task show, "Blocked" filter | pending |

### Phase 1B — Agile workflow (2 sessions)
- 1B.1: `Sprint` model + `story_points` on Task. Sprint board page (lanes by status, drag to reorder, "Start sprint"/"Complete sprint" actions).
- 1B.2: Custom workflow per project — `project_workflows` (status list) + `workflow_transitions` (allowed from→to with optional permission guard). Replace hardcoded `Task::STATUSES` with project-resolved set.
- 1B.3: Epic linkage — `epic_id` on Task, roadmap timeline view with date-range bars.

### Phase 2 — Fellow parity, meetings ✅ shipped 2026-04-28
- 2A: `Meeting`, `Agenda`, `AgendaItem`, `ActionItem` models. Recurrence rule (daily/weekly/biweekly/monthly via `RecurrenceExpander`). Convert action items to Task (preserves `meeting_id` link via `task_id` on the action item).
- 2B: `MeetingTemplate` model + library page + auto-fill agenda when template selected. Series view shows all recurring occurrences and lets you jump between them.
- 2C: ICS export feed — *deferred* (still pending). Full Google/Outlook OAuth sync excluded by user.

### Phase 3 — OKRs ✅ shipped 2026-04-28
- `Objective` (period, owner, parent for alignment, team_id, project_id, visibility) → `KeyResult` (metric_type number/percentage/currency/boolean, start/target/current, status) → `KrUpdate` (value snapshots with confidence + note + recorder). Per-KR `progress` accessor; objective `progress` recomputed as KR average on every update.

### Phase 3.5 — Feedback ✅ shipped 2026-04-28
- `FeedbackCycle` (kind: peer/360/manager/self/team, status: draft/active/closed, anonymous flag) + `FeedbackQuestion` (text/rating/yes_no, required, position) + `FeedbackRequest` (subject, reviewer, status: pending/submitted/declined) + `FeedbackResponse` (per-question answer + optional 1–5 rating).
- Activate flow: cycle → service notifies every reviewer via in-app NotificationService.
- Reviewer respond page handles all three question kinds. Decline + submit are tracked.

### Phase 4 — Platform (3 sessions)
- 4A: API tokens + Sanctum + REST endpoints mirroring web routes, scoped by permission slugs.
- 4B: Webhooks — `webhook_endpoints` table, signed POSTs with HMAC, retries with backoff.
- 4C: Configurable dashboards — widget library (count, chart, list, KPI). Per-user layouts.
- 4D: Global search — Laravel Scout + Meilisearch index across Task/Project/Comment/Attachment/Meeting.

### Phase 5 — Polish (open-ended)
- TOTP 2FA (replace/augment email OTP), Slack/Teams notification connectors, AI meeting summaries (Claude), charts library (burndown/velocity), generic custom-fields engine.

---

## 4. Architectural Notes

- **No JSON API** — Inertia returns props from controllers. New features should follow the same pattern unless an external integration is required.
- **PermissionRegistry is the seed source** — all new modules add slugs there and run `RolePermissionSeeder`.
- **Activity logging is opinionated** — every state change should append to `activities` with `module`, `action`, `description`, and a `properties` JSON containing the entity ids (search uses `whereJsonContains('properties->task_id', ...)`).
- **NotificationService is the only path** for in-app notifications — feeds the bell + grouping. New events should use the `tasks|projects|expenses|mentions|deadlines|system` group taxonomy.
- **Soft deletes** are standard on User, Project, Task, Expense, Team, Attachment.
- **Currency** is enumerated SAR/PKR/USD. New monetary fields should reuse this list, not invent a new one.

---

## 5. Implementation Patterns Reference (for future slices)

A typical vertical slice (as demonstrated by Time Tracking) follows this checklist:

1. **Migration** under `database/migrations/2026_*.php` — additive only on existing tables; new tables get foreign keys with cascade rules and indexes for common filters.
2. **Eloquent model** with `$fillable`, `casts()`, relationships (typed return), and any scopes (`visibleTo`, `root`).
3. **Form Request** under `Http/Requests/` — `authorize()` returns false unless permission slug is held *and* the parent entity is visible to the user.
4. **Service method** under `Services/` — wrap mutations in `DB::transaction`, call `Activity::log` and `NotificationService::push` from inside.
5. **Controller action** — thin, accepts the FormRequest, delegates to the service, returns `back()` or `redirect()->route()` with a flash `status`.
6. **Route registration** — `EnsurePermission` middleware attached, named consistently (`{module}.{resource}.{action}`).
7. **Permission slug** added to `PermissionRegistry` and seeder rerun.
8. **Frontend**: extend the relevant page component or add a new one under `resources/js/pages/`. Use `useForm().transform(...)` for client-side input shaping. Always `preserveScroll: true` on inline mutations.
9. **Pint + ESLint + build** — run `vendor/bin/pint`, `npm run lint`, `npm run build` before considering the slice done.

---

## 6. Out-of-scope for this audit

- AI summaries, transcription, voice recording — defer to Phase 5.
- Native mobile apps — Inertia covers responsive web; native mobile is a separate product decision.
- Marketplace / third-party app store — intentionally not in scope.
- Confluence-like wiki / knowledge base — Fellow has a notes feature but the bigger Confluence-class product is a separate build.

---

*End of document.*
