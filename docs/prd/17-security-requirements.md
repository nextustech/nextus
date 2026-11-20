# Frenchies — Security Requirements

| Field | Value |
|---|---|
| Document | 17-security-requirements.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Security & Architecture Team |
| Related documents | `02-user-roles-and-permissions.md`, `04-multi-tenancy-architecture.md`, `14-api-specification.md`, `15-user-workflows.md`, `17-security-requirements.md`, `18-non-functional-requirements.md`, `19-testing-and-quality-assurance.md`, `22-open-questions-and-assumptions.md` |

## Table of Contents

1. Purpose & Scope
2. Security Principles
3. Authentication & Session Security
4. Authorization & Tenant Isolation
5. Secrets & Cryptography
6. Audit Logging
7. API & Webhook Security
8. Payment & Financial Security
9. Data Protection & Privacy
10. Fraud & Abuse Controls
11. Secure SDLC & Operations
12. Security Acceptance Criteria
13. Assumptions & Open Questions

---

## 1. Purpose & Scope

This document defines the platform-wide security controls for the Frenchies monolith.
The requirements use the stable identifier format `SEC-NNN` and must be enforced for
admin, reseller, storefront, customer, affiliate and integration surfaces.

Security requirements in this document complement the permission catalogue in
`02-user-roles-and-permissions.md`, tenancy architecture in
`04-multi-tenancy-architecture.md`, API conventions in `14-api-specification.md` and
test gates in `19-testing-and-quality-assurance.md`.

---

## 2. Security Principles

1. **Deny by default:** no route or model action is authorized without tenant and
   permission checks.
2. **Least privilege:** every role receives only the minimal team-scoped permission set.
3. **Defence in depth:** UI visibility, server authorization, row scope and data
   validation are independent controls.
4. **Auditability:** sensitive and security-relevant actions retain actor, tenant,
   action, subject and before/after values.
5. **Secure failure:** out-of-scope resources return 403 or a non-disclosing 404 and
   never expose existence or neighboring tenant data.
6. **Idempotent money:** payment, refund, payout and commission effects are replay-safe.
7. **Secrets isolation:** credentials are never stored in database configuration or
   source control in recoverable plaintext.

---

## 3. Authentication & Session Security

| ID | Priority | Requirement |
|---|---|---|
| SEC-AUTH-001 | P0 | Passwords shall use Laravel's configured Argon2id or an equivalent reviewed password-hash mechanism; plaintext passwords shall never be logged or retained. |
| SEC-AUTH-002 | P0 | Login shall be protected by tenant-aware rate limits, progressive lockout and generic failure responses. |
| SEC-AUTH-003 | P0 | Session cookies shall use `Secure`, `HttpOnly` and `SameSite=Lax` or stricter settings appropriate to the flow. |
| SEC-AUTH-004 | P0 | MFA shall be mandatory for Super Admin and Finance Admin accounts before dashboard access. |
| SEC-AUTH-005 | P0 | MFA reset shall require a privileged, audited recovery workflow and out-of-band identity confirmation. |
| SEC-AUTH-006 | P0 | Password reset tokens shall be single-use, bounded in lifetime and invalidated after password change or session compromise. |
| SEC-AUTH-007 | P0 | Invitations shall be single-use, expire within the configured policy and bind to the target tenant and role. |
| SEC-AUTH-008 | P0 | Logout shall invalidate the active session; password or role changes shall invalidate sessions according to policy. |
| SEC-AUTH-009 | P1 | API token and Sanctum abilities shall be scoped to the tenant and expire or rotate under configurable policy. |
| SEC-AUTH-010 | P1 | Suspicious login patterns shall generate auditable security events and configurable alerts. |

---

## 4. Authorization and Tenant Isolation

| ID | Priority | Requirement |
|---|---|---|
| SEC-TENANT-001 | P0 | Every tenant-owned record shall carry `tenant_id` and be protected by a tenant global scope or equivalent repository-level guard. |
| SEC-TENANT-002 | P0 | `TenantContext` shall be resolved from verified host/subdomain/session context before route authorization. |
| SEC-TENANT-003 | P0 | Every mutation shall pass team-scoped permission and tenant ownership checks before a write. |
| SEC-TENANT-004 | P0 | Unauthenticated public routes shall use an explicit allowlist and shall not expose tenant records through weak object identifiers. |
| SEC-TENANT-005 | P0 | Tenant B resources requested through tenant A context shall return 403 or safe 404 and no cross-tenant content. |
| SEC-TENANT-006 | P0 | Platform staff cross-tenant access shall require an explicit grant or Super Admin scope. |
| SEC-TENANT-007 | P0 | Impersonation shall use a short expiry, visible banner, dual audit attribution and money-mutation denial. |
| SEC-TENANT-008 | P0 | Raw SQL, queue payloads, cache keys and storage paths shall retain tenant scope and shall not be externally enumerable. |
| SEC-TENANT-009 | P0 | Queue and broadcast authorization shall validate tenant and recipient identity rather than trusting a client-provided tenant. |
| SEC-TENANT-010 | P0 | Tenant lifecycle suspension shall block mutations and invalidate active operational access within the defined SLA. |

