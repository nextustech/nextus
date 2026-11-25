# Sprint 0 Spike — OQ-022 Tenancy Layer (and OQ-026 Topology)

| Field | Value |
|---|---|
| Document | oq-022-tenancy-spike.md |
| Version | 1.0 |
| Status | Spike report — recommendations ratified 2026-11-20 |
| Date | 2026-11-20 |
| Question | Purpose-built tenancy layer vs `stancl/tenancy` for Phase 1 task T1.5 |
| Evidence base | `docs/prd/04-multi-tenancy-architecture.md` §2.4, §4–§6, §12; `20-development-roadmap.md` §4.1(c); repo inspection on 2026-11-20 |
| Scope note | This is a design/evidence spike based on repository and PRD inspection, not a 3-day coding experiment. No application code was changed. |

## 1. Verified baseline

- Laravel `^12.0` / PHP `^8.2`; `laravel/ui ^4.6`, `spatie/laravel-permission ^6.24` installed. No tenancy package, no Sanctum, no Horizon/Reverb.
- `app/Models/User.php`: no `tenant_id`, no `BelongsToTenant`, spatie `HasRoles` present but team scoping not configured.
- `app/Providers/AppServiceProvider.php`: only `Schema::defaultStringLength(125)` — no tenant context binding.
- `config/permission.php`: stock package config (teams feature must be enabled in T1.3).
- Migrations: `users`, `cache`, `jobs`, spatie permission tables only. No `tenants`, `tenant_domains`, `tenant_settings`, `audit_logs`.
- `routes/web.php`: `Auth::routes()` + Blade `/home`; no host-based resolution layer.
- PRD position: `04-multi-tenancy-architecture.md` §2.4 already rejects strict domain tenancy as the default; A-04 fixes shared-schema row isolation; OQ-022 was `Open` at spike time and is now `Closed` in `22-open-questions-and-assumptions.md` §5.

## 2. Decision criteria

| # | Criterion (source) | Requirement |
|---|---|---|
| C1 | Cross-tenant platform staff (04 §2.4.1, §4.6) | Finance/Support/Super Admin span many tenants per screen; impersonation must be explicit, reason-captured, fail-closed and audited |
| C2 | Tenant cardinality (04 §2.4.2, A-20) | Sub-resellers are full tenants at unlimited depth; provisioning is rows, not infrastructure |
| C3 | Resolution precedence (04 §4.2/§4.5) | Sanctum token claim > host; browser session never inherits token tenancy; API never silently falls back to host |
| C4 | Isolation semantics (04 §3, §6.1) | Mixed global/tenant/shared-read tables; `BelongsToTenant` on tenant-owned only; no-context must see **nothing** |
| C5 | Queue/event context (04 §5.3/§5.4) | `tenant_id` explicit in payloads; `tenant_gone` fail; suspended-tenant job parking; synchronous vs queued listener behavior |
| C6 | Audit and bypass policy (04 §6.2, §4.6) | Any global-scope removal goes through one audited helper; raw `DB::table` on tenant tables blocked in review |
| C7 | Monolith constraint (A-17, 04 §2.4.3) | No extra services; tenant config lives in our own `tenants` / `tenant_settings` tables |
| C8 | Cost of reversal (OQ-022 entry) | Switching later changes domain-resolution strategy, migration assumptions and cross-tenant access model |

## 3. Option analysis

| Option | C1 | C2 | C3 | C4 | C5 | C6 | C7 | C8 | Notes |
|---|---|---|---|---|---|---|---|---|---|
| (a) Purpose-built layer (middleware + global scopes + context container) | Strong | Strong | Strong — explicit precedence pipeline | Strong — trait allow/deny list per §3 | Strong — custom queue middleware | Strong — single bypass helper we own | Strong — ~framework code only | Moderate — ours to change | Matches 04 §2.4 and A-04; no package lock-in |
| (b) `stancl/tenancy` shared-DB mode + custom cross-tenant glue | Weak — one-tenant-per-request assumption, glue needed | Moderate — provisions tenant infra concepts | Weak — domain-first pipeline conflicts with token-first rule | Moderate — package scopes must be reconciled with our mixed-table classification | Moderate — must wrap its pipeline | Weak — two bypass mechanisms to audit | Weak — dependency + config surface | Weak — glue grows over time | Rejected as default in 04 §2.4 |
| (c) Hybrid (stancl for resolution only) | Moderate | Moderate | Weak — still domain-first at resolution | Moderate | Moderate | Weak | Moderate | Weak | Lowest benefit: resolution is the part we most need to control |

**Recommendation: option (a), purpose-built.** It is the only option that satisfies C1, C3 and C6 without custom glue, and it is the PRD's stated default. stancl/tenancy remains a fallback only if the isolation acceptance gates (§5 below) fail at scale — that contingency should be recorded, not adopted.

