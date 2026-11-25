# Frenchies — Non-Functional Requirements

| Field | Value |
|---|---|
| Document | 18-non-functional-requirements.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Platform Architecture & DevOps |
| Related documents | `04-multi-tenancy-architecture.md`, `13-database-design.md`, `14-api-specification.md`, `16-ui-ux-specification.md`, `17-security-requirements.md`, `19-testing-and-quality-assurance.md`, `20-development-roadmap.md` |

## Table of Contents

1. Purpose & Scope
2. Performance
3. Availability & Reliability
4. Scalability
5. Observability
6. Security Operations
7. Backup, Recovery & Retention
8. Accessibility
9. Browser & Responsive Support
10. Data Integrity & Consistency
11. Capacity & Growth Assumptions
12. NFR Acceptance Criteria
13. Assumptions & Open Questions

---

## 1. Purpose & Scope

This document defines platform-wide service levels and technical quality constraints.
Requirements use `NFR-NNN` identifiers and apply across the Laravel monolith, Redis,
MySQL, Horizon, Reverb and external integrations.

Where a requirement conflicts with product behavior, the requirement here and the
security or domain specification must be reconciled before implementation.

---

## 2. Performance

| ID | Priority | Requirement |
|---|---|---|
| NFR-PERF-001 | P0 | P95 HTTP response time shall be ≤ 500 ms for standard dashboard list/detail reads under MVP load. |
| NFR-PERF-002 | P0 | P95 storefront catalog and product-detail response time shall be ≤ 800 ms under MVP load. |
| NFR-PERF-003 | P0 | Checkout totals shall be recalculated and returned in ≤ 800 ms P95 before payment initiation. |
| NFR-PERF-004 | P0 | Realtime notification events shall reach an open authenticated dashboard within 2 seconds P95. |
| NFR-PERF-005 | P0 | Global search shall return results within 500 ms P95 for a typed query meeting its minimum length. |
| NFR-PERF-006 | P0 | Audit log filtering shall return 100k-row datasets within 500 ms P95 with required indexes. |
| NFR-PERF-007 | P1 | Image and document assets shall use appropriate size limits, variants or delivery optimization where supported. |
| NFR-PERF-008 | P1 | P95 API latency shall be ≤ 1 second for standard resource reads and mutations excluding external gateway latency. |

### 2.1 MVP Load Baseline

- 100 concurrent authenticated dashboard users
- 500 concurrent storefront sessions
- 50 orders per minute at peak
- 100 webhook events per minute across active gateways
- 200 notification events per minute

The baseline is a minimum test load, not a claim of production capacity.

---

## 3. Availability & Reliability

| ID | Priority | Requirement |
|---|---|---|
| NFR-AVL-001 | P0 | MVP application services shall target 99.9% monthly availability, excluding scheduled maintenance. |
| NFR-AVL-002 | P0 | Database and application availability shall be recoverable independently of a single application host. |
| NFR-AVL-003 | P0 | Queue failures shall remain visible and retryable without duplicate external effects. |
| NFR-AVL-004 | P0 | Webhook and renewal processing shall resume safely after worker, Redis or network recovery. |
| NFR-AVL-005 | P1 | Scheduled maintenance shall be announced through the platform-defined status channel. |
| NFR-AVL-006 | P1 | External gateway, email or fulfillment outage shall degrade with visible retry or manual processing rather than silently losing work. |

---

## 4. Scalability

| ID | Priority | Requirement |
|---|---|---|
| NFR-SCALE-001 | P0 | Tenant isolation shall remain correct when tenants share database, Redis, queue and storage infrastructure. |
| NFR-SCALE-002 | P0 | Database migrations shall support shared-schema data growth and reversible rollout. |
| NFR-SCALE-003 | P1 | Queue concurrency shall be configurable by environment and tenant-safe. |
| NFR-SCALE-004 | P1 | Cache keys and locked resources shall include tenant identity where tenant context is required. |
| NFR-SCALE-005 | P1 | Storage object names shall remain tenant-scoped and not rely on predictable public keys. |
| NFR-SCALE-006 | P1 | Product, order, audit and ledger queries shall be indexed against documented filter and join patterns. |

---

## 5. Observability

| ID | Priority | Requirement |
|---|---|---|
| NFR-OBS-001 | P0 | Requests, jobs and external integration operations shall carry a correlation ID. |
| NFR-OBS-002 | P0 | Application logs shall include service, environment, tenant ID, actor ID, route/job and correlation ID where permitted. |
| NFR-OBS-003 | P0 | Logs shall redact passwords, secrets, full payment instruments and authorization credentials. |
| NFR-OBS-004 | P0 | Health checks shall cover application, database, Redis, queue and Reverb connectivity. |
| NFR-OBS-005 | P0 | Critical queue, webhook, payment, fulfillment and payout failure rates shall generate alerts. |
| NFR-OBS-006 | P1 | Metrics shall cover HTTP latency, queue depth/age, worker failures, database latency and Reverb connections. |
| NFR-OBS-007 | P1 | Financial reconciliation exceptions shall be visible before a settlement or payout period closes. |

---

## 6. Security Operations

