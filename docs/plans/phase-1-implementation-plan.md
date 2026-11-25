# Frenchies — Phase 1 Implementation Plan

| Field | Value |
|---|---|
| Document | phase-1-implementation-plan.md |
| Version | 0.1 (execution draft) |
| Status | Phase 1 complete — T1.1 through T1.9; T1.8 local worker runtime requires Redis/pcntl/posix-capable host. Phase 2 T2.1 registration and KYC queue complete; T2.2 tenant provisioning and lifecycle admin complete; T2.3 closure-table hierarchy engine complete; T2.4 sub-reseller creation and invitation flows complete |
| Date | 2026-11-20 |
| Source of truth | `docs/prd/20-development-roadmap.md` §4.1 (tasks T1.1–T1.9, 96 person-days), `docs/prd/04-multi-tenancy-architecture.md`, `docs/prd/02-user-roles-and-permissions.md`, `docs/prd/03-functional-requirements.md`, `docs/prd/23-master-prd.md` |
| Owner | Product & Architecture Team (execution: Backend / Frontend / QA / DevOps) |
| Note | This plan sequences PRD tasks; it does not redefine requirements or close OQ/A decisions. |

## 1. Verified current state

Checked against the repository on 2026-11-20:

- `composer.json`: Laravel `^12.0`, PHP `^8.2`, `laravel/ui ^4.6`, `spatie/laravel-permission ^6.24`, `barryvdh/laravel-dompdf ^3.1`. No Inertia, Sanctum, Horizon, Reverb or tenancy package.
- `routes/web.php`: Blade welcome/time views, `Auth::routes()` and `/home` via `App\Http\Controllers\HomeController` — the laravel/ui scaffolding the PRD requires removing.
- `database/migrations/`: `users`, `cache`, `jobs`, and the spatie permission tables only. No `tenants`, `tenant_domains`, `audit_logs`, `user_invitations` or `login_activities`.
- `database/seeders/RolePermissionSeeder.php`: creates 5 demo roles (`owner`, `admin`, `sales`, `developer`, `customer`) and 4 `project` permissions. **This is not the canonical 12-role / 136-permission catalogue** and must be replaced, not extended.
- `docs/prd/`: 24 PRD documents + index, all "Draft for Review" and untracked before this commit.

Target for implementation: Laravel 12 / PHP 8.2+, Inertia 2 + Vue 3 + TypeScript + Tailwind 4, Sanctum, team-scoped spatie permissions (`teams = tenant`), shared-schema tenancy, Redis/Horizon/Reverb, audit trail.

## 2. Gate 0 — decisions required before code

| Gate | Decision | Needed before | Proposed default in `22-open-questions...` §5.1 |
|---|---|---|---|
| G0.1 | **OQ-022** — purpose-built tenancy vs `stancl/tenancy` | Task T1.5 | **Ratified 2026-11-20:** purpose-built layer; spike evidence in `docs/spikes/oq-022-tenancy-spike.md` |
| G0.2 | **OQ-026** — Redis / Horizon / Reverb topology | Task T1.8 | **Ratified 2026-11-20:** single managed Redis with key prefixes; split only on measured queue-lag / connection thresholds |
| G0.3 | **OQ-020** — custom-domain SSL/DNS flow | T1.5 must stay provider-agnostic; full build is T4.2 (Phase 4) | CNAME + HTTP token + Cloudflare proxied edge SSL |
| G0.4 | **OQ-025** — email provider selection | Must start before Phase 2 T2.6 | Single provider, platform sending domain, per-tenant display name + Reply-To |
| G0.5 | 12 P0 OQs ratified per §5.1 | Phase 6 Gate G6 | All 12 ratified 2026-11-20; G5/G6/T4.2 unblocked |

Assumptions carried into the plan: owner decision (stay on Laravel 12; no framework-major upgrade in Phase 1), A-11 (replace Blade scaffolding entirely), A-12 (spatie version compatible with Laravel 12 with teams), A-13 (Cloudflare), A-04 (shared-schema row isolation).

G0.1 and G0.2 are ratified and recorded in `22-open-questions-and-assumptions.md` §5; T1.5 and T1.8 are unblocked. G0.3 keeps T1.5 provider-agnostic; G0.4 must start before Phase 2 T2.6.

## 3. Task sequence (roadmap T1.1–T1.9)

