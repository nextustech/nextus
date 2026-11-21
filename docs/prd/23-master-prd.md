# Frenchies — Consolidated Master PRD

| Field | Value |
|---|---|
| Document | 23-master-prd.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Product & Architecture Team |
| Related documents | All PRD documents, especially `00-product-overview.md`, `01-business-requirements.md`, `03-functional-requirements.md`, `13-database-design.md`, `20-development-roadmap.md` and `22-open-questions-and-assumptions.md` |

## Table of Contents

1. Executive Summary
2. Product Vision
3. Goals and Success Metrics
4. Personas and Roles
5. Product Scope
6. System Architecture
7. Functional Requirement Index
8. Security and Non-Functional Requirements
9. User and Release Journeys
10. MVP Scope Boundary
11. Delivery Plan
12. Decision Log
13. Traceability
14. Open Risks and Decisions
15. PRD Governance

---

## 1. Executive Summary

Frenchies is an India-first, white-label, multi-tenant SaaS platform for resellers,
sub-resellers, affiliates and customers that sells digital products and services.
The Central Platform owns the shared infrastructure, catalog supply, payment
collection, GST-oriented financial records, commissions, fulfillment and compliance
substrate. Each reseller owns its customer relationship, branding, domain, catalog
selection, pricing, support, staff and distribution network.

The product is a modular Laravel monolith using shared-schema tenant isolation,
team-scoped RBAC and exact financial ledgers. The MVP establishes reseller onboarding,
tenant isolation, storefront and catalog publication, checkout and payments,
subscriptions, refunds, commissions, payouts, fulfillment/licensing and support
ticketing. AI, WhatsApp and broad post-MVP automation follow the MVP gate.

## 2. Product Vision

Any qualified seller in India shall be able to launch a credible own-brand
digital-services business in under one day, with storefront, supply catalog,
GST-ready billing, payments, fulfillment, support and reseller economics provided
by the Central Platform.

### Core Principles

- Own brand for the reseller; Central Platform infrastructure underneath
- Tenant isolation as a default, enforced in authorization and data access
- Exact money calculations and replay-safe financial effects
- Unlimited-depth hierarchy within defined visibility and commission rules
- Server-calculated checkout and entitlement truth
- Permission-visible UX backed by server-side authorization
- Auditability for authentication, lifecycle, financial and administrative changes

## 3. Goals and Success Metrics

The measurable product goals are defined in `01-business-requirements.md`.

| Goal | Required outcome | Key metrics |
|---|---|---|
| G1 — Reseller channel | Prove adoption and activation | 100 active resellers, 60% activation, TTFS ≤ 14 days, monthly churn ≤ 5% |
| G2 — Transaction volume | Grow paid digital-product GMV | ₹5 Cr cumulative GMV, 90% paid-order rate, 25% repeat purchase, ≤ 2% refund rate |
| G3 — Financial exactness | Preserve exact and reconciled money | 100% ledger reconciliation, zero replay defects, 95% payout SLA, 99.9% commission accuracy |
| G4 — Supportability | Operate reliable tickets and support | 90% first-response SLA, CSAT ≥ 4.2, zero tenant cross-leaks |

Targets are draft business targets until approved by product and finance owners.
The M1–M4 definitions and 12-month measurement rules remain owned by
`01-business-requirements.md`.

## 4. Personas and Roles

Frenchies uses the following canonical 12-role model.

| Role slug | Area | Primary responsibility |
|---|---|---|
| `platform.super_admin` | Central | Global settings, roles, suspensions, overrides |
| `platform.admin` | Central | Reseller operations, catalog, marketing, support |
| `platform.finance_admin` | Central | Payments, refunds, reconciliation, payouts |
| `platform.product_manager` | Central | Products, variants, demos and distribution |
| `platform.support_manager` | Central/Reseller | Queues, SLAs, escalation and reports |
| `platform.support_agent` | Central/Reseller | Ticket resolution and handoff |
| `reseller.owner` | Reseller tenant | Store, staff, catalog, hierarchy and finances |
| `reseller.sub_reseller` | Reseller tenant | Own store and downline within hierarchy rules |
| `reseller.employee` | Reseller tenant | Assigned operational work |
| `reseller.sales_agent` | Reseller tenant | CRM, quotations and lead conversion |
| `affiliate.partner` | Affiliate | Links, coupons, conversions and payouts |
| `customer.buyer` | Customer | Purchase, subscriptions, licenses and support |

The role matrix and 136 permission slugs are owned by
`02-user-roles-and-permissions.md`. Team assignments and row ownership are tenant-bound
unless explicitly granted for cross-tenant platform work.

