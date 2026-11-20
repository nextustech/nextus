# Frenchies — Testing & Quality Assurance

| Field | Value |
|---|---|
| Document | 19-testing-and-quality-assurance.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | QA & Engineering Team |
| Related documents | `15-user-workflows.md`, `17-security-requirements.md`, `18-non-functional-requirements.md`, `20-development-roadmap.md`, `22-open-questions-and-assumptions.md` |

## Table of Contents

1. Purpose & Scope
2. Quality Principles
3. Test Pyramid
4. Suite Catalogue
5. Tenancy Isolation Regression
6. Authentication & Authorization
7. Financial Idempotency
8. Workflow Tests
9. API & Webhook Tests
10. Performance & Reliability
11. Accessibility & Responsive QA
12. UAT
13. Release Quality Gates
14. Defect Severity
15. Assumptions & Open Questions

---

## 1. Purpose & Scope

This document defines the test strategy, suite ownership and release gates for
Frenchies. It covers unit, integration, database, API, E2E, security, load,
accessibility and UAT validation.

Test IDs shall use the format `TEST-NNN` or suite-specific IDs owned by this document.
Workflow IDs from `15-user-workflows.md`, requirement IDs from
`03-functional-requirements.md`, security IDs from `17-security-requirements.md` and
NFR IDs from `18-non-functional-requirements.md` must be traceable to test cases.

---

## 2. Quality Principles

1. Every P0 requirement shall have at least one positive and one negative test.
2. Money, entitlement, webhook and lifecycle effects shall be replay-tested.
3. Tenant isolation shall be tested by both authorization and database scope.
4. Tests shall use explicit tenant, role and permission fixtures rather than Super
   Admin bypasses.
5. UI tests shall verify loading, empty, error and permission states.
6. A release may not pass with unresolved P0/P1 defects.
7. Test evidence shall retain environment, revision, suite version and result artifacts.

---

## 3. Test Pyramid

| Level | Focus | Examples |
|---|---|---|
| Unit | Pure domain rules and models | GST, ledger, commission, state transition, validation |
| Integration | Laravel services and database | Tenant scope, RBAC, queue jobs, webhooks, invoice generation |
| Component | Vue/Inertia interactions | Forms, tables, filters, modals, permission-visible controls |
| API | Request contracts | Auth, error envelope, rate limits, idempotency, pagination |
| E2E | Canonical persona journeys | Onboarding, checkout, payout, ticket, affiliate, support |
| Security | Isolation and abuse | Cross-tenant access, MFA, secrets, replay, role escalation |
| Performance | Latency and concurrency | NFR load baseline, queue depth, database and Reverb behavior |
| UAT | Real-user acceptance | Reseller setup, first sale, payout and refund review |

---

## 4. Suite Catalogue

| Suite ID | Suite | Minimum Coverage |
|---|---|---|
| TEST-SUIT-001 | Authentication & Tenant Session | Login, logout, reset, MFA, lockout, invitation, impersonation |
| TEST-SUIT-002 | Tenant Isolation | Cross-tenant CRUD, global scopes, search, export, queue/cache/storage |
| TEST-SUIT-003 | Roles & Permissions | 12 canonical roles, custom templates, tenant teams, blocked financial slugs |
| TEST-SUIT-004 | Reseller Lifecycle | Application, KYC, approval, activation, suspension, resume |
| TEST-SUIT-005 | Catalog & Storefront | Authorization, demo, variants, MSP, pricing, CMS and domain |
| TEST-SUIT-006 | Cart, Order & Payment | Totals, gateway, manual payment, invoice, receipt, offline conversion |
| TEST-SUIT-007 | Subscription | Lifecycle, retry, grace, suspension, upgrade, downgrade, cancellation |
| TEST-SUIT-008 | Refund & Ledger | Approval, clawback, replay, credit note, reconciliation |
| TEST-SUIT-009 | Commission & Payout | Splits, holds, release, duplicate payout, negative balance |
| TEST-SUIT-010 | Fulfillment & License | Adapter success/failure, retries, activation limit, entitlement revoke |
| TEST-SUIT-011 | CRM & Support | Lead stages, quotation, ticket routing, SLA, escalation, CSAT |
| TEST-SUIT-012 | Affiliate | Link, cookie, last-touch/self-referral, conversion and payout |
| TEST-SUIT-013 | AI & Automation | Tenant scoping, safety refusal, usage caps, provider failover |
| TEST-SUIT-014 | Observability & Recovery | Health checks, restore, queue recovery, log and correlation integrity |
| TEST-SUIT-015 | Accessibility & Responsive | Core screens at desktop/mobile with WCAG 2.2 AA checks |

---

## 5. Tenancy Isolation Regression

The suite shall create at least two active reseller tenants and tenant-specific records
for users, products, orders, customers, leads, tickets, wallets and audit logs.

Required cases:

1. Direct record ID request from a different tenant.
2. List, export and global-search leakage checks.
3. Queue job, cache key and broadcast channel isolation.
4. Signed document and invoice URL isolation.
5. API key and bearer-token tenant mismatch.
6. Support department and CRM assignment boundaries.
7. Platform staff explicit and implicit cross-tenant access rules.
8. Super Admin impersonation banner and money-mutation restrictions.