**Ratified 2026-11-20:** the Super Admin (architecture) approved option (a); OQ-022 is `Closed` in `22-open-questions-and-assumptions.md` §5.

## 4. Implementation shape for T1.5

1. **Resolution pipeline** — `ResolveTenantFromToken` (API routes) then `ResolveTenantFromHost` (browser), followed by `EnsureTenantReachable`, `BindTenantContext`, `ValidateSessionTenantBinding`, then spatie teams + route middleware, exactly in the order of 04 §4.2. Reserved hosts map to central; unknown hosts fall through with no tenant bound.
2. **`TenantContext`** — request/job-scoped instance (never static) with `get()`, `id()` (throws when absent), `isPlatform()`, `assertActive()`, `is()` per 04 §5.1. No-context means zero rows, never all rows.
3. **`BelongsToTenant` trait + `TenantScope`** — auto-assign `tenant_id` on create, throw on cross-tenant assignment, and add the scope only to tables classified `tenant-owned` in 04 §3. A reflection test asserts trait presence/absence per model class.
4. **Bypass policy** — one audited helper in `app/Services/Platform/`, `TENANCY-BYPASS:` comment markers, CI grep gate on `withoutGlobalScope` and `DB::table(` with a reviewed allowlist.
5. **Impersonation** — reason ≥10 chars, `impersonation.started` audit BEFORE context switch (fail-closed), banner in UI chrome, dual actor IDs on writes, forbidden financial/role/deletion actions while impersonating (04 §4.6).
6. **Propagation** — explicit `tenant_id` on every queued job and tenant-domain event; `TenantQueueMiddleware` revalidates tenant on processing; jobs tagged `tenant:{id}` for Horizon; cache keys `t:{id}:…` / `g:{…}`; storage `tenants/{id}/…`.
7. **Data model** — `tenants`, `tenant_domains`, `tenant_settings`; `tenant_domains` stays provider-agnostic so the ratified OQ-020 CNAME + HTTP-token + Cloudflare flow can be implemented in T4.2 without schema rework.

## 5. Spike exit tests (fold into Phase 1 exit criteria)

- [ ] ≥20 negative isolation tests: tenant-A user receives 403/404 on tenant-B resources across HTTP, API token and queue paths.
- [ ] Reflection test: every tenant-owned model has `BelongsToTenant`; every global/shared-read model does not (04 §6.1).
- [ ] CI grep gate: 0 unapproved `withoutGlobalScope` / `DB::table(` occurrences.
- [ ] No-context read on a tenant-owned model returns zero rows; `id()` throws `TenantContextMissingException`.
- [ ] Token + host mismatch returns 403 `tenant_mismatch`; browser session tenant mismatch invalidates the session.
- [ ] Impersonation with a short reason or a failed audit write is refused; every impersonated write carries both actor IDs.
- [ ] Suspended tenant: non-critical job parks after 24 releases; critical settlement job still executes.

## 6. OQ-026 topology recommendation (gates T1.8)

**Recommendation: (a) single managed Redis with logical separation** for MVP (≤100 tenants per G1):
- key prefixes `t:{tenant_id}:…`, `g:{domain}:…`, `tenancy:domain:{host}`, `queue:{queue}:…`;
- one Horizon dashboard with per-queue and `tenant:{id}` tag monitoring;
- Reverb pub/sub on the same instance with connection count tracked.

**Split trigger (record as an operational threshold, not a date):** move to separate instances when sustained queue lag exceeds the target in `18-non-functional-requirements.md`, or Reverb connections exceed documented sizing. This keeps OQ-026 from blocking T1.8 once ratified.

**Ratified 2026-11-20:** the Super Admin (platform engineering) approved option (a); OQ-026 is `Closed` in `22-open-questions-and-assumptions.md` §5.

## 7. Risks and rollback

- Reversal cost if isolation gates fail: adding stancl later means rewriting resolution and queue context. Mitigation: keep all tenant logic behind `TenantContext`, the trait and the bypass helper — no tenant ids read directly in controllers.
- Rollback point for Phase 1 is tagged `pre-phase1-rollback` on the current commit; T1.1 records its own breaking-change log before upgrading.
- If the reflection/grep gates cannot be made green in T1.5, stop before T1.6 and re-run this spike with a package-based prototype.

## 8. Next actions

1. OQ-022 (option a) and OQ-026 (option a) ratified 2026-11-20 in `22-open-questions-and-assumptions.md` §5; §2/§3 statuses are `Closed`.
2. Start T1.1 baseline validation (Laravel 12 / PHP 8.2+) — tenancy code must not merge before the baseline exits green.
3. T1.5 implements §4 above; §5 checklist becomes part of Phase 1 exit evidence.