| ID | Priority | Requirement |
|---|---|---|
| NFR-SECOPS-001 | P0 | Production secrets shall not appear in Nginx, Docker, CI logs or exported environment files. |
| NFR-SECOPS-002 | P0 | Privileged operational actions shall be audited and alertable. |
| NFR-SECOPS-003 | P1 | CI shall validate migrations, builds, code style, tests and dependency security before deployment. |
| NFR-SECOPS-004 | P1 | Audit log ingestion and export shall remain operational during application degradation. |

---

## 7. Backup, Recovery & Retention

| ID | Priority | Requirement |
|---|---|---|
| NFR-REC-001 | P0 | Production database backups shall use encrypted storage with an agreed recovery cadence. |
| NFR-REC-002 | P0 | Point-in-time or equivalent recoverability shall support the approved RPO. |
| NFR-REC-003 | P0 | Application recovery shall support the approved RTO. |
| NFR-REC-004 | P0 | Recovery tests shall be executed at least quarterly and after major topology changes. |
| NFR-REC-005 | P0 | Financial and audit records shall retain data for the legal and policy period after deactivation. |
| NFR-REC-006 | P1 | Object-storage documents shall include lifecycle, retention and tenant-deletion rules. |

### 7.1 Recovery Targets

| Target | MVP Proposed Value |
|---|---|
| RPO | ≤ 15 minutes for database writes |
| RTO | ≤ 2 hours for application/database recovery |
| Backup verification | Monthly restore test plus quarterly full recovery exercise |

These targets require owner confirmation and infrastructure validation.

---

## 8. Accessibility

| ID | Priority | Requirement |
|---|---|---|
| NFR-A11Y-001 | P0 | Primary interfaces shall meet WCAG 2.2 AA for keyboard, labeling, focus and semantic structure. |
| NFR-A11Y-002 | P0 | Status, validation and loading changes shall expose appropriate ARIA or live-region feedback. |
| NFR-A11Y-003 | P1 | Text and UI components shall meet WCAG contrast requirements in default and white-label themes. |
| NFR-A11Y-004 | P1 | Core workflows shall be operable without drag-and-drop or pointer-only interaction. |
| NFR-A11Y-005 | P1 | Public and dashboard pages shall provide a visible skip-to-content link. |

---

## 9. Browser & Responsive Support

| ID | Priority | Requirement |
|---|---|---|
| NFR-RESP-001 | P0 | Dashboards shall support modern desktop browsers at 1280 px width and above. |
| NFR-RESP-002 | P0 | Storefront, customer portal and affiliate portal shall support mobile layouts down to 320 px. |
| NFR-RESP-003 | P1 | Primary actions shall remain reachable without horizontal overflow in supported layouts. |
| NFR-RESP-004 | P1 | Supported browser matrix shall exclude legacy Internet Explorer and end-of-life browser releases. |

---

## 10. Data Integrity & Consistency

| ID | Priority | Requirement |
|---|---|---|
| NFR-DATA-001 | P0 | Financial mutations shall use database transactions and constraints to prevent partial state. |
| NFR-DATA-002 | P0 | Payment totals, tax and currency fields shall use exact decimal values. |
| NFR-DATA-003 | P0 | State machines shall reject illegal transitions and persist an audit event for legal transitions. |
| NFR-DATA-004 | P0 | Idempotency keys shall prevent duplicate writes for retried jobs, webhooks and mutations. |
| NFR-DATA-005 | P1 | Soft-deleted tenants and users shall remain excluded by default from operational queries. |

---

## 11. Capacity & Growth Assumptions

| ID | Priority | Requirement |
|---|---|---|
| NFR-CAP-001 | P1 | MVP sizing shall support at least 100 active reseller tenants and the 12-month growth goals without schema redesign. |
| NFR-CAP-002 | P1 | Architecture changes shall be measurable through load and data-growth tests rather than assumed by code review. |
| NFR-CAP-003 | P1 | A scaling threshold shall trigger alert or review before queue or database saturation. |
| NFR-CAP-004 | P2 | Internationalization, multi-currency and additional tax jurisdictions are excluded from MVP unless decided separately. |

---

## 12. NFR Acceptance Criteria

| AC ID | Acceptance criterion |
|---|---|
| AC-NFR-001 | P95 latency targets pass in the MVP load suite at agreed concurrency and data volume. |
| AC-NFR-002 | Monthly availability evidence and alerting dashboards are available for launch. |
| AC-NFR-003 | Redis, MySQL, Horizon, Reverb and external integration health are represented in `/health` or operations tooling. |
| AC-NFR-004 | Audit, error and correlation logs contain enough traceability while masking secrets and PII. |
| AC-NFR-005 | RPO/RTO restore exercises meet or document exceptions before production launch. |
| AC-NFR-006 | Automated and manual accessibility checks cover the core reseller, customer and support workflows. |
| AC-NFR-007 | Financial integrity and idempotency tests pass with no duplicates or partial journal entries. |

---

## 13. Assumptions & Open Questions

1. OQ-026 determines Redis, Horizon and Reverb topology and sizing.
2. OQ-037 determines the final uptime commitment and service-credit terms.
3. RPO and RTO values in this document are proposed targets pending infrastructure validation.
4. Browser and accessibility targets apply to the shipped frontend stack unless a
   documented exception is approved.