## 5. Product Scope

### 5.1 In Scope

- Shared-schema multi-tenancy with subdomains and custom domains
- Reseller onboarding, KYC, approval, suspension and hierarchy
- Central and reseller product catalogs, variants, demos and pricing
- White-label storefront, CMS, branding and customer portal
- Cart, checkout, Razorpay/Cashfree capture, invoices and offline payments
- Subscriptions, renewals, grace periods, upgrades and cancellation
- Double-entry ledger, commissions, refunds, clawbacks and payouts
- Fulfillment jobs and license activation
- Leads, quotations, support departments, tickets, SLA and CSAT
- Affiliate attribution and coupons
- Notifications, audit logs and tenant-scoped reporting
- AI and automation after MVP gate

### 5.2 MVP Boundary

The MVP is the sellable platform through payments, commissions, fulfillment/licensing
and the support core. It includes Phases 1–7 and the support subset of Phase 8.

Out of MVP scope unless separately funded or explicitly moved forward:

- Full post-MVP AI assistant and chatbot program
- WhatsApp Cloud API messaging
- n8n automation as a customer-facing GA dependency
- Multi-currency and broad international tax support
- Advanced review, coupon stacking and configurable multi-pipeline CRM behavior
- International tax residency expansion

## 6. System Architecture

The target architecture is a modular Laravel monolith:

- Laravel 12 and PHP 8.2+ implementation baseline
- Vue 3, Inertia 2, TypeScript and Tailwind CSS 4
- Shared MySQL 8 database with tenant row isolation
- Redis, Horizon and Reverb
- Spatie permission teams set to tenant context
- Laravel Sanctum session and API authentication
- Stripe-style idempotency for money and external state effects is not used; stable
  platform records and provider event IDs provide replay protection
- One application deployment, modularized by domain

Architecture ownership:

| Area | Owning document |
|---|---|
| Tenant resolution and isolation | `04-multi-tenancy-architecture.md` |
| Hierarchy | `05-reseller-hierarchy.md` |
| Database | `13-database-design.md` |
| API | `14-api-specification.md` |
| Workflows | `15-user-workflows.md` |
| UI | `16-ui-ux-specification.md` |
| Security | `17-security-requirements.md` |
| Operations | `18-non-functional-requirements.md` |

## 7. Functional Requirement Index

The authoritative detailed requirements are in `03-functional-requirements.md` and
the domain specifications. The master index is summarized below.

| Prefix family | Domain | Reference |
|---|---|---|
| `FR-AUTH` | Login, MFA, password reset, invitations and sessions | `03-functional-requirements.md` |
| `FR-TENANT` | Tenant lifecycle, domains and tenant administration | `03`, `04-multi-tenancy-architecture.md` |
| `FR-RESELLER` | Reseller lifecycle and hierarchy | `03`, `05-reseller-hierarchy.md` |
| `FR-CUSTOMER` | Customer accounts and portal ownership | `03-functional-requirements.md` |
| `FR-ORDER` / `FR-SUBSCRIPTION` | Cart, checkout, orders and recurring entitlements | `03`, `07-billing-and-financial-architecture.md` |
| `FR-NOTIFY` | Email, in-app notifications and channel delivery | `03-functional-requirements.md` |
| `FR-MARKETING` | Promotions and campaign behavior | `03`, `10-white-label-cms.md` |
| `FR-REPORT` | Operational and financial reports | `03-functional-requirements.md` |
| `FR-API` | Sanctum/API keys and integration access | `03`, `14-api-specification.md` |
| `FR-ADMIN` | Audit, settings and platform administration | `03`, `17-security-requirements.md` |
| `FR-PRODUCT` / `FR-DEMO` | Catalog, variants, authorization and demos | `06-product-marketplace.md` |
| `FR-BILLING` / `FR-PAYMENT` / `FR-WALLET` | GST billing, payment effects and wallet-ledger integration | `07-billing-and-financial-architecture.md` |
| `FR-COMMISSION` / `FR-AFFILIATE` | Hierarchy splits, holds, clawbacks and affiliate payouts | `08-affiliate-and-commission-system.md` |
| `FR-FULFILLMENT` / `FR-LICENSE` | Fulfillment jobs and license activation | `09-fulfillment-and-licensing.md` |
| `FR-CMS` | Themes, pages, menus and tenant domains | `10-white-label-cms.md` |
| `FR-AI` | AI provider, chatbot, assistant and usage controls | `11-ai-and-automation.md` |
| `FR-CRM` / `FR-SUPPORT` | Leads, quotations, tickets, SLA and CSAT | `12-crm-and-support.md` |
| `FLOW-NN` | End-to-end persona journeys | `15-user-workflows.md` |
| `UI-A..F-NN` | Interface and screen contracts | `16-ui-ux-specification.md` |
| `SEC-NNN` | Security controls | `17-security-requirements.md` |
| `NFR-NNN` | Quality and service requirements | `18-non-functional-requirements.md` |
| `TEST-*` | Test suites and release gates | `19-testing-and-quality-assurance.md` |

