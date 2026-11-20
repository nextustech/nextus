# Database Design — Schema Specification

| Field | Value |
|---|---|
| Document | 13-database-design.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Product & Architecture Team |
| Related documents | frenchies-prd-charter.md (§6 canonical inventory, §7 financial rules), 02-user-roles-and-permissions.md, 03-functional-requirements.md, 04-multi-tenancy-architecture.md, 05-reseller-hierarchy.md, 06-product-marketplace.md, 07-billing-and-financial-architecture.md, 08-affiliate-and-commission-system.md, 09-fulfillment-and-licensing.md, 10-white-label-cms.md, 11-ai-and-automation.md, 12-crm-and-support.md, 14-api-specification.md, 17-security-requirements.md, 18-non-functional-requirements.md |

## Table of Contents

1. [Purpose & Scope](#1-purpose--scope)
2. [Global Conventions](#2-global-conventions)
   - 2.1 [Engine, charset, collation](#21-engine-charset-collation)
   - 2.2 [Primary keys & public identifiers](#22-primary-keys--public-identifiers)
   - 2.3 [Money & currency](#23-money--currency)
   - 2.4 [Timestamps & time zones](#24-timestamps--time-zones)
   - 2.5 [Status columns & enum discipline](#25-status-columns--enum-discipline)
   - 2.6 [Soft deletes](#26-soft-deletes)
   - 2.7 [JSON columns](#27-json-columns)
   - 2.8 [Foreign keys & on-delete policy](#28-foreign-keys--on-delete-policy)
   - 2.9 [Index strategy](#29-index-strategy)
   - 2.10 [Polymorphic (morph) convention](#210-polymorphic-morph-convention)
   - 2.11 [Database users & least privilege](#211-database-users--least-privilege)
3. [Data Dictionary Summary](#3-data-dictionary-summary)
4. [Per-Table Specifications](#4-per-table-specifications)
   - 4.1 [Framework / identity](#41-framework--identity)
   - 4.2 [Tenancy](#42-tenancy)
   - 4.3 [Reseller hierarchy](#43-reseller-hierarchy)
   - 4.4 [Catalog & demos](#44-catalog--demos)
   - 4.5 [Customers & CRM](#45-customers--crm)
   - 4.6 [Orders & billing](#46-orders--billing)
   - 4.7 [Money, ledger & payouts](#47-money-ledger--payouts)
   - 4.8 [Affiliate](#48-affiliate)
   - 4.9 [Subscription](#49-subscription)
   - 4.10 [Fulfillment & licensing](#410-fulfillment--licensing)
   - 4.11 [Support, CSAT & knowledge base](#411-support-csat--knowledge-base)
   - 4.12 [AI](#412-ai)
   - 4.13 [Communication](#413-communication)
   - 4.14 [CMS & media](#414-cms--media)
   - 4.15 [Admin, API & integrations](#415-admin-api--integrations)
5. [Key Design Callouts](#5-key-design-callouts)
   - 5.1 [Hierarchy: closure table + resellers path/counter columns](#51-hierarchy-closure-table--resellers-pathcounter-columns)
   - 5.2 [Per-tenant gapless numbering](#52-per-tenant-gapless-numbering)
   - 5.3 [Webhook idempotency](#53-payment_webhook_events-idempotency)
   - 5.4 [Commission duplicate prevention](#54-commissions-duplicate-prevention)
   - 5.5 [Double-entry balance invariant & append-only ledger](#55-double-entry-balance-invariant--append-only-ledger)
   - 5.6 [Tenant-scoped query index strategy](#56-tenant-scoped-query-index-strategy)
   - 5.7 [Retention & partitioning for high-volume tables](#57-retention--partitioning-for-high-volume-tables)
   - 5.8 [Time zone & currency columns](#58-time-zone--currency-columns)
6. [ER Diagrams](#6-er-diagrams)
7. [Seed Data Requirements](#7-seed-data-requirements)
8. [Migration Strategy (documentation only)](#8-migration-strategy-documentation-only)
9. [Assumptions & Open Questions](#9-assumptions--open-questions)
10. [Appendix / References](#10-appendix--references)

---

## 1. Purpose & Scope

This document is the authoritative schema specification for the Frenchies platform. It fully
defines columns, types, defaults, keys, indexes, constraints, and relationships for:

- all **86 canonical tables** fixed by `frenchies-prd-charter.md` §6 (names are FINAL; no
  sibling document may invent alternatives for the same concept);
- the **deferred additions** demanded by sibling documents (`07` → `ledger_accounts`,
  `gateway_settlements`, `risk_blocklist`; `02` → `support_department_agents` + extended
  `permissions` columns; `12` → `csat_responses`, `kb_categories`, `kb_articles`,
  `kb_article_revisions`, `kb_article_votes`, `canned_responses`, `support_calendars`,
  `support_calendar_holidays`, `lead_lost_reasons`; `06` → demo-token store,
  order/lead demo-attribution columns; `10` → page-revision storage);
- a small number of **"Added by doc 13"** supporting tables that are genuinely required to
  implement requirements defined elsewhere (`api_request_logs`, `personal_access_tokens`,
  `demo_token_events`) — every such table is explicitly marked and classified per
  `04-multi-tenancy-architecture.md` §3 rule R1.

Stack constraints (charter §3): MySQL 8, **single shared database, shared schema**, row-level
tenant isolation via `tenant_id` columns + global scopes; Laravel 13 / PHP 8.4; Redis for
cache/queues (Horizon) with framework DB tables retained for `failed_jobs` and driver
fallback. **Documentation only — this document defines no migrations and no code.**

Isolation semantics (global / tenant-owned / shared-read per row) are authoritative in
`04-multi-tenancy-architecture.md` §3; the summary in §3 below repeats them for
convenience and adds classification for every doc-13 addition.

---

## 2. Global Conventions

### 2.1 Engine, charset, collation

- Storage engine: **InnoDB** (row-level locking, FK enforcement) for every table.
- Charset: `utf8mb4`, collation: **`utf8mb4_0900_ai_ci`** (MySQL 8 default) at database,
  table, and column level. Case-insensitive matching is required by design for slugs,
  email-per-tenant, codes and status strings (normalization to lowercase happens in the
  application before write).
- No `MyISAM`, no `MEMORY` tables, no stored procedures/triggers for business logic —
  business rules live in the application layer; the schema provides constraints only.

### 2.2 Primary keys & public identifiers

- Every table: **`id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`** except the
  composite-key framework/pivot tables documented below (`reseller_hierarchy`,
  `role_has_permissions`, `cache`, `sessions`, spatie pivots) which follow their natural
  or package-defined key shape.
- **`public_id`** — externally exposed entities carry a second, public identifier:
  `public_id CHAR(26) NOT NULL UNIQUE`, a ULID generated application-side
  (`Str::ulid()`, monotonic; never DB-generated, never sequential-guessable). Scope:
  `tenants`, `resellers`, `products`, `customers`, `orders`, `invoices`, `payments`,
  `refunds`, `payouts`, `licenses`, `support_tickets`, `subscriptions`, `quotations`,
  `leads`, `ai_conversations`.
- All API path resources identify rows by `public_id`; `id` never appears in URLs or
  webhook payloads. Internal joins use `id`.
- Declarative partitioned tables (§5.7) are the **only** exception to single-column PKs
  among domain tables: their PK becomes `(id, created_at)` because MySQL 8 requires every
  unique key to include the partition column.

### 2.3 Money & currency

- All monetary amounts: **`DECIMAL(15,2) NOT NULL`** — never `FLOAT`/`DOUBLE` (charter §7.11).
- Every amount-bearing table carries **`currency CHAR(3) NOT NULL DEFAULT 'INR'`**
  (ISO-4217). Multi-currency is P2/P3 (charter §1); the column exists from day one so no
  migration is needed later.
- Rates/percentages: `DECIMAL(5,2)` (0–100) for percentages, `DECIMAL(6,3)` for tax rates.
  Token-cost estimates use `DECIMAL(10,4)`.
- Rounding rule (doc 07 §3.1): half-up; residual ₹0.01 absorbed by the largest tax line —
  enforced in application code, not the DB.

### 2.4 Timestamps & time zines

- All timestamps stored **UTC** as `TIMESTAMP` where the value is an event time and
  `DATETIME` where the value is business-dated (issue dates, periods, expiry dates use
  `DATE`). Sub-second precision is not required: `DATETIME(0)`/`TIMESTAMP` (second
  precision) throughout.
- `created_at` and `updated_at` (`DATETIME NOT NULL`) exist on **all domain tables**.
  On append-only tables (§5.5, §5.7) `updated_at` is set at insert and never modified.
- Framework-owned tables retain their package schema (`cache` and `jobs` have no
  timestamp pair; spatie pivots follow the package migration) — documented exception.
- **Display:** every tenant has `tenants.timezone` (default `Asia/Kolkata`) and
  `tenants.locale` (default `en-IN`); the UI renders UTC values in tenant time zone.
  Business-dated columns (`issue_date`, `expires_at` as DATE, `follow_up_at` scheduling)
  are computed against tenant time zone before persistence (§5.8).

### 2.5 Status columns & enum discipline

- Stateful columns are `VARCHAR(32) NOT NULL` (never MySQL `ENUM` type — app-level backed
  PHP enums so values can be extended without DDL; `ENUM` shown below means the PHP-backed
  value set). **Every status/enum column's value set is listed inline in its column table**
  in §4 and in the consolidated lists of sibling documents.
- State transitions are validated server-side; illegal transitions raise and are audited.
- Boolean flags use `TINYINT(1) NOT NULL DEFAULT 0/1`.
- Canonical status columns and values (single source compiled here; per-table repetition
  in §4 marks the owning document):

| Column | Values | Owner doc |
|---|---|---|
| `tenants.status` | `pending`, `active`, `suspended`, `terminated` | 03 §6.1 |
| `tenant_domains.status` | `pending_dns`, `verifying`, `verified`, `provisioning_ssl`, `active`, `failed_dns_mismatch`, `failed_ssl`, `disabled` | 10 appendix (finalized here — see §9) |
| `users.status` | `active`, `suspended`, `disabled` | 13 |
| `user_invitations.status` | `pending`, `accepted`, `revoked`, `expired` | 02 §10 |
| `products.status` | `draft`, `pending_review`, `published`, `archived`, `rejected` | 06 App. B |
| `products.marketplace_status` | `unlisted`, `pending_review`, `approved`, `rejected` | 06 App. B |
| `tenant_products.status` | `active`, `suspended`, `revoked` | 06 App. B |
| `product_reviews.status` | `pending`, `approved`, `rejected`, `flagged` | 06 App. B |
| `product_demos.status` | `active`, `needs_reset`, `expired` | 06 App. B |
| demo token `state` | `active`, `expired`, `revoked` | 06 §10 |
| `orders.status` | `pending_payment`, `paid`, `processing`, `on_hold`, `fulfilled`, `completed`, `cancelled`, `partially_refunded`, `refunded`, `failed` | 03 §9.1 |
| `invoices.status` | `draft`, `issued`, `void`, `paid`, `partially_paid`, `overdue` | 07 §2 |
| `payments.status` | `pending`, `authorized`, `captured`, `partially_refunded`, `refunded`, `failed`, `cancelled`, `expired`, `manual_submitted`, `manual_approved`, `manual_rejected` | 07 §2 |
| `payment_webhook_events.processing` | `received`, `verified`, `applied`, `deferred`, `ignored_duplicate`, `rejected_signature`, `failed` | 07 §2 |
| `refunds.status` | `initiated`, `processing`, `completed`, `failed` | 07 §2 |
| `payouts.status` | `requested`, `approved`, `paid`, `rejected`, `cancelled` | 07 §2 |
| `commissions.status` | `pending`, `held`, `released`, `reversed`, `written_off`, `not_eligible_depth` | 08 §4 |
| `wallet_transactions.type` | `commission_pending`, `commission_release`, `commission_reversal`, `payout_hold`, `payout_paid`, `payout_rejected_release`, `adjustment`, `refund_clawback`, `ai_usage_charge`, `topup` | 07 §5.4 |
| wallet state buckets | `available`, `pending`, `commission` | charter §7.3 |
| `subscriptions.status` | `trialing`, `active`, `past_due`, `grace_period`, `suspended`, `cancelled`, `expired` | 03 §10.1 |
| `fulfillment_jobs.state` | `pending`, `in_progress`, `awaiting_customer`, `delivered`, `completed`, `failed`, `rejected`, `cancelled` | 09 §5 |
| `licenses.state` | `pending`, `active`, `expired`, `suspended`, `revoked` | 09 §10.2 |
| `license_activations.action` | `activate`, `deactivate`, `validate`, `heartbeat` | 09 §10.8 |
| `leads.status` | `new`, `contacted`, `qualified`, `proposal`, `negotiation`, `won`, `lost` | 12 §4.3 |
| `leads.source` | `contact_form`, `demo_request`, `manual`, `csv_import`, `api_webhook`, `affiliate`, `chatbot` | 12 §3 |
| `quotations.status` | `draft`, `sent`, `viewed`, `accepted`, `rejected`, `converted`, `expired` (+ `declined_after_acceptance`, P2) | 12 §6 |
| `support_tickets.status` | `open`, `pending_customer`, `resolved`, `closed`, `reopened` | 12 §12 |
| `support_tickets.priority` | `low`, `normal`, `high`, `urgent` | 12 §12 |
| `support_tickets.channel` | `portal`, `email`, `whatsapp`, `chatbot` | 12 §12 |
| `ticket_messages.visibility` | `public`, `internal` | 12 §22.7 |
| `ai_agents.status` | `draft`, `active`, `disabled` | 11 §6.1 |
| `ai_conversations.status` | `open`, `closed_by_limit`, `closed_by_handoff`, `purged` | 11 §11 |
| `ai_messages.role` | `user`, `assistant`, `tool`, `system` | 11 §11 |
| `api_keys.status` | `active`, `revoked` | 03 §14.1 |
| `webhook_deliveries.status` | `pending`, `success`, `failed`, `dead` | 03 §14.1 |
| `integration_connections.status` | `connected`, `degraded`, `disconnected`, `error` | 03 §14.1 (finalized over 11 §6.2 — see §9) |
| `website_pages.status` | `draft`, `published`, `archived` (system legal pages: `draft`/`published` only) | 10 appendix |
| `refunds`→coupons: `coupons.status` | `draft`, `active`, `paused`, `expired`, `exhausted` | 13 |
| `affiliates.status` | `applied`, `active`, `rejected`, `suspended`, `paused` | 08 §6 |

### 2.6 Soft deletes

Soft delete = `deleted_at TIMESTAMP NULL DEFAULT NULL` + `SoftDeletes` on the model
(`WHERE deleted_at IS NULL` added by the global scope). Policy:

- **Yes** — user-editable content entities whose deletion is a "trash" action and must be
  restorable: `users`, `products`, `product_variants`, `product_media`, `product_packages`,
  `package_items`, `product_addons`, `product_addon_links`, `product_demos`,
  `product_documents`, `customer_groups`, `quotations`, `coupons`, `support_departments`,
  `sla_policies` (tenant rows), `canned_responses`, `ai_agents`, `ai_knowledge_sources`,
  `website_themes`, `website_pages`, `website_menus`, `media_library`,
  `notification_templates`, `api_webhooks`, `integration_connections`.
- **No** — everything else, for these explicit reasons:
  - append-only / audit / financial & legal records (never deleted at app layer):
    `orders`, `order_items`, `invoices`, `invoice_items`, `payments`, `refunds`,
    `wallet_transactions`, `journal_entries`, `journal_lines`, `payouts`, `commissions`,
    `audit_logs`, `fulfillment_events`, `lead_activities`, `subscription_events`,
    `notification_logs`, `login_activities`, `affiliate_clicks`, `ai_messages`,
    `ai_usage_logs`, `license_activations`, `api_request_logs`, `webhook_deliveries`
    (rows are removed only by retention/partition jobs — §5.7);
  - lifecycle-managed entities that use status, not trash: `tenants`, `resellers`,
    `customers`, `affiliates`, `subscriptions`, `subscription_plans`, `licenses`,
    `support_tickets`, `leads`, `kb_articles`, `kb_categories`, `roles`, `permissions`,
    `api_keys`, `taxes`, `categories`;
  - infrastructure/framework tables (`sessions`, `cache`, `jobs`, `failed_jobs`, spatie
    pivots, `personal_access_tokens`) keep package schema;
  - rows purged during tenant termination are deleted by the explicit purge job
    (`04` §7.5) which bypasses soft deletes deliberately.

### 2.7 JSON columns

JSON columns are used only for genuinely flexible configuration; every one is documented
here with its expected shape (application validates against a schema registry before
write; unknown keys rejected — FR-TENANT-011):

| Table.column | Shape (documented schema) |
|---|---|
| `tenant_settings.value` | typed scalar/array per registered key (e.g., `invoice_sequence = {"prefix":"FRS","fy":"2026-27","next_seq":42,"format":"{PREFIX}-{FY}-{SEQ6}"}`) |
| `platform_settings.value` | scalar/array per registered key; secrets stored via encrypted cast |
| `products.fulfillment_config` | driver config: `{driver, template:{deliverables[], sla_days}, credentials:{…encrypted}, version_hash}` |
| `products.license_policy` | `{validity_days, max_activations, issuance:"pool"\|"on_demand", auto_renew, updates_included}` |
| `tenant_products` (none) | structured columns only |
| `ai_agents.tools` | allowlist array: `["catalog_lookup","lead_capture","kb_search","order_status","ticket_create"]` |
| `ai_agents.widget_settings` | `{widget_key, display_name, avatar, color, greeting, position, max_turns, fallback_message, availability_windows}` |
| `ai_messages.tool_calls` / `moderation` | array of `{name, args, result_digest, duration_ms}` / `{verdict, categories}` |
| `ai_knowledge_sources.config` | per-type fetch config (URL crawl depth, file parse options) |
| `orders`/`invoices` (none) | structured columns; snapshots in dedicated columns |
| `website_pages.sections` | ordered array: `[{type, settings:{…}}]` (§10 field contract; one document per page) |
| `website_pages.seo` | `{meta_title, meta_description, og_image, canonical, robots}` |
| `website_menus.structure` | nested array: `[{label, target_type:"page"\|"url"\|"category", target, children[]}]` |
| `api_keys.scopes` / `ip_allowlist` | scope strings array / CIDR strings array |
| `api_webhooks.events` | subscribed event keys array (catalogue per 03 FR-API-008) |
| `user_invitations.roles` / `department_grants` | requested role-name array / `[{department_id, permissions[]}]` |
| `notification_templates` (body column) | `TEXT` with `{{placeholder}}` tokens (not JSON) |
| `subscription_plans.features` / `quotas` | plan feature flags / `{max_users, max_products, max_domains, max_api_keys, max_sub_resellers, ai_token_cap}` |
| `licenses.meta` | `{domain_allowlist[], device_fingerprints[], notes}` |
| `ticket_messages.attachments` | array of `{disk_key, filename, mime, size, scan_status}` |
| `audit_logs.metadata` | `{before:{…}, after:{…}, reason, request_id}` (secrets redacted) |
| `payment_webhook_events.payload` / `headers` | raw gateway body (redacted of PAN/CVV) / selected headers |
| `gateway_settlements.raw_payload` | raw API/CSV row as received |

### 2.8 Foreign keys & on-delete policy

Default rule, applied unless a table's spec states otherwise:

| Policy | When used | Examples |
|---|---|---|
| **`RESTRICT`** (default) | financial/legal references, parent entities, anything whose loss breaks an invariant | `orders.customer_id→customers`, `order_items.product_id→products`, `payments.order_id→orders`, `commissions.order_item_id→order_items`, `journal_lines.journal_entry_id→journal_entries`, `tenant_id→tenants` on tenant-owned tables, both closure endpoints |
| **`CASCADE`** | pure dependent children with no independent meaning | `invoice_items→invoices`, `cart_items→carts`, `journal_lines→journal_entries` (child of entry), `package_items→product_packages`, `model_has_roles→roles`, `webhook_deliveries→api_webhooks`, `ticket_messages→support_tickets` (hard purge only) |
| **`SET NULL`** | optional references (who performed / which user / nullable link) | `created_by`, `assigned_to`, `updated_by`, `requested_by`, `approved_by`, `affiliates.user_id`, `refunds.credit_note_invoice_id` |

- `tenant_id` FKs are **`RESTRICT`** on tenant-owned tables: a tenant can only be removed
  by the explicit purge job after its rows are deleted — no accidental cascade of financial
  history (04 §7.5).
- Self-references (`resellers.parent_reseller_id`, `fulfillment_jobs.parent_job_id`,
  `invoices.original_invoice_id`, `journal_entries.reverses_entry_id`) are `RESTRICT`.
- Every FK column is indexed (InnoDB requires an index on the referencing column; we make
  it explicit and lead with `tenant_id` where both exist — §2.9).

### 2.9 Index strategy

- **Every tenant-owned table** carries at least one composite index **leading with
  `tenant_id`** — this is the mandatory pattern that makes the global scope cheap:
  `(tenant_id, <most-selective-filter>, <sort>)`.
- Column order inside a composite: equality columns first (`tenant_id`, `status`,
  `tenant_id, status`), then range/sort columns (`created_at`, `due_at`).
- Low-cardinality columns (`status`) are never indexed alone on small tables — only as a
  composite leading after `tenant_id`.
- Foreign keys always indexed; polymorphic pairs indexed as `(type, id)`.
- Business-unique keys use `UNIQUE` indexes (see per-table specs): tenant slug, domain
  host, per-tenant `order_number` / `invoice_number` / `quotation_number`, license `key`,
  affiliate `affiliate_code`, `coupons.code_key`, `users(email, tenant_id)`,
  `(tenant_id, key)` for settings, webhook `(gateway, gateway_event_id)`, commission
  5-column idempotency key (§5.4).
- Target: ≤ 6 secondary indexes on write-hot tables (`orders`, `audit_logs`,
  `notification_logs`, `ai_messages`, `affiliate_clicks`) — every index costs on ingest.
- Expression indexes (`IFNULL(col, 0)`) are used where NULL must participate in a unique
  key (documented per table).
- Covering-index checks for the hottest lists are recorded in the per-table `Indexes`
  line (e.g., `orders (tenant_id, status, created_at)` serves the default admin list).

### 2.10 Polymorphic (morph) convention

- Shape: **`<name>_type VARCHAR(32) NOT NULL` + `<name>_id BIGINT UNSIGNED NOT NULL`**
  (nullable only where the reference itself is optional). Both columns always indexed as
  `(<name>_type, <name>_id)`.
- `<name>_type` stores the **morph-map key**, never a PHP FQCN
  (`Model::morphMap([...])`), so table/class renames never corrupt stored data. Canonical
  morph map (single registry in the application):

| Map key | Target | Used by |
|---|---|---|
| `tenant` | `Tenant` | `wallets.owner_type`, `ledger_accounts.scope_id` companion |
| `affiliate` | `Affiliate` | `wallets.owner_type`, `commissions.beneficiary_type` |
| `customer` / `user` / `guest` | `Customer`, `User`, — | `orders.owner_type`, `carts.owner_type`, `support_tickets.requester_type` |
| `order`, `payment`, `refund`, `payout`, `invoice`, `commission` | billing entities | `journal_entries.reference_type`, `wallet_transactions.reference_type` |
| `user`, `system`, `job` | actor kinds | `audit_logs.actor_type` |
| any subject entity | polymorphic subject | `audit_logs.subject_type` (`product`, `tenant`, `lead`, `ticket`, …) |
| `system`, `driver`, `user`, `customer` | fulfillment actors | `fulfillment_events.actor_type` |
| `user`, `customer`, `system`, `ai` | message authors | `ticket_messages.author_type` |
| `user`, `customer` | recipients | `notification_logs.recipient_type` |
| `user`, `system` | lead authors | `lead_activities.author_type` |

- Concrete morph columns in the schema (checked against sibling requirements):
  **orders `owner_type/owner_id`** (who placed/owns the order for access scoping;
  commercial counterparty stays `customer_id`), **`support_tickets.requester_type/
  requester_id`** (`customer|user|affiliate` per 12 §10), **`commissions.beneficiary_type/
  beneficiary_id`** (`tenant|affiliate` per 08 §4), **`audit_logs.subject_type/subject_id`
  and `actor_type/actor_id`**, **`fulfillment_events.actor_type/actor_id`** (the
  fulfillment actor/"owner" of each transition), plus `wallets.owner_*`,
  `journal_entries.reference_*`, `wallet_transactions.reference_*`, `carts.owner_*`,
  `ticket_messages.author_*`, `notification_logs.recipient_*`,
  `lead_activities.author_*`, `personal_access_tokens.tokenable_*` (package-defined).

### 2.11 Database users & least privilege

- **`app_rw`** (Laravel runtime): `SELECT, INSERT, UPDATE, DELETE` + `ALTER, CREATE,
  INDEX` only during migrations (separate `app_migrate` grant revoked after deploy).
  **No `DROP`, no `GRANT OPTION`.**
- **`app_ledger`** (posting path for `07` §5.3): `INSERT, SELECT` on `journal_entries`
  and `journal_lines` **only — no UPDATE/DELETE grants** on those tables, ever. Same
  insert-only treatment for `fulfillment_events` (09 §14.2).
- **`app_readonly`** (reporting/exports): `SELECT` only.
- Nightly integrity jobs (ledger balance, closure reconciliation, sequence anomaly check)
  run under `app_readonly` + a narrow stored maintenance role; retention/partition
  maintenance uses `app_ops` (`ALTER TABLE ... DROP PARTITION` only).
- Cross-check: `17-security-requirements.md` owns the security review of these grants;
  this document fixes which tables must be insert-only: `journal_entries`,
  `journal_lines`, `fulfillment_events`.

---

## 3. Data Dictionary Summary

One row per table. **Classification** follows `04-multi-tenancy-architecture.md` §3:
`tenant-owned` = `tenant_id` isolation + `BelongsToTenant` scope; `tenant + central` =
mixed, always queried with explicit `tenant_id` (incl. central rows); `central/global` =
platform-managed, no tenant filter; `shared-read` = tenants read, never mutate.
**Volume tiers:** `S` < 100k rows/3y · `M` < 10M · `L` < 500M · `XL` ≥ 500M or unbounded.

### 3.1 Canonical tables (charter §6 — 86)

| # | Table | Purpose | Classification | Soft delete | Vol |
|---|---|---|---|---|---|
| 1 | `users` | Tenant-scoped login accounts (staff, customers, affiliates, platform) | tenant-owned | yes | M |
| 2 | `sessions` | HTTP session rows (DB driver fallback; Redis in production) | tenant-owned | no | M |
| 3 | `login_activities` | Login attempt audit per tenant | tenant-owned | no | L |
| 4 | `user_invitations` | Pending invitations with roles + department grants | tenant-owned | no | S |
| 5 | `roles` | spatie team-scoped roles (`teams_key = tenant_id`) | tenant-owned (team) | no | M |
| 6 | `permissions` | Platform permission catalogue (136 slugs) + doc-13 columns | global / shared-read | no | S |
| 7 | `model_has_roles` | User ↔ role assignments bound to a tenant team | tenant-owned (team) | no | M |
| 8 | `model_has_permissions` | Direct permission grants bound to a tenant team | tenant-owned (team) | no | M |
| 9 | `role_has_permissions` | Role → permission grants (via role's team) | tenant-owned (via role) | no | M |
| 10 | `cache` | Database cache driver rows (Redis in production) | global (infra) | no | S |
| 11 | `jobs` | Queue transport rows (Redis/Horizon in production) | global (infra) | no | S |
| 12 | `failed_jobs` | Failed jobs; payload retains tenant attribution | global (infra) | no | M |
| 13 | `tenants` | Business identities: central platform + reseller tenants | global | no | M |
| 14 | `tenant_domains` | Subdomain/custom-domain registry + verification/SSL state | global | no | M |
| 15 | `tenant_settings` | Typed per-tenant settings incl. invoice/order sequences | tenant-owned | no | L |
| 16 | `resellers` | Reseller business profile: parent link, path, counters | tenant-owned | no | M |
| 17 | `reseller_hierarchy` | Closure table: every ancestor → descendant pair + depth | global structure | no | M |
| 18 | `categories` | Global product taxonomy (15 seeded categories) | shared-read | no | S |
| 19 | `products` | Catalog entries: ownership, type, fulfillment + license config | central/global (ownership) | yes | L |
| 20 | `product_variants` | Product variants (options, price scope) | global (via product) | yes | M |
| 21 | `product_media` | Ordered product/demo images & videos (S3 keys) | global (via product) | yes | M |
| 22 | `product_packages` | Bundle definitions | global (via product) | yes | S |
| 23 | `package_items` | Bundle component lines (bundle depth = 1) | global (via product) | yes | S |
| 24 | `product_addons` | Standalone add-on definitions | global (via product) | yes | S |
| 25 | `product_addon_links` | Product ↔ add-on attachment rows | global (via product) | yes | S |
| 26 | `product_pricing` | Effective-dated base prices + floor (product/variant/addon) | global (base) | no | M |
| 27 | `tenant_products` | Per-tenant sell grants + price/commission overrides | tenant-owned | no | L |
| 28 | `product_demos` | Demo instances (live URL / video / screenshots) | global (via product) | yes | M |
| 29 | `product_versions` | Immutable major.minor releases + changelog | global (via product) | no | M |
| 30 | `product_reviews` | Moderated verified-purchaser reviews | tenant-owned (author) + shared-read | no | M |
| 31 | `product_documents` | Product attachments / manuals | global (via product) | yes | S |
| 32 | `customers` | End-buyer profiles (GST, portal user link) | tenant-owned | no | L |
| 33 | `customer_groups` | Named customer segments | tenant-owned | yes | S |
| 34 | `customer_group_members` | Group membership rows | tenant-owned | no | M |
| 35 | `leads` | CRM prospects: pipeline status, assignment, demo attribution | tenant-owned | no | L |
| 36 | `lead_activities` | Append-only lead timeline | tenant-owned | no | L |
| 37 | `quotations` | Quotes: secure link, status machine, expiry sweep | tenant-owned | yes | M |
| 38 | `quotation_items` | Quote lines with price/MSP/tax snapshots | tenant-owned | no | M |
| 39 | `carts` | Guest + authenticated carts per storefront | tenant-owned | no | M |
| 40 | `cart_items` | Cart lines with price snapshots | tenant-owned | no | M |
| 41 | `coupons` | Discount codes: platform / tenant / affiliate scope | tenant-owned | yes | M |
| 42 | `orders` | Sales orders (selling reseller = `tenant_id`) | tenant-owned | no | L |
| 43 | `order_items` | Order lines: version pinning, tax + floor snapshots | tenant-owned | no | L |
| 44 | `taxes` | Scoped GST rate definitions (global/product/category/tenant) | shared-read | no | M |
| 45 | `invoices` | GST invoices, credit notes, receipts + per-tenant sequence | tenant-owned + central | no | L |
| 46 | `invoice_items` | Invoice lines with CGST/SGST/IGST split | tenant-owned + central | no | L |
| 47 | `payments` | Gateway/manual payment attempts (central merchant accounts) | tenant-owned (gateway central) | no | L |
| 48 | `payment_webhook_events` | Inbound gateway webhook idempotency ledger | central/global | no | L |
| 49 | `refunds` | Refund attempts + clawback/credit-note linkage | tenant-owned | no | M |
| 50 | `wallets` | Owner wallets (tenant/affiliate/platform) + payout destination | tenant-owned | no | M |
| 51 | `wallet_transactions` | Append-only wallet subledger projection | tenant-owned | no | L |
| 52 | `journal_entries` | Double-entry postings (append-only, authoritative) | tenant-owned + central | no | L |
| 53 | `journal_lines` | Balanced debit/credit lines (append-only) | tenant-owned + central | no | L |
| 54 | `payouts` | Payout requests, Finance Admin approval, ledger posting link | tenant-owned | no | M |
| 55 | `commissions` | Per-line beneficiary commissions (idempotent accrual) | tenant-owned (beneficiary) | no | L |
| 56 | `affiliates` | Affiliate program members (code, status, terms) | tenant-owned | no | M |
| 57 | `affiliate_links` | Short referral links with campaign labels | tenant-owned | no | M |
| 58 | `affiliate_clicks` | Click/attribution event log (30-day cookie source) | tenant-owned | no | XL |
| 59 | `subscription_plans` | Reseller plans (platform) + product plans (tenant), versioned | shared-read (platform) + tenant-owned (product) | no | M |
| 60 | `subscriptions` | Subscriber plan instances + lifecycle state machine | tenant-owned | no | L |
| 61 | `subscription_events` | Append-only subscription lifecycle log | tenant-owned | no | L |
| 62 | `fulfillment_jobs` | Provisioning/manual jobs: SLA, approvals, disputes, retries | tenant-owned | no | L |
| 63 | `fulfillment_events` | Append-only job timeline | tenant-owned | no | L |
| 64 | `licenses` | License keys & states (globally unique key) | tenant-owned | no | M |
| 65 | `license_activations` | activate/deactivate/validate/heartbeat log | tenant-owned | no | L |
| 66 | `sla_policies` | First-response/resolution targets: defaults + overrides | shared-read defaults + tenant-owned overrides | yes | S |
| 67 | `support_departments` | Per-tenant queues + platform default mapping | tenant-owned + central | yes | S |
| 68 | `support_tickets` | Support threads: requester morph, SLA, escalation links | tenant-owned | no | M |
| 69 | `ticket_messages` | Public/internal replies with attachments | tenant-owned | no | L |
| 70 | `ai_agents` | Chatbot/assistant configuration + widget settings | tenant-owned | yes | S |
| 71 | `ai_conversations` | Conversation headers (handoff, lead, ticket links) | tenant-owned | no | M |
| 72 | `ai_messages` | Messages + tool traces + token counts | tenant-owned | no | XL |
| 73 | `ai_usage_logs` | Per-invocation token/cost metering | tenant-owned | no | L |
| 74 | `ai_knowledge_sources` | URL/file/text sources for retrieval | tenant-owned | yes | S |
| 75 | `notification_templates` | Per-tenant cloned message templates | tenant-owned + central | yes | M |
| 76 | `notification_logs` | Outbound message log (email/in-app/WhatsApp) | tenant-owned | no | XL |
| 77 | `website_themes` | Theme tokens; system themes are shared-read | tenant-owned + central (system) | yes | M |
| 78 | `website_pages` | Storefront pages: type, sections JSON, SEO, workflow | tenant-owned | yes | M |
| 79 | `website_menus` | Menu structures (header/footer/mobile) | tenant-owned | yes | S |
| 80 | `media_library` | Tenant-prefixed object storage index | tenant-owned | yes | M |
| 81 | `platform_settings` | Global platform configuration (secret-aware) | central/global | no | S |
| 82 | `audit_logs` | Append-only actor/subject audit trail | central/global (tenant-tagged) | no | XL |
| 83 | `api_keys` | Tenant API keys: scopes, rotation, revocation | tenant-owned | no | M |
| 84 | `api_webhooks` | Outbound event subscriptions + signing secret | tenant-owned | yes | M |
| 85 | `webhook_deliveries` | Delivery attempts, retries, dead-letter state | tenant-owned | no | L |
| 86 | `integration_connections` | Gateway/provider/channel credential references | tenant-owned + central | yes | M |

### 3.2 Additions (deferred by siblings + "Added by doc 13" — 18)

| # | Table | Purpose | Source / reason | Classification | Soft delete | Vol |
|---|---|---|---|---|---|---|
| A1 | `ledger_accounts` | Chart-of-accounts-light incl. per-wallet-state instances | 07 §5.1 (deferred) | central/global + tenant/affiliate instances | no | M |
| A2 | `gateway_settlements` | Daily gateway settlement rows for reconciliation | 07 (deferred) | central/global | no | M |
| A3 | `risk_blocklist` | Payment fraud blocklist entries | 07 (deferred) | global + tenant-scoped rows | no | S |
| A4 | `support_department_agents` | Support Agent ↔ department membership pivot | 02 (deferred) | tenant-owned | no | M |
| A5 | `csat_responses` | One CSAT response per resolved ticket | 12 (deferred) | tenant-owned | no | M |
| A6 | `kb_categories` | KB category tree (platform + tenant scopes) | 12 (deferred) | tenant-owned + shared-read | no | S |
| A7 | `kb_articles` | KB articles with visibility scopes | 12 (deferred) | tenant-owned + shared-read | no | M |
| A8 | `kb_article_revisions` | KB publish snapshots (restore) | 12 (deferred) | tenant-owned | no | M |
| A9 | `kb_article_votes` | Anonymous helpfulness votes | 12 (deferred) | tenant-owned | no | M |
| A10 | `canned_responses` | Agent reply templates (P2) | 12 (deferred) | tenant-owned | yes | S |
| A11 | `support_calendars` | Business-hours calendars (P2) | 12 (deferred) | tenant-owned | yes | S |
| A12 | `support_calendar_holidays` | Holiday rows of a calendar (P2) | 12 (deferred "…+ holidays") | tenant-owned | no | S |
| A13 | `lead_lost_reasons` | Tenant-defined lost-reason taxonomy | 12 (deferred; table form chosen — §9) | tenant-owned | no | S |
| A14 | `demo_access_tokens` | Unique per-visitor demo links (`/d/{token}`) + attribution | 06 (deferred demo-token store) | tenant-owned | no | L |
| A15 | `demo_token_events` | Per-token view/heartbeat/CTA event stream | 06 FR-DEMO-008 (tracking needs a store) | tenant-owned | no | XL |
| A16 | `website_page_revisions` | Last-10 page snapshots + non-destructive restore | 10 (deferred) | tenant-owned | no | M |
| A17 | `api_request_logs` | API request metrics log, 90-day retention | 03 FR-API-006 (no canonical table existed) | tenant-owned | no | XL |
| A18 | `personal_access_tokens` | Sanctum tokens with team (tenant) context | 02 §9.10 (package table) | tenant-owned | no | M |

**Totals: 104 tables = 86 canonical + 18 additions.** Not added, deliberately
(decisions recorded in §9): persistent reseller rollup table (Redis-first per 05 §10),
`notifications` in-app table (in-app rows live in `notification_logs`), `credit_notes`
table (realized as `invoices.type = 'credit_note'`), `invoice_sequence` table (lives in
`tenant_settings` per 07 §2).

---

## 4. Per-Table Specifications

Legend repeated per table: *Class* = isolation class (§3), *Vol* = volume tier.
Column tables list PK/FK targets **with on-delete** and inline enum values; the trailing
line lists indexes (`IDX`), unique business keys (`UNQ`), Eloquent-style relations
(`Rel`), and soft-delete state. `TS` = `created_at`/`updated_at` pair.

### 4.1 Framework / identity

#### 4.1.1 `users`
*Purpose:* tenant-scoped accounts (staff, customers, affiliates, platform staff). · *Class:* tenant-owned · *Soft delete:* yes · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT; every user belongs to exactly one tenant (charter §4) |
| name | VARCHAR(100) | no | — | display name |
| email | VARCHAR(255) | no | — | normalized lowercase before write |
| email_verified_at | DATETIME | yes | NULL | |
| password | VARCHAR(255) | no | — | hashed; portal customers may set later (FR-ORDER-007) |
| status | VARCHAR(32) | no | 'active' | Enum: active, suspended, disabled |
| phone | VARCHAR(32) | yes | NULL | |
| locale | CHAR(5) | no | 'en-IN' | |
| timezone | VARCHAR(64) | no | 'Asia/Kolkata' | display fallback before tenant default applies |
| last_login_at | DATETIME | yes | NULL | |
| remember_token | VARCHAR(100) | yes | NULL | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(tenant_id, status), IDX(email). **Unique:** UNQ(email, tenant_id) — one
account per human per tenant (02 §10.5); login shows a tenant chooser on multi-match.
**Relations:** `belongsTo(tenant)`; `hasMany(orders owner, support_tickets requester,
leads assigned)`. **Soft delete:** yes.

#### 4.1.2 `sessions`
*Purpose:* DB-driver session rows (tagged with tenant; Redis session store is the
production driver — charter §3). · *Class:* tenant-owned (payload carries tenant) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | CHAR(41) | no | — | PK (Laravel session string id) |
| user_id | BIGINT UNSIGNED | yes | NULL | FK users(id) CASCADE |
| tenant_id | BIGINT UNSIGNED | yes | NULL | FK tenants(id) CASCADE; tenant binding per 04 §3.1 |
| ip_address | VARCHAR(45) | yes | NULL | |
| user_agent | TEXT | yes | NULL | |
| payload | TEXT | no | — | serialized; contains tenant context |
| last_activity | INT UNSIGNED | no | — | unix seconds; IDX(last_activity) for sweeps |

**Indexes:** IDX(user_id), IDX(last_activity). **Unique:** none. **Relations:**
`belongsTo(user, tenant)`. **Soft delete:** no (framework schema).

#### 4.1.3 `login_activities`
*Purpose:* append-only login attempt audit per tenant. · *Class:* tenant-owned · *Soft delete:* no · *Vol:* L (partition candidate §5.7)

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| user_id | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL (unknown email ⇒ NULL) |
| email | VARCHAR(255) | no | — | attempted identifier |
| success | TINYINT(1) | no | 0 | |
| failure_reason | VARCHAR(64) | yes | NULL | Enum: invalid_credentials, unknown_email, suspended, tenant_suspended, expired_session, rate_limited |
| ip_address | VARCHAR(45) | yes | NULL | |
| user_agent | VARCHAR(512) | yes | NULL | |
| created_at / updated_at | DATETIME | no | — | TS; append-only (updated_at frozen) |

**Indexes:** IDX(tenant_id, created_at), IDX(user_id, created_at), IDX(email, created_at).
**Unique:** none (repeat attempts are data). **Relations:** `belongsTo(tenant, user)`.
**Soft delete:** no.

#### 4.1.4 `user_invitations`
*Purpose:* pending invitations carrying roles and optional department grants (02 §10). · *Class:* tenant-owned · *Soft delete:* no · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| email | VARCHAR(255) | no | — | matched case-insensitively at acceptance |
| roles | JSON | no | — | array of role names (e.g., `["reseller.custom.support"]`) |
| department_grants | JSON | yes | NULL | `[{department_id, permissions[]}]` (02 §10.1) |
| invited_by | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL |
| token_hash | CHAR(64) | no | — | SHA-256 of the mailed token; token never stored/logged (17) |
| status | VARCHAR(32) | no | 'pending' | Enum: pending, accepted, revoked, expired |
| expires_at | DATETIME | no | created_at + 168h | single-use; resend resets expiry once |
| accepted_at | DATETIME | yes | NULL | acceptance writes roles + dept grants atomically |
| revoked_at | DATETIME | yes | NULL | revocable until acceptance |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(tenant_id, status, expires_at), IDX(tenant_id, email).
**Unique:** UNQ(token_hash) (global). **Relations:** `belongsTo(tenant, invitedBy)`.
**Soft delete:** no (status-driven).

#### 4.1.5 `roles` (spatie + teams)
*Purpose:* team-scoped roles; `teams_key = tenant_id` (02 §9.2). · *Class:* tenant-owned (team) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| name | VARCHAR(191) | no | — | fixed 12 template names (02 §9.3) + `reseller.custom.<slug>` |
| tenant_id | BIGINT UNSIGNED | no | — | spatie teams key (config `teams_key = 'tenant_id'`); FK tenants(id) RESTRICT; missing team throws (02 §9.2) |
| guard_name | VARCHAR(255) | no | 'web' | one catalogue serves web + Sanctum (02 §9.3) |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(tenant_id). **Unique:** UNQ(name, tenant_id, guard_name).
**Relations:** `belongsTo(tenant)`, `belongsToMany(users, model_has_roles)` (pivot carries
team). **Soft delete:** no.

#### 4.1.6 `permissions`
*Purpose:* platform permission catalogue (136 slugs / 26 modules) with doc-13 extension
columns. · *Class:* global / shared-read · *Soft delete:* no · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| name | VARCHAR(191) | no | — | slug `<module>.<action>` (02 §3.1) |
| guard_name | VARCHAR(255) | no | 'web' | |
| module | VARCHAR(32) | no | — | **doc-13 extension (02 §A6)**: one of the 26 module keys, e.g. `orders` |
| reseller_assignable | TINYINT(1) | no | 0 | **doc-13 extension**: platform-flipped flag gating custom roles (02 §8.2.3) |
| description | VARCHAR(255) | yes | NULL | **doc-13 extension**: human text for role editor |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(module). **Unique:** UNQ(name, guard_name).
**Relations:** `belongsToMany(roles)`, `belongsToMany(users)`. **Soft delete:** no —
catalogue rows are renamed via versioned rename map, never deleted (02 §9.6).

#### 4.1.7 `model_has_roles`
*Purpose:* user ↔ role assignments bound to a tenant team. · *Class:* tenant-owned (team) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| model_type | VARCHAR(255) | no | — | morph key `user` (morph map, §2.10) |
| model_id | BIGINT UNSIGNED | no | — | users.id |
| role_id | BIGINT UNSIGNED | no | — | FK roles(id) CASCADE |
| tenant_id | BIGINT UNSIGNED | no | — | spatie teams key; FK tenants(id) CASCADE; assignment never crosses tenants (04 §3.1) |

**Indexes:** IDX(role_id), IDX(tenant_id), IDX(model_id). **Unique:** UNQ(model_type,
model_id, role_id, tenant_id). **Relations:** `belongsTo(role, tenant)`.
**Soft delete:** no (package pivot, no timestamps per package schema).

#### 4.1.8 `model_has_permissions`
*Purpose:* direct permission grants bound to a tenant team. · *Class:* tenant-owned (team) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| model_type | VARCHAR(255) | no | — | morph key `user` |
| model_id | BIGINT UNSIGNED | no | — | users.id |
| permission_id | BIGINT UNSIGNED | no | — | FK permissions(id) CASCADE |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) CASCADE (teams key) |

**Indexes:** IDX(permission_id), IDX(tenant_id), IDX(model_id).
**Unique:** UNQ(model_type, model_id, permission_id, tenant_id).
**Relations:** `belongsTo(permission, tenant)`. **Soft delete:** no.

#### 4.1.9 `role_has_permissions`
*Purpose:* role → permission grants (tenant scope via the parent role's team). · *Class:* tenant-owned (via role) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| permission_id | BIGINT UNSIGNED | no | — | part of PK; FK permissions(id) CASCADE |
| role_id | BIGINT UNSIGNED | no | — | part of PK; FK roles(id) CASCADE |

**Indexes:** IDX(role_id). **Unique:** PK(permission_id, role_id).
**Relations:** `belongsTo(role, permission)`. **Soft delete:** no (package pivot).

#### 4.1.10 `cache`
*Purpose:* database cache driver rows (production uses Redis; table retained for driver
fallback and tests). · *Class:* global (infra) · *Soft delete:* no · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| key | VARCHAR(255) | no | — | PK; tenant-namespaced keys per 04 §6.5 |
| value | MEDIUMTEXT | no | — | |
| expiration | INT | no | — | unix seconds; IDX(expiration) |

**Indexes:** IDX(expiration). **Unique:** PK(key). **Relations:** none.
**Soft delete:** no (framework schema).

#### 4.1.11 `jobs`
*Purpose:* database queue transport (production: Redis/Horizon — charter §3; table kept
for `database` driver parity). · *Class:* global (infra) · *Soft delete:* no · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| queue | VARCHAR(255) | no | — | IDX(queue) |
| payload | LONGTEXT | no | — | serialized; carries `tenant_id` (04 §5.3) |
| attempts | TINYINT UNSIGNED | no | 0 | |
| reserved_at | INT UNSIGNED | yes | NULL | unix seconds |
| available_at | INT UNSIGNED | no | — | unix seconds |
| created_at | INT UNSIGNED | no | — | unix seconds (framework schema) |

**Indexes:** IDX(queue, reserved_at). **Unique:** none. **Relations:** none.
**Soft delete:** no.

#### 4.1.12 `failed_jobs`
*Purpose:* failed job records retaining tenant attribution for purge/resume (04 §3.1). · *Class:* global (infra) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| uuid | VARCHAR(255) | no | — | UNQ(uuid) |
| connection / queue | TEXT | yes | NULL | |
| payload | LONGTEXT | no | — | JSON payload retains `tenant_id` |
| exception | LONGTEXT | no | — | |
| failed_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes:** IDX(failed_at). **Unique:** UNQ(uuid). **Relations:** none.
**Soft delete:** no.

---

### 4.2 Tenancy

#### 4.2.1 `tenants`
*Purpose:* business identities — exactly one platform tenant + one per reseller. · *Class:* global (registry) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| public_id | CHAR(26) | no | — | ULID; UNQ(public_id) |
| slug | VARCHAR(63) | no | — | lowercase `[a-z0-9-]`; **UNQ(slug)**; first label of `*.frenchies.com`; immutable after creation (FR-TENANT-019) |
| name | VARCHAR(191) | no | — | display name |
| legal_name | VARCHAR(191) | yes | NULL | invoice header (B2B) |
| type | VARCHAR(32) | no | 'reseller' | Enum: platform, reseller; DB CHECK: only one `platform` row (FR-TENANT-001) |
| status | VARCHAR(32) | no | 'pending' | Enum: pending, active, suspended, terminated (03 §6.1) |
| gstin | VARCHAR(15) | yes | NULL | seller GSTIN; mandatory before first `issued` invoice (07 §3.1) |
| currency | CHAR(3) | no | 'INR' | ISO-4217; default billing currency (§2.3) |
| timezone | VARCHAR(64) | no | 'Asia/Kolkata' | display/scheduling zone (§5.8) |
| locale | CHAR(5) | no | 'en-IN' | |
| logo_path | VARCHAR(512) | yes | NULL | S3 key under tenant prefix (panel + invoice PDF) |
| favicon_path | VARCHAR(512) | yes | NULL | |
| provisioned_at | DATETIME | yes | NULL | set by `ProvisionResellerTenant` |
| suspended_at / terminated_at | DATETIME | yes | NULL | lifecycle audit stamps |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(type, status). **Unique:** UNQ(slug), UNQ(public_id).
**Relations:** `hasOne(reseller)` (central tenant → root row, §4.3), `hasMany(users,
tenant_domains, tenant_settings, orders)`. **Soft delete:** no — lifecycle only; purge is
an explicit job (04 §7.5).

#### 4.2.2 `tenant_domains`
*Purpose:* subdomain + custom-domain registry with verification/SSL lifecycle. · *Class:* global (platform-managed) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| host | VARCHAR(255) | no | — | normalized lowercase; **UNQ(host)** (globally unique, FR-TENANT-019) |
| type | VARCHAR(16) | no | 'custom' | Enum: subdomain, custom |
| status | VARCHAR(32) | no | 'pending_dns' | Enum: pending_dns, verifying, verified, provisioning_ssl, active, failed_dns_mismatch, failed_ssl, disabled (10 appendix — finalized here, §9.1) |
| verification_token | VARCHAR(64) | yes | NULL | TXT/CNAME evidence token |
| verified_at | DATETIME | yes | NULL | |
| ssl_expires_at | DATE | yes | NULL | Cloudflare-managed cert; renewal watchdog |
| released_at | DATETIME | yes | NULL | released domains: 30-day cooldown before reuse (FR-TENANT-019) |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(tenant_id, type). **Unique:** UNQ(host) — serves the exact-host
resolution lookup; the resolver predicate is `status = 'active'` (04 §4.1 `is_verified`
maps to `status='active'` — §9.1). **Relations:** `belongsTo(tenant)`.
**Soft delete:** no (status-driven).

#### 4.2.3 `tenant_settings`
*Purpose:* typed key-value settings with schema-registry validation (FR-TENANT-011);
also holds sequence state (§5.2). · *Class:* tenant-owned · *Soft delete:* no · *Vol:* L

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| key | VARCHAR(128) | no | — | registered key, e.g. `invoice_sequence`, `order_sequence`, `invoice.format`, `payout.min_amount` overrides, `ai_retention_days`, custom 403/404/500 text (16 A8) |
| value | JSON | no | — | typed scalar/array (§2.7); secrets via encrypted cast |
| type | VARCHAR(16) | no | 'json' | Enum: string, int, bool, json, encrypted |
| updated_by | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(tenant_id, key) (from UNQ). **Unique:** UNQ(tenant_id, key).
**Relations:** `belongsTo(tenant, updatedBy)`. **Soft delete:** no — keys are upserted.
`invoice_sequence` rows are locked with `SELECT … FOR UPDATE` (§5.2).

---

### 4.3 Reseller hierarchy

#### 4.3.1 `resellers`
*Purpose:* business profile 1:1 with a reseller tenant + hierarchy adjacency mirror. · *Class:* tenant-owned · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| public_id | CHAR(26) | no | — | ULID; UNQ(public_id) |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT; **UNQ(tenant_id)** — one profile per tenant |
| type | VARCHAR(16) | no | 'standard' | Enum: standard, root — `root` is the single non-loginable system row bound to the central tenant (05 §A1; decided here) |
| parent_reseller_id | BIGINT UNSIGNED | yes | NULL | self FK resellers(id) RESTRICT; adjacency mirror (05 §3.3 I4); NULL only for `root` |
| name | VARCHAR(191) | no | — | business/brand name |
| legal_name | VARCHAR(191) | yes | NULL | |
| gstin | VARCHAR(15) | yes | NULL | reseller GSTIN for its own B2B documents |
| email / phone | VARCHAR(255) / VARCHAR(32) | yes | NULL | contact |
| path | VARCHAR(1024) | no | '/' | materialized ancestor path, slash-delimited reseller ids incl. self, e.g. `/1/12/45/` — updated atomically on reparent (05 §A1/A2; closure remains source of truth) |
| child_count | INT UNSIGNED | no | 0 | direct-children counter for plan-limit guard (05 §A3); incremented in the create/reparent transaction; weekly reconciliation guards drift |
| effective_status | VARCHAR(32) | no | 'active' | Enum: active, suspended, suspended_ancestor, terminated — DB-visible derivation of 05 §6.1; **cache remains authoritative** for the 60 s freshness target; maintained by the cascade job |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(parent_reseller_id), IDX(tenant_id) (from UNQ).
**Unique:** UNQ(tenant_id), UNQ(public_id).
**Relations:** `belongsTo(tenant)`, `selfbelongsTo(parent)`,
`belongsToMany(descendants, reseller_hierarchy)`.
**Soft delete:** no — deletion is the explicit purge flow that removes closure rows first
(05 §8).

#### 4.3.2 `reseller_hierarchy`
*Purpose:* closure table — one row per (ancestor, descendant) pair including self at
depth 0 (05 §3). · *Class:* global (cross-tenant structure) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| ancestor_reseller_id | BIGINT UNSIGNED | no | — | part of PK; FK resellers(id) RESTRICT (purge removes rows explicitly) |
| descendant_reseller_id | BIGINT UNSIGNED | no | — | part of PK; FK resellers(id) RESTRICT |
| depth | SMALLINT UNSIGNED | no | — | 0 = self row; CHECK(depth >= 0) |
| created_at / updated_at | DATETIME | no | — | TS; updated_at frozen (rows are rewritten on reparent, never edited) |

**Indexes:** PK(ancestor_reseller_id, descendant_reseller_id);
`reseller_hierarchy_descendant_idx` IDX(descendant_reseller_id) — "all ancestors of X";
`reseller_hierarchy_depth_idx` IDX(ancestor_reseller_id, depth) — children-only/level
queries (05 §3.4). **Unique:** PK. **Relations:** `belongsTo(ancestorReseller)`,
`belongsTo(descendantReseller)`. **Soft delete:** no.
Column naming: fully-qualified names adopted per 05 §A2 (charter §6 abbreviates — §9.5).

---

### 4.4 Catalog & demos

#### 4.4.1 `categories`
*Purpose:* global product taxonomy (15 seeded roots; slugs global). · *Class:* shared-read · *Soft delete:* no · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| name | VARCHAR(191) | no | — | |
| slug | VARCHAR(191) | no | — | **UNQ(slug)** — global across tenants (06 A1) |
| parent_id | BIGINT UNSIGNED | yes | NULL | self FK categories(id) RESTRICT; single seeded level, deeper nesting admin-created |
| description | VARCHAR(500) | yes | NULL | |
| position | INT | no | 0 | IDX(parent_id, position) |
| is_active | TINYINT(1) | no | 1 | |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(is_active, position). **Unique:** UNQ(slug).
**Relations:** `selfBelongsTo(parent)`, `hasMany(products)`. **Soft delete:** no
(deactivation only).

#### 4.4.2 `products`
*Purpose:* catalog entries with ownership, type binding, fulfillment + license
configuration. · *Class:* central/global (ownership via owner_tenant_id) · *Soft delete:* yes · *Vol:* L

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| public_id | CHAR(26) | no | — | ULID; UNQ(public_id) |
| owner_tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT; central OR reseller owner (06 §2) |
| category_id | BIGINT UNSIGNED | no | — | FK categories(id) RESTRICT |
| product_type | VARCHAR(32) | no | — | Enum: saas_subscription, license_software, digital_service, managed_service, ai_agent_deployment, bundle — immutable after first sale (06 FR-PRODUCT-001) |
| name | VARCHAR(191) | no | — | |
| slug | VARCHAR(191) | no | — | UNQ(owner_tenant_id, slug); storefront route `products/{slug}` (10 §5); on visibility collision with a granted product, own product wins (note) |
| short_description | VARCHAR(500) | yes | NULL | |
| description | MEDIUMTEXT | yes | NULL | sanitized rich text |
| status | VARCHAR(32) | no | 'draft' | Enum: draft, pending_review, published, archived, rejected (06 App. B) |
| marketplace_status | VARCHAR(32) | no | 'unlisted' | Enum: unlisted, pending_review, approved, rejected — reseller-owned products only (06 App. B) |
| price_type | VARCHAR(16) | no | 'fixed' | Enum: fixed, custom (custom ⇒ storefront refuses to quote — 06 FR-PRODUCT-012) |
| fulfillment_model | VARCHAR(32) | no | — | **09 §14.5**; Enum: subscription, license, manual, api, service, metered, composite (bound by product_type, 06 §3) |
| fulfillment_driver | VARCHAR(64) | yes | NULL | **09 §14.5**: driver key, e.g. `manual_default`, `license_key_driver`, `generic_api_driver` |
| fulfillment_config | JSON | yes | NULL | **09 §14.5**: driver config w/ encrypted secret fields (§2.7) |
| promised_delivery_days | SMALLINT UNSIGNED | no | 5 | **09 §14.5**; drives `fulfillment_jobs.promised_delivery_date` (§9 default) |
| max_revisions | SMALLINT UNSIGNED | no | 2 | **09 §14.5**; range 0–10 |
| license_policy | JSON | yes | NULL | **09 §14.5**: `{validity_days, max_activations, issuance: pool or on_demand, auto_renew}` (§2.7) |
| re_deliverable_on_renewal | TINYINT(1) | no | 1 | **09 §14.5** |
| subscription_plan_id | BIGINT UNSIGNED | yes | NULL | FK subscription_plans(id) SET NULL — saas_subscription binding (06 FR-PRODUCT-003) |
| active_version_id | BIGINT UNSIGNED | yes | NULL | FK product_versions(id) SET NULL — current pointer (06 §8); nullable circular FK, updated by publish action |
| rating_avg | DECIMAL(3,2) | no | 0.00 | cached aggregate of approved reviews |
| rating_count | INT | no | 0 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes (archive is `status=archived`; delete = trash) |

**Indexes:** IDX(owner_tenant_id, status, created_at), IDX(category_id, status),
IDX(marketplace_status, status), IDX(subscription_plan_id).
**Unique:** UNQ(owner_tenant_id, slug), UNQ(public_id).
**Relations:** `belongsTo(ownerTenant, category, activeVersion)`,
`hasMany(variants, media, pricing, demos, versions, reviews, documents)`,
`belongsToMany(sellingTenants, tenant_products)`. **Soft delete:** yes.

#### 4.4.3 `product_variants`
*Purpose:* sellable variants (option sets) with independent price/floor scope. · *Class:* global (via product) · *Soft delete:* yes · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| name | VARCHAR(191) | no | — | e.g. "5 seats" |
| sku | VARCHAR(64) | yes | NULL | UNQ(product_id, sku) |
| options | JSON | no | `{}` | `{"Color":"Red","Seats":"5"}` (§2.7) |
| position | SMALLINT | no | 0 | |
| is_active | TINYINT(1) | no | 1 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_id, position). **Unique:** UNQ(product_id, sku).
**Relations:** `belongsTo(product)`, `hasOne(product_pricing scope)`.
**Soft delete:** yes.

#### 4.4.4 `product_media`
*Purpose:* ordered product gallery (and demo screenshot galleries — 06 §10). · *Class:* global (via product) · *Soft delete:* yes · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| product_demo_id | BIGINT UNSIGNED | yes | NULL | FK product_demos(id) SET NULL — non-NULL ⇒ row belongs to that demo's screenshot gallery (06 §10); exclusive with plain gallery |
| product_variant_id | BIGINT UNSIGNED | yes | NULL | FK product_variants(id) SET NULL |
| type | VARCHAR(16) | no | 'image' | Enum: image, video |
| path | VARCHAR(512) | no | — | S3 key under owner-tenant prefix (04 §6.4) |
| url | VARCHAR(512) | yes | NULL | external video URL (YouTube/Vimeo) |
| alt | VARCHAR(255) | yes | NULL | |
| position | SMALLINT | no | 0 | ordered gallery |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_id, position), IDX(product_demo_id, position).
**Unique:** none. **Relations:** `belongsTo(product, demo, variant)`.
**Soft delete:** yes.

#### 4.4.5 `product_packages`
*Purpose:* bundle definitions (bundle depth = 1 — 06 FR-PRODUCT-008). · *Class:* global (via product) · *Soft delete:* yes · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE (the bundle product) |
| name | VARCHAR(191) | no | — | |
| description | VARCHAR(500) | yes | NULL | |
| position | SMALLINT | no | 0 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_id). **Unique:** none (one package per bundle, app rule).
**Relations:** `belongsTo(product)`, `hasMany(packageItems)`. **Soft delete:** yes.

#### 4.4.6 `package_items`
*Purpose:* bundle component lines pointing at non-bundle products. · *Class:* global (via product) · *Soft delete:* yes · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_package_id | BIGINT UNSIGNED | no | — | FK product_packages(id) CASCADE |
| item_product_id | BIGINT UNSIGNED | no | — | FK products(id) RESTRICT; app validation rejects bundle-in-bundle |
| quantity | SMALLINT UNSIGNED | no | 1 | |
| position | SMALLINT | no | 0 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_package_id). **Unique:** UNQ(product_package_id, item_product_id).
**Relations:** `belongsTo(package)`, `belongsTo(itemProduct)`. **Soft delete:** yes.
Floor sanity at authoring: Σ component floors ≤ bundle price (06 FR-PRODUCT-044).

#### 4.4.7 `product_addons`
*Purpose:* standalone add-on definitions with own price, floor, fulfillment (06 §7.3). · *Class:* global (via owner tenant) · *Soft delete:* yes · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| owner_tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| name | VARCHAR(191) | no | — | |
| slug | VARCHAR(191) | no | — | UNQ(owner_tenant_id, slug) |
| description | TEXT | yes | NULL | |
| product_type | VARCHAR(32) | no | 'digital_service' | same 6-value enum as `products.product_type` (ad-hoc/one-time types only in MVP) |
| fulfillment_driver | VARCHAR(64) | yes | NULL | add-on's own fulfillment (06 FR-PRODUCT-045) |
| fulfillment_config | JSON | yes | NULL | §2.7 shape |
| is_active | TINYINT(1) | no | 1 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(owner_tenant_id, is_active). **Unique:** UNQ(owner_tenant_id, slug).
**Relations:** `belongsTo(ownerTenant)`, `belongsToMany(products, product_addon_links)`,
`hasOne(product_pricing scope)`. **Soft delete:** yes.

#### 4.4.8 `product_addon_links`
*Purpose:* product ↔ add-on attachment rows. · *Class:* global (via product) · *Soft delete:* yes · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| product_addon_id | BIGINT UNSIGNED | no | — | FK product_addons(id) CASCADE |
| position | SMALLINT | no | 0 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_addon_id). **Unique:** UNQ(product_id, product_addon_id).
**Relations:** `belongsTo(product)`, `belongsTo(addon)`. **Soft delete:** yes.

#### 4.4.9 `product_pricing`
*Purpose:* effective-dated base prices + platform floor for product / variant / add-on,
per currency (06 §7). · *Class:* global (base) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | yes | NULL | FK products(id) CASCADE — scope A |
| product_variant_id | BIGINT UNSIGNED | yes | NULL | FK product_variants(id) CASCADE — scope B |
| product_addon_id | BIGINT UNSIGNED | yes | NULL | FK product_addons(id) CASCADE — scope C |
| currency | CHAR(3) | no | 'INR' | §2.3 |
| base_price | DECIMAL(15,2) | no | — | list base price |
| floor_type | VARCHAR(16) | no | 'fixed' | Enum: fixed, percent (percent ⇒ of current base price — 06 FR-PRODUCT-040) |
| floor_value | DECIMAL(15,2) | no | — | effective floor computed at validation time |
| effective_from | DATE | no | — | effective-dating (06 FR-PRODUCT-038) |
| effective_to | DATE | yes | NULL | open-ended when NULL |
| is_active | TINYINT(1) | no | 1 | single active floor per scope enforced app-side |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(product_id, currency, effective_from), IDX(product_variant_id),
IDX(product_addon_id). **Unique:** none (history retained by effective dating).
**Relations:** `belongsTo(product, variant, addon)`. **Soft delete:** no.
**CHECK:** exactly one scope column is non-NULL
(`((product_id IS NOT NULL) + (product_variant_id IS NOT NULL) + (product_addon_id IS NOT NULL)) = 1`)
— enforced by MySQL 8 CHECK (§9.8 records this as a design decision).

#### 4.4.10 `tenant_products`
*Purpose:* distribution grants: one row authorizes one reseller tenant to sell one
product, with price/commission overrides (06 §6). · *Class:* tenant-owned · *Soft delete:* no · *Vol:* L

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT — **grantee** |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) RESTRICT |
| grant_scope | VARCHAR(16) | no | 'tenant' | Enum: tenant, subtree |
| subtree_root_tenant_id | BIGINT UNSIGNED | yes | NULL | FK tenants(id) RESTRICT; non-NULL iff grant_scope = subtree; resolved via `reseller_hierarchy` at catalog build |
| price_override | DECIMAL(15,2) | yes | NULL | selling-price override; validated ≥ floor at set time (06 FR-PRODUCT-039) |
| commission_override | DECIMAL(5,2) | yes | NULL | % override of product reseller commission (bounded by 08 minimums) |
| status | VARCHAR(32) | no | 'active' | Enum: active, suspended, revoked (06 App. B); explicit deny beats subtree grant |
| granted_by | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL |
| granted_at | DATETIME | no | — | |
| expires_at | DATETIME | yes | NULL | auto-revoke by scheduled job (06 §6.6) |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(product_id, status), IDX(tenant_id, status),
IDX(subtree_root_tenant_id, status).
**Unique:** UNQ(tenant_id, product_id, grant_scope) — allows a tenant to hold one direct
row **and** one subtree row for the same product (explicit deny wins).
**Relations:** `belongsTo(tenant, product, granter)`.
**Soft delete:** no (status-driven; entitlements survive revocation).

#### 4.4.11 `product_demos`
*Purpose:* demo instances: live URL w/ encrypted credentials, video, screenshot gallery. · *Class:* global (via product) · *Soft delete:* yes · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| title | VARCHAR(191) | no | — | |
| access_type | VARCHAR(16) | no | 'open' | Enum: open, lead_gate (06 §10) |
| live_url | VARCHAR(512) | yes | NULL | demo environment URL |
| credentials_username | VARCHAR(191) | yes | NULL | reveal is MASKED (first 2 chars + mask); full reveal permission-gated + audited |
| credentials_encrypted | TEXT | yes | NULL | Laravel encrypted cast — never plaintext, never logged (06 FR-DEMO-003) |
| video_url | VARCHAR(512) | yes | NULL | external video URL |
| video_path | VARCHAR(512) | yes | NULL | direct upload (tenant S3 prefix) |
| access_grant_hours | SMALLINT UNSIGNED | no | 24 | lead-gate access-grant window (06 §10) |
| status | VARCHAR(32) | no | 'active' | Enum: active, needs_reset, expired (06 App. B) |
| environment_created_at | DATETIME | yes | NULL | TTL flagging source for `needs_reset` |
| credentials_rotated_at | DATETIME | yes | NULL | manual rotation stamp (06 FR-DEMO-011) |
| schedule_windows | JSON | yes | NULL | P2 active windows (06 FR-DEMO-012); §2.7 |
| position | SMALLINT | no | 0 | multiple demos per product |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_id, status). **Unique:** none.
**Relations:** `belongsTo(product)`, `hasMany(demoAccessTokens, productMedia)`.
**Soft delete:** yes.

#### 4.4.12 `product_versions`
*Purpose:* immutable MAJOR.MINOR releases with changelog + content snapshot. · *Class:* global (via product) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| version | VARCHAR(16) | no | — | MAJOR.MINOR; UNQ(product_id, version); immutable once created (06 §8) |
| changelog | MEDIUMTEXT | no | — | rich text |
| content_snapshot | JSON | yes | NULL | **06 §8 spec delta**: snapshot of sellable content at release |
| content_storage_key | VARCHAR(512) | yes | NULL | large snapshots in S3 (pointer form) |
| released_at | DATETIME | yes | NULL | |
| created_by | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL |
| created_at / updated_at | DATETIME | no | — | TS; rows immutable (updated_at frozen after insert) |

**Indexes:** IDX(product_id, released_at). **Unique:** UNQ(product_id, version).
**Relations:** `belongsTo(product, createdBy)`; `hasMany(orderItems pinned)`.
**Soft delete:** no (versions are never removed — customers stay pinned, 06 FR-PRODUCT-048).

#### 4.4.13 `product_reviews`
*Purpose:* verified-purchaser reviews with moderation queue. · *Class:* tenant-owned (author) + shared-read (owner/platform) · *Soft delete:* no · *Vol:* M

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) RESTRICT |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT — reviewing storefront (authors never leave their tenant) |
| customer_id | BIGINT UNSIGNED | no | — | FK customers(id) RESTRICT |
| order_item_id | BIGINT UNSIGNED | no | — | FK order_items(id) RESTRICT — verified-purchase evidence (FULFILLED order) |
| rating | TINYINT UNSIGNED | no | — | CHECK 1–5 |
| title | VARCHAR(191) | yes | NULL | |
| body | TEXT | no | — | 10–2,000 chars; sanitized plain text |
| status | VARCHAR(32) | no | 'pending' | Enum: pending, approved, rejected, flagged (06 App. B); refund ⇒ back to pending |
| edited_at | DATETIME | yes | NULL | 14-day edit window (06 §9) |
| published_at | DATETIME | yes | NULL | only approved rows render/count |
| moderated_by | BIGINT UNSIGNED | yes | NULL | FK users(id) SET NULL |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(product_id, status, created_at), IDX(tenant_id, status).
**Unique:** UNQ(tenant_id, customer_id, product_id) — one review per customer per product
per tenant (06 §9).
**Relations:** `belongsTo(product, tenant, customer, orderItem, moderator)`.
**Soft delete:** no (status-driven).

#### 4.4.14 `product_documents`
*Purpose:* downloadable product attachments (manuals, datasheets). · *Class:* global (via product) · *Soft delete:* yes · *Vol:* S

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) CASCADE |
| title | VARCHAR(191) | no | — | |
| path | VARCHAR(512) | no | — | S3 key under owner prefix |
| mime_type | VARCHAR(125) | no | — | |
| size_bytes | INT UNSIGNED | no | 0 | |
| visibility | VARCHAR(16) | no | 'public' | Enum: public, authenticated (purchasers) |
| position | SMALLINT | no | 0 | |
| created_at / updated_at | DATETIME | no | — | TS |
| deleted_at | TIMESTAMP | yes | NULL | SoftDeletes |

**Indexes:** IDX(product_id, position). **Unique:** none.
**Relations:** `belongsTo(product)`. **Soft delete:** yes.

#### 4.4.15 `demo_access_tokens` *(Added by doc 13 — deferred demo-token store, 06 §10)*
*Purpose:* unique per-visitor demo link `/d/{token}` with state + attribution. · *Class:* tenant-owned · *Soft delete:* no · *Vol:* L

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT — selling tenant (branding + attribution) |
| product_demo_id | BIGINT UNSIGNED | no | — | FK product_demos(id) RESTRICT |
| product_id | BIGINT UNSIGNED | no | — | FK products(id) RESTRICT — denormalized for funnels |
| token | CHAR(32) | no | — | opaque 128-bit random; **UNQ(token)**; rendered at `{domain}/d/{token}` |
| state | VARCHAR(32) | no | 'active' | Enum: active, expired, revoked (06 §10 — canonical names per 06 A6) |
| lead_id | BIGINT UNSIGNED | yes | NULL | FK leads(id) SET NULL — set on gate form submit |
| granted_at | DATETIME | yes | NULL | lead-gate grant time; expiry = granted_at + demo.access_grant_hours |
| expires_at | DATETIME | yes | NULL | |
| first_viewed_at / last_viewed_at | DATETIME | yes | NULL | funnel + attribution window anchor (30 days default) |
| converted_at | DATETIME | yes | NULL | stamped when a paid order carries this token (conversion; orders side holds the FK) |
| created_at / updated_at | DATETIME | no | — | TS |

**Indexes:** IDX(tenant_id, state), IDX(product_demo_id, created_at),
IDX(tenant_id, created_at). **Unique:** UNQ(token).
**Relations:** `belongsTo(tenant, demo, product, lead)`, `hasMany(demoTokenEvents)`.
**Soft delete:** no (expired/revoked states instead).

#### 4.4.16 `demo_token_events` *(Added by doc 13 — implements 06 FR-DEMO-008 tracking)*
*Purpose:* per-token event stream: view, heartbeat, credential_revealed, cta_click,
form_submit. · *Class:* tenant-owned · *Soft delete:* no · *Vol:* XL (partition candidate §5.7)

| Column | Type | Null | Default | Keys / notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | no | auto_increment | PK (composite PK with created_at if partitioned — §5.7) |
| demo_access_token_id | BIGINT UNSIGNED | no | — | FK demo_access_tokens(id) CASCADE |
| tenant_id | BIGINT UNSIGNED | no | — | FK tenants(id) RESTRICT |
| event | VARCHAR(32) | no | — | Enum: view, heartbeat, credential_revealed, cta_click, form_submit (06 §10) |
| duration_seconds | SMALLINT UNSIGNED | yes | NULL | 15-second heartbeat pings |
| payload | JSON | yes | NULL | event-specific detail (§2.7) |
| ip_address | VARCHAR(45) | yes | NULL | |
| created_at / updated_at | DATETIME | no | — | TS; append-only (updated_at frozen) |

**Indexes:** IDX(demo_access_token_id, created_at), IDX(tenant_id, created_at).
**Unique:** none. **Relations:** `belongsTo(token, tenant)`.
**Soft delete:** no; aggregates per demo maintained by hourly rollup job.

<!-- SECTION-4-CONTINUES -->

## Ratified P0 decisions (2026-11-20)

Decisions below are ratified in `22-open-questions-and-assumptions.md` §5; see that
section for ratifier, date and launch-gate caveats.

| OQ-001 | `platform_settings`: platform fee value (default 0%); dedicated platform revenue leg in `journal_lines`. |
| OQ-007 | Commission `hold_days` configurable, constrained `>= refund_window_days` per product type. |
| OQ-008 | Payout minimum and fee policy in `platform_settings`; `payouts` records bank/UPI method and fee absorption. |
| OQ-013 | Split table supports arbitrary depth; default rows for depths 1–3 (50/30/20), deeper default 0%. |
| OQ-020 | `tenant_domains` retains verification state, token and SSL sub-state; provider-agnostic. |
| OQ-027 | KYC artifact fields per tenant with restricted access and audit views. |
