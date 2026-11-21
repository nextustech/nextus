# Frenchies — PRD Library Index

| Field | Value |
|---|---|
| Document | index.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Product & Architecture Team |

## Purpose

This index is the entry point for the Frenchies product requirement library. It lists
the canonical documents, reading order and maintainers. Domain documents own detailed
requirements; `23-master-prd.md` provides the consolidated summary and traceability.

## Document Library

| # | Document | Description |
|---|---|---|
| 00 | [`00-product-overview.md`](00-product-overview.md) | Vision, problem, personas, principles, scope and architecture overview |
| 01 | [`01-business-requirements.md`](01-business-requirements.md) | Goals, success metrics, market, personas and business constraints |
| 02 | [`02-user-roles-and-permissions.md`](02-user-roles-and-permissions.md) | 12 canonical roles and team-scoped permission catalogue |
| 03 | [`03-functional-requirements.md`](03-functional-requirements.md) | Master FR catalogue and acceptance rules |
| 04 | [`04-multi-tenancy-architecture.md`](04-multi-tenancy-architecture.md) | Tenant resolution, context and isolation architecture |
| 05 | [`05-reseller-hierarchy.md`](05-reseller-hierarchy.md) | Closure-table hierarchy and parent/child visibility |
| 06 | [`06-product-marketplace.md`](06-product-marketplace.md) | Catalog, variants, demos and distribution authorizations |
| 07 | [`07-billing-and-financial-architecture.md`](07-billing-and-financial-architecture.md) | Billing, payments, ledger, refunds, wallets and payouts |
| 08 | [`08-affiliate-and-commission-system.md`](08-affiliate-and-commission-system.md) | Commission engine and affiliate economics |
| 09 | [`09-fulfillment-and-licensing.md`](09-fulfillment-and-licensing.md) | Fulfillment jobs and license activation |
| 10 | [`10-white-label-cms.md`](10-white-label-cms.md) | Themes, pages, menus, media and custom domains |
| 11 | [`11-ai-and-automation.md`](11-ai-and-automation.md) | AI agents, knowledge, usage and automation |
| 12 | [`12-crm-and-support.md`](12-crm-and-support.md) | CRM pipeline, quotations, tickets and support SLA |
| 13 | [`13-database-design.md`](13-database-design.md) | Canonical tables, columns, keys and constraints |
| 14 | [`14-api-specification.md`](14-api-specification.md) | REST endpoints, auth, errors and webhook contracts |
| 15 | [`15-user-workflows.md`](15-user-workflows.md) | Cross-domain end-to-end persona workflows |
| 16 | [`16-ui-ux-specification.md`](16-ui-ux-specification.md) | Screens, components, states and design tokens |
| 17 | [`17-security-requirements.md`](17-security-requirements.md) | SEC-NNN security controls and acceptance |
| 18 | [`18-non-functional-requirements.md`](18-non-functional-requirements.md) | NFR-NNN performance, availability and operations |
| 19 | [`19-testing-and-quality-assurance.md`](19-testing-and-quality-assurance.md) | Test strategy, suites, UAT and release gates |
| 20 | [`20-development-roadmap.md`](20-development-roadmap.md) | Ten-phase implementation roadmap and acceptance gates |
| 21 | [`21-risk-register.md`](21-risk-register.md) | RISK-NNN register: per-phase risk treatments and cross-cutting controls |
| 22 | [`22-open-questions-and-assumptions.md`](22-open-questions-and-assumptions.md) | OQ/A decision register and decision log |
| 23 | [`23-master-prd.md`](23-master-prd.md) | Consolidated executive summary and traceability |

## Reading Order

### Founder / Product Owner

1. `00-product-overview.md`
2. `01-business-requirements.md`
3. `23-master-prd.md`
4. `22-open-questions-and-assumptions.md`
5. `20-development-roadmap.md`

### Backend Engineer

1. `04-multi-tenancy-architecture.md`
2. `02-user-roles-and-permissions.md`
3. `03-functional-requirements.md`
4. `13-database-design.md`
5. `14-api-specification.md`
6. Domain documents `05–12`
7. `17-security-requirements.md`
8. `18-non-functional-requirements.md`

### Frontend Engineer

1. `16-ui-ux-specification.md`
2. `15-user-workflows.md`
3. `02-user-roles-and-permissions.md`
4. `14-api-specification.md`
5. `10-white-label-cms.md`
6. `12-crm-and-support.md`

### QA / Security

1. `19-testing-and-quality-assurance.md`
2. `17-security-requirements.md`
3. `18-non-functional-requirements.md`
4. `15-user-workflows.md`
5. `02-user-roles-and-permissions.md`
6. `04-multi-tenancy-architecture.md`

### DevOps / Operations

1. `18-non-functional-requirements.md`
2. `20-development-roadmap.md`
3. `17-security-requirements.md`
4. `13-database-design.md`
5. `14-api-specification.md`

## Ownership Rules

| Identifier | Owner |
|---|---|
| FR-* | `03-functional-requirements.md` plus the named domain document |
| FLOW-* | `15-user-workflows.md` |
| UI-* | `16-ui-ux-specification.md` |
| SEC-* | `17-security-requirements.md` |
| NFR-* | `18-non-functional-requirements.md` |
| TEST-* | `19-testing-and-quality-assurance.md` |
| RISK-* | `21-risk-register.md` |
| OQ-* / A-* | `22-open-questions-and-assumptions.md` |
| Phase/T* | `20-development-roadmap.md` |

The master PRD is a summary and traceability map. It must not change the definitions
owned by domain documents or close OQ/A decisions without following the governance
process in `22-open-questions-and-assumptions.md`.

## Filename and Cross-Reference Notes

Earlier drafts of this library planned `20-deployment-and-operations.md` and
`21-implementation-roadmap.md`; neither was ever written. Cross-references have been
reconciled as follows:

- Phase gates, effort and backlog content is owned by `20-development-roadmap.md`.
- Test suite ownership is `19-testing-and-quality-assurance.md` (TEST-NNN); older
  references to a `19-testing-and-acceptance-criteria.md` now point there.
- Risk IDs (`RISK-NNN`) are owned by `21-risk-register.md`. The doc-number slot 21
  was originally reserved for the unwritten `21-implementation-roadmap.md`; it now
  hosts the risk register instead, and no document references the old name.
- Deployment/operations detail (Docker/Nginx topology, CI/CD, Cloudflare config,
  Horizon/Reverb operations) is **not yet consolidated**. Its nearest sources are
  `20-development-roadmap.md` §9 Release Strategy (environments, pipeline, rollback)
  and `18-non-functional-requirements.md` §3/§6/§7 (availability, observability,
  backup/DR). Authoring `20-deployment-and-operations.md` remains a doc-library gap
  and should be closed before Phase 10 (T10.6) delivery gates are finalized.

## Version History

| Version | Date | Change |
|---|---|---|
| 1.0 | 2026-11-20 | Initial PRD library index and master PRD consolidated for review |