## 8. Security and Non-Functional Requirements

P0 security requirements are release blockers. Core obligations include:

- Team-scoped roles and tenant global scopes
- Cross-tenant negative tests and safe 403/404 behavior
- MFA for Super Admin and Finance Admin
- Secrets stored outside source and logs
- Append-only attribution for sensitive audit events
- Signed webhook validation and replay protection
- Segregated refund, payout and financial override actions
- Idempotent ledger, commission and entitlement mutation
- PII masking and tenant-bound documents

Core service levels:

| Area | MVP target |
|---|---|
| Dashboard P95 | ≤ 500 ms |
| Storefront P95 | ≤ 800 ms |
| Checkout recalculation P95 | ≤ 800 ms |
| Realtime notification P95 | ≤ 2 seconds |
| Monthly availability | 99.9% |
| RPO | ≤ 15 minutes proposed |
| RTO | ≤ 2 hours proposed |
| Accessibility | WCAG 2.2 AA |

The detailed contracts and acceptance criteria are owned by
`17-security-requirements.md` and `18-non-functional-requirements.md`.

## 9. User and Release Journeys

The implementation must support these end-to-end flows:

1. Reseller application, KYC review, approval and activation
2. Sub-reseller creation and hierarchy relationship
3. Staff invitation and team-scoped role assignment
4. Customer discovery, cart, checkout and paid order
5. Quotation creation, acceptance and conversion
6. Subscription purchase, renewal and suspension
7. Refund approval, gateway execution and commission clawback
8. Commission release and payout disbursement
9. Affiliate link attribution and conversion
10. Lead capture, qualification and conversion
11. Ticket creation, escalation and resolution
12. White-label domain and branding setup
13. Catalog authorization, reseller pricing and publication
14. License activation and entitlement enforcement
15. Tenant suspension, resume and deactivation

Detailed steps and failure handling are owned by `15-user-workflows.md`.

## 10. MVP Scope Boundary

| MVP includes | MVP excludes or defers |
|---|---|
| Tenancy and 12-role RBAC | Post-MVP AI and provider-cost controls |
| Reseller/sub-reseller lifecycle | WhatsApp Cloud API |
| Central and reseller catalog | n8n as an MVP launch dependency |
| White-label storefront/CMS and domains | Advanced CRM pipelines and reviews |
| Checkout, payments and GST documents | Multi-currency support |
| Renewals and subscriptions | International tax expansion |
| Ledger, commissions and payouts | Advanced stacked coupon rules unless decided |
| Fulfillment and licenses | P2/P3 AI, marketing and chatbot scope |
| CRM and support core | P2/P3 operational features |

The MVP/roadmap boundary is governed by `20-development-roadmap.md`. Open product
decisions remain governed by `22-open-questions-and-assumptions.md`.

## 11. Delivery Plan

The implementation roadmap comprises 10 phases:

| Phase | Objective |
|---|---|
| 1 | Foundation, tenancy, RBAC and audit |
| 2 | Reseller management and hierarchy |
| 3 | Product catalog and demos |
| 4 | White-label storefront and CMS |
| 5 | Orders and payments |
| 6 | Accounting and commissions |
| 7 | Fulfillment and licensing |
| 8 | CRM and support |
| 9 | AI and automation |
| 10 | Testing and launch |

The roadmap estimate is approximately 844 person-days. The MVP subset is estimated
at approximately 673 person-days, with CRM/AI/launch hardening forming the remaining
scope. Task estimates are planning assumptions and must be revised at phase gates.

Release gates require:

- P0/P1 requirements complete
- Isolation and financial replay suites passing
- Build, unit, integration and E2E suites passing
- Security and accessibility acceptance passing
- UAT for critical reseller, customer and support journeys
- Recovery, alerting and operations runbooks available

## 12. Decision Log

The decision log is intentionally not duplicated here. It is owned by
`22-open-questions-and-assumptions.md`, with these rules:

1. Open decisions remain open until the named owner disposes them.
2. A decision must update the owning requirements, schema, flow and test documents.
3. P0 decisions block the dependent build phase.
4. P1 decisions block MVP GA.
5. P2/P3 decisions block only the dependent feature or rollout.