No isolation test may assert only a hidden UI control; it must verify server response
and database/read scope.

**Minimum gate:** at least 20 negative isolation cases with zero P0/P1 failures.

---

## 6. Authentication & Authorization

Required tests include:

- Invalid and locked accounts
- Tenant mismatch
- MFA required and bypass attempt
- Expired and revoked invitation
- Password reset replay
- Role assignment outside allowed scope
- Session invalidation after suspension
- Permission matrix for all 12 canonical roles
- Row ownership for Customer and Affiliate roles

Authorization tests shall include direct HTTP requests rather than only route rendering.

---

## 7. Financial Idempotency

Required replay tests:

1. Same payment webhook arrives twice.
2. Same refund callback arrives twice.
3. Same renewal reference arrives twice.
4. Same payout request arrives twice.
5. Same commission release job is retried.
6. Same refund or clawback is submitted from different jobs.
7. Partial gateway success followed by timeout and retry.

Each test shall assert exactly one external or ledger effect and no duplicate invoices,
notifications or entitlement changes.

---

## 8. Workflow Tests

Each workflow in `15-user-workflows.md` shall have at least:

- One happy-path test
- Required validation failures
- Permission denial
- Tenant mismatch
- Stale or illegal state transition
- Replay or idempotency case where money or entitlement is involved

Canonical UAT journeys include `FLOW-02-03`, `FLOW-05-02`, `FLOW-06-01`,
`FLOW-09-02`, `FLOW-11-02`, `FLOW-12-02`, `FLOW-13-01` and `FLOW-14-02`.

---

## 9. API & Webhook Tests

Required API coverage:

- Canonical success/error envelope
- 401, 403, 404 and 422 distinctions
- Pagination and filter state
- Rate limiting
- API key revoke and ability scope
- Signed webhook timestamp, signature and replay checks
- Idempotency key collision and tenant mismatch
- No stack trace or secret disclosure

---

## 10. Performance & Reliability

Tests shall execute against representative schema size and the MVP load baseline:

- P95 standard dashboard reads ≤ 500 ms
- P95 storefront reads ≤ 800 ms
- P95 checkout totals ≤ 800 ms
- Reverb notification delivery ≤ 2 seconds
- Audit filter ≤ 500 ms at 100k rows
- Queue recovery after Redis or worker interruption
- Gateway timeout and fulfillment retry behavior
- Database restore and RPO/RTO exercise

Performance tests must report P50, P95 and P99, throughput, error rate and environment
metadata.

---

## 11. Accessibility & Responsive QA

Core journeys shall be checked for:

- Keyboard reachability and focus restoration
- Skip link and heading structure
- Form label and error association
- Modal focus trap and escape behavior
- WCAG 2.2 AA contrast
- 320 px storefront layout
- Permission-hidden controls and server-enforced restrictions

Automated accessibility findings require manual confirmation before triage.

---

## 12. UAT

The beta UAT cohort shall include:

- 3–5 active reseller partners
- At least one sub-reseller hierarchy
- Customer checkout and subscription journeys
- Support ticket and escalation journey
- Affiliate registration and payout journey
- Reseller payout and refund scenarios
- Custom-domain and branding setup

UAT evidence shall identify actor, tenant, browser/device, data fixture and pass/fail
result for each critical workflow.

---

## 13. Release Quality Gates

| Gate | Required result |
|---|---|
| G-QA-01 Build | Laravel build, Pint, PHPUnit and Vite build pass with zero deprecation warnings |
| G-QA-02 Unit/Integration | P0/P1 suites pass; no unexplained skipped P0 tests |
| G-QA-03 Isolation | Cross-tenant suite passes with zero P0/P1 findings |
| G-QA-04 Security | Critical/high findings closed or formally approved |
| G-QA-05 Financial | Ledger, refund, commission and payout replay suites pass |
| G-QA-06 Performance | MVP NFR load suite passes or release owner approves explicit exception |
| G-QA-07 Accessibility | Core personas pass WCAG checks or owner approves time-bound remediation |
| G-QA-08 UAT | Critical reseller/customer/support journeys accepted |
| G-QA-09 Defects | Zero open P0/P1 defects; P2 defects have named owner and target |

No feature flag may hide an unresolved P0 or P1 defect before GA.

---

## 14. Defect Severity

| Severity | Definition | Release treatment |
|---|---|---|
| P0 | Security, financial, data-loss or availability-critical defect | Blocks release; immediate owner and hotfix path |
| P1 | Core workflow or regulatory defect with no practical workaround | Blocks release unless formal exception |
| P2 | Moderate defect with workaround or limited impact | May ship with owner and target |
| P3 | Cosmetic or advisory issue | Backlog unless bundled with a launch requirement |

---

## 15. Assumptions & Open Questions

1. Test environments shall replicate PHP, database, Redis, queue and Reverb topology
   sufficiently to reveal integration defects.
2. Gateway and provider sandbox credentials are required before phase-specific suite
   completion.
3. The UAT cohort and beta duration are subject to product-owner confirmation.
4. OQ decisions affecting refund, payout, subscription or SLA behavior must be closed
   before their dependent suites are finalized.