### 4.1 Isolation Acceptance

- Automated suite shall include at least 20 cross-tenant negative cases for P0 resources.
- A code-review checklist shall find zero unscoped queries in tenancy and
  tenant-owned modules.
- Global search shall return no record names or counts outside the user's tenant and
  permissions.

---

## 5. Secrets & Cryptography

| ID | Priority | Requirement |
|---|---|---|
| SEC-SECRET-001 | P0 | Secrets shall be managed through the deployment secrets mechanism or a secrets vault; source files and exports shall contain no live credentials. |
| SEC-SECRET-002 | P0 | Gateway credentials, webhook signing secrets and API keys shall be encrypted at rest. |
| SEC-SECRET-003 | P0 | Secret values shall not appear in logs, audit payloads, job payloads, exception reports or browser responses. |
| SEC-SECRET-004 | P0 | Secrets shall support rotation without changing application source. |
| SEC-SECRET-005 | P0 | Webhook, signing and encryption keys shall be rotated and deactivated with an auditable procedure. |
| SEC-SECRET-006 | P1 | Invoice and document signing secrets shall be separated from application session keys. |

---

## 6. Audit Logging

| ID | Priority | Requirement |
|---|---|---|
| SEC-AUDIT-001 | P0 | `audit_logs` shall record authentication, role, tenant lifecycle, financial, refund, payout and high-risk admin mutations. |
| SEC-AUDIT-002 | P0 | Audit entries shall contain actor, tenant, action, subject type/ID, IP, user agent, timestamp and before/after payload where applicable. |
| SEC-AUDIT-003 | P0 | Audit records shall be append-only for application roles and exportable only with `audit.export` where granted. |
| SEC-AUDIT-004 | P0 | Secret values and full payment instrument details shall be excluded or masked in audit payloads. |
| SEC-AUDIT-005 | P0 | A failed authorization attempt to a sensitive resource shall be recorded according to abuse-policy thresholds. |
| SEC-AUDIT-006 | P1 | Cross-tenant platform audit export shall enforce actor scope and preserve an export event. |

---

## 7. API & Webhook Security

| ID | Priority | Requirement |
|---|---|---|
| SEC-API-001 | P0 | Public API authentication shall use Sanctum or tenant-bound API keys with tenant and ability scope. |
| SEC-API-002 | P0 | API keys shall be shown once at creation, stored hashed and revocable immediately. |
| SEC-API-003 | P0 | Every write endpoint shall enforce CSRF or an equivalent bearer-token policy appropriate to the channel. |
| SEC-API-004 | P0 | Inbound webhooks shall verify signature, timestamp window and replay identifiers. |
| SEC-API-005 | P0 | Webhook replay shall produce no duplicate payment, ledger, invoice or notification effect. |
| SEC-API-006 | P0 | Requests shall be limited by authenticated or host-level rate policy and return standard 429 responses. |
| SEC-API-007 | P0 | Request and validation responses shall use the canonical error envelope and shall not disclose stack traces or secrets. |
| SEC-API-008 | P1 | Signed background and integration callbacks shall bind `tenant_id`, action and nonce. |

---

## 8. Payment & Financial Security

| ID | Priority | Requirement |
|---|---|---|
| SEC-FIN-001 | P0 | Money amounts shall use decimal types end-to-end and shall never rely on floating-point calculation. |
| SEC-FIN-002 | P0 | Refund initiation and gateway execution shall remain segregated where assigned roles differ. |
| SEC-FIN-003 | P0 | Payout approval shall require an authorized approver distinct from the requester. |
| SEC-FIN-004 | P0 | Wallet, ledger and commission mutations shall use database transactions and idempotency constraints. |
| SEC-FIN-005 | P0 | Gateway or manual settlement must be reconciled to journal entries and an immutable transaction record. |
| SEC-FIN-006 | P0 | Financial override permissions shall be restricted to named roles and audited with mandatory reason. |
| SEC-FIN-007 | P0 | Full card, bank or payment-instrument data shall never be retained outside the gateway. |
| SEC-FIN-008 | P1 | Excessive refund, clawback or payout behavior shall raise fraud-review alerts. |