All 12 high-impact P0 decisions were ratified on 2026-11-20 by the product owner and
are now `Closed` in `22-open-questions-and-assumptions.md` §5. Full decisions, ratifiers,
dates and affected documents live there; the summary below is for traceability only.

| OQ | Ratified decision (summary) | Gate retained |
|---|---|---|
| OQ-001 | Fee mechanism in `platform_settings`, default 0%, dedicated ledger leg | Model (a)/(b) before GA |
| OQ-002 | Three paid tiers + annual option, seeded `subscription_plans`, no free tier | Exact INR prices from beta interviews |
| OQ-007 | 7-day hold; `hold_days >= refund_window_days`; fulfillment gating unchanged | — |
| OQ-008 | Min payout ₹1,000; bank + UPI; fee policy in `platform_settings` | — |
| OQ-010 | Selling reseller liable; chargeback + fee clawed back; manual evidence pack | — |
| OQ-013 | Depths 1–3 = 50/30/20; deeper 0% default; configurable table | Percentages confirmed before GA |
| OQ-014 | Per-type refund windows with per-product override | — |
| OQ-020 | CNAME + HTTP token + Cloudflare proxied edge SSL; assisted nameserver upgrade | — |
| OQ-027 | Standard KYC for go-live; minimal = create-only; strict for high-risk | Counsel confirmation before GA |
| OQ-028 | Platform-owned jurisdiction wording templates; reseller identity slots only | Counsel review before GA |
| OQ-029 | Reseller fiduciary / platform processor split; platform DPA at approval | Counsel confirmation before GA |
| OQ-031 | Enumerated prohibited categories, risk-based pre-moderation, audit-ladder | Counsel review before GA |

OQ-022 (purpose-built tenancy layer) and OQ-026 (single managed Redis with logical
separation) were ratified on 2026-11-20 from the Sprint 0 spike; see
`docs/spikes/oq-022-tenancy-spike.md` and `22-open-questions-and-assumptions.md` §5.

## 13. Traceability

| Goal | Business metric | Primary requirements | Workflows | Tests |
|---|---|---|---|---|
| G1 — Reseller channel | M1.1–M1.4 | `FR-TENANT`, `FR-RESELLER` | FLOW-02, FLOW-03 | Reseller lifecycle/UAT |
| G2 — Transaction volume | M2.1–M2.5 | `FR-ORDER`, `FR-SUBSCRIPTION`, `FR-MARKETING` | FLOW-05, FLOW-06, FLOW-07 | Checkout/payment/load |
| G3 — Financial exactness | M3.1–M3.4 | `FR-BILLING`, `FR-PAYMENT`, `FR-WALLET`, `FR-COMMISSION` | FLOW-08, FLOW-09 | Replay/ledger/payout |
| G4 — Supportability | M4.1–M4.4 | `FR-SUPPORT`, `FR-CRM`, `SEC-NNN`, `NFR-NNN` | FLOW-11, FLOW-12 | SLA/support/isolation |

Cross-reference ownership is as follows:

- Business metrics: `01-business-requirements.md`
- Functional behavior: `03-functional-requirements.md`
- Domain rules: `05–12` PRD documents
- State and person journeys: `15-user-workflows.md`
- Security and quality: `17–19` PRD documents
- Delivery: `20-development-roadmap.md`
- Master decisions: `22-open-questions-and-assumptions.md`

## 14. Open Risks and Decisions

The following areas must be resolved or explicitly time-boxed before dependent
implementation:

1. Tenancy implementation choice
2. Redis/Horizon/Reverb topology and sizing
3. Custom-domain and TLS provider
4. Email sender and notification strategy
5. Gateway and manual settlement operations
6. Commission hold periods and hierarchy participation depth
7. Payout threshold, bank methods and negative-balance policy
8. Reseller KYC and legal branding
9. Product refund windows and coupon precedence
10. Affiliate attribution model
11. Support SLA defaults

New security, financial or P0 PII risks require a named owner and decision date.

## 15. PRD Governance

- The domain document owns its requirement family.
- The master PRD summarizes and links; it does not silently override a domain document.
- `22-open-questions-and-assumptions.md` owns OQ/A decisions.
- `20-development-roadmap.md` owns execution sequence and estimates.
- `19-testing-and-quality-assurance.md` owns test-suite and release gates.
- `23-master-prd.md` owns only the consolidated executive summary and traceability.
- Conflicting documents must be reconciled at PRD review before implementation starts.