| Order | Task | Scope in this repo | BE / FE / QA / DO | Gate / notes |
|---|---|---|---:|---|
| 1 | **T1.1** Laravel 12 baseline validation, PHP 8.2+ runtime, CI + Docker baseline | Confirm the existing Laravel 12 dependency set and platform extensions; add CI coverage for PHP 8.2/8.3/8.4 without changing framework major; record baseline test results | 3 / 0 / 2 / 2 = 7 | No framework-major upgrade |
| 2 | **T1.2** Replace laravel/ui + Blade auth with Inertia 2 + Vue 3 + TS + Tailwind 4 | Remove `Auth::routes()` and `/home`; delete `app/Http/Controllers/Auth/*` and `HomeController` after route parity; install Vite plugin, Ziggy, app entry, base layouts | 3 / 9 / 2 / 2 = 16 | A-11; inventory Blade-dependent custom code first |
| 3 | **T1.3** spatie Laravel-12-compatible upgrade, `teams = tenant`, seed 12 canonical roles | Keep a Laravel-12-compatible spatie major (currently ^6.24), replace demo roles with all 12 role slugs from `02-user-roles-and-permissions.md` + 136 permission slugs; enable teams; add guard against duplicate seeding | 5 / 0 / 3 / 0 = 8 | A-12; roles are tenant-scoped from the first seed |
| 4 | **T1.4** Sanctum auth, login/logout/reset, `login_activities`, rate limiting | Session + API token auth; capture IP/device in `login_activities`; keep MFA requirement for `platform.super_admin` and `platform.finance_admin` in view (SEC control, may land with Phase 2) | 5 / 7 / 1 / 0 = 13 | Depends on T1.2 pages |
| 5 | **T1.5** Tenancy core | `TenantContext`, tenant middleware, global scope on every tenant-bearing model, subdomain + custom-host resolution (provider-agnostic per OQ-020), `tenants` / `tenant_domains` / `tenant_settings`, lifecycle states | 11 / 0 / 2 / 0 = 13 | **G0.1 required**; 0 unscoped queries at exit |
| 6 | **T1.6** Users & invitations | Tenant-scoped user CRUD, `user_invitations` create/accept/revoke; no account merging (A-14/A-24) | 4 / 6 / 1 / 0 = 11 | Depends on T1.3, T1.5 |
| 7 | **T1.7** Audit logging | `audit_logs` writer for auth + admin + lifecycle events, ≥10 action types with actor, tenant, IP, before/after payloads; UI-A-17 viewer | 3 / 5 / 1 / 0 = 9 | Append-only; indexes for 500 ms at 100k rows |
| 8 | **T1.8** Redis + Horizon + Reverb | Queue workers, broadcast channel authorization, health checks, logical key prefixes | 4 / 0 / 1 / 2 = 7 | **G0.2 required** |
| 9 | **T1.9** Design tokens + app shells | Tailwind token layer, interface A/B shells, nav, table/form primitives | 2 / 9 / 1 / 0 = 12 | Feeds all later screens |

**Total: 96 person-days** (BE 40 / FE 36 / QA 14 / DO 6), matching roadmap §4.1(e).

## 4. Suggested sprints (2-week cadence)

- **Sprint 0 (gate sprint):** G0.1 tenancy spike (3 days), G0.2 topology decision, ratify OQ-020/025 proposals, capture results in `22-open-questions-and-assumptions.md` §5, create rollback tag. No feature merge until G0.1 is closed.
- **Sprint 1:** T1.1 → T1.2. Exit: CI green on PHP 8.2+ / Laravel 12, zero `Auth::routes()` or Blade auth views.
- **Sprint 2:** T1.3 → T1.4. Exit: 12 roles seeded team-scoped, login/reset/invitation flows green through Inertia, `login_activities` populated.
- **Sprint 3:** T1.5 → T1.6. Exit: tenant resolution works for subdomain and a provider-agnostic custom-host hook; ≥20 negative isolation tests pass; invitation accept creates the correct tenant-scoped user.
- **Sprint 4:** T1.7 → T1.8 → T1.9. Exit: phase acceptance criteria in §5 all green; Phase 1 review and gate sign-off.

## 5. Phase exit checklist (roadmap §4.1(f))

1. [ ] PHP 8.2+ / Laravel 12, zero deprecation warnings; CI green (build, Pint, PHPUnit, Vite build) on every push.
2. [ ] No laravel/ui or Blade auth views; `Auth::routes()` removed from `routes/web.php`.
3. [ ] All 12 canonical roles seeded; tenant-A user gets 403/404 for tenant-B resources in ≥20 negative isolation tests.
4. [ ] Every tenant-bearing Eloquent model carries the global scope; code-review audit finds 0 unscoped queries in the tenancy module.
5. [ ] `audit_logs` records ≥10 action types with actor, tenant, IP, before/after; UI-A-17 filters in ≤500 ms at 100k rows.
6. [ ] Horizon shows queues processing; Reverb delivers an event to an authenticated session within 2 s in staging.
7. [ ] Open P0/P1 bugs: 0; open P2 bugs ≤ 5 with named owners.
8. [ ] Auth/session, RBAC-with-teams, tenancy-isolation and audit suites pass 100% of P0 cases; smoke E2E (login → dashboard → tenant switch) and upgrade regression green.

Local verification commands (run from project root):

```bash
composer install
php artisan migrate:fresh --seed
./vendor/bin/pint --test
php artisan test
npm install && npm run build
```

## 6. Risks and mitigations

From roadmap §4.1(g): `RISK-014` Laravel major-upgrade defects are deferred out of scope for Phase 1; `RISK-015` spatie teams migration on a Laravel-12-compatible version (seed rewrite in T1.3), `RISK-016` laravel/ui removal breaking routes (route parity check in T1.2), `RISK-001` cross-tenant leakage (negative tests + scope audit), `RISK-035` Reverb sizing (G0.2 thresholds), `RISK-020` key-person dependency (pair on tenancy core).

Additional execution risk: the existing `RolePermissionSeeder` uses `Permission::create()` / `Role::create()` without idempotency — rewriting it as upserts avoids a failed re-seed during QA resets.

## 7. Definition of done

Phase 1 is done when the exit checklist in §5 is fully green, the OQ decisions consumed by G0 are recorded in `22-open-questions-and-assumptions.md` §5 with §2 statuses updated per §1 rule 2, and the tenancy code-review checklist has been merged into `17-security-requirements.md`. Only then may Phase 2 (reseller management) start.