---

## 9. Data Protection & Privacy

| ID | Priority | Requirement |
|---|---|---|
| SEC-DATA-001 | P0 | PII shall be encrypted or masked in transit and at rest under the applicable platform policy. |
| SEC-DATA-002 | P0 | Export endpoints shall be permission- and tenant-scoped and shall write an audit event. |
| SEC-DATA-003 | P0 | Soft delete, retention and data-export workflows shall preserve required financial and audit evidence. |
| SEC-DATA-004 | P0 | Support notes and tickets shall not expose PII or secrets outside granted department scope. |
| SEC-DATA-005 | P1 | Backups shall use access-controlled encrypted storage and be included in recovery tests. |
| SEC-DATA-006 | P1 | Document links shall be signed, short-lived and bound to authorization and tenant context. |
| SEC-DATA-007 | P1 | AI retrieval and prompt context shall include only authorized tenant knowledge sources. |

---

## 10. Fraud & Abuse Controls

| ID | Priority | Requirement |
|---|---|---|
| SEC-FRAUD-001 | P0 | Checkout, lead submission and account creation shall use rate limiting and bot controls. |
| SEC-FRAUD-002 | P0 | Suspicious duplicate accounts, self-referral and abnormal commission behavior shall generate review events. |
| SEC-FRAUD-003 | P0 | Refund and payout controls shall verify account ownership and transaction eligibility. |
| SEC-FRAUD-004 | P1 | Tenant suspension or abuse escalation shall preserve evidence and notify the designated operators. |
| SEC-FRAUD-005 | P1 | High-risk AI content or prompt-injection attempts shall be refused and auditable. |

---

## 11. Secure SDLC & Operations

| ID | Priority | Requirement |
|---|---|---|
| SEC-SDLC-001 | P0 | Every protected branch change shall require review and passing automated tests. |
| SEC-SDLC-002 | P0 | Dependency scans shall run in CI and block releases with unresolved critical vulnerabilities. |
| SEC-SDLC-003 | P0 | Production deployments shall use reproducible artifacts, secrets injection and rollback support. |
| SEC-SDLC-004 | P1 | Security-sensitive changes shall include a tenancy, authorization and audit impact review. |
| SEC-SDLC-005 | P1 | Critical services and privileged actions shall emit alerts with traceable correlation IDs. |
| SEC-SDLC-006 | P1 | Penetration testing shall cover authentication, tenant isolation, financial workflows and API access before GA. |

---

## 12. Security Acceptance Criteria

| AC ID | Acceptance criterion |
|---|---|
| AC-SEC-001 | No live secret appears in source control, build logs or application responses. |
| AC-SEC-002 | All 12 canonical roles are team-scoped and no role bypasses tenant isolation except explicitly documented platform behavior. |
| AC-SEC-003 | Isolation suite passes at least 20 negative cross-tenant cases with 0 P0/P1 findings. |
| AC-SEC-004 | Security-sensitive mutations produce immutable audit entries containing required attribution. |
| AC-SEC-005 | Payment, refund, renewal, payout and webhook replay tests produce exactly one external or ledger effect. |
| AC-SEC-006 | MFA, lockout, reset, invitation and impersonation flows pass policy tests. |
| AC-SEC-007 | Critical and high dependency findings are resolved or formally accepted before release. |
| AC-SEC-008 | A pen-test report closes all critical and high findings before MVP GA. |

---

## 13. Assumptions & Open Questions

1. Secret-provider selection, custom-domain verification and data residency must follow
   decisions in `22-open-questions-and-assumptions.md`.
2. Session, invitation and password-reset durations are security-policy settings and
   require operations approval.
3. Exact bot-control provider selection is deferred, while rate limiting and required
   server-side enforcement remain P0.
4. MFA recovery, impersonation expiry and privileged role assignment remain
   double-attributed and auditable.

## Ratified P0 decisions (2026-11-20)

Decisions below are ratified in `22-open-questions-and-assumptions.md` §5; see that
section for ratifier, date and launch-gate caveats.

| OQ-010 | Chargeback evidence pack, pass-through fee and negative ledger clawback are in scope; track chargeback rate weekly. |
| OQ-027 | KYC artifacts restricted to Platform/Finance Admin; every view audit-logged. |
| OQ-028 | Legal disclosure text is platform-owned, versioned and non-editable by tenants. |
| OQ-029 | DPDP roles: reseller fiduciary / platform processor for reseller customer data; platform DPA at approval; consent notices and breach playbook. Counsel confirm before GA. |
| OQ-031 | AUP enumeration, risk-based pre-moderation, enforcement ladder with reason codes, audit logging and abuse-report channel. |
