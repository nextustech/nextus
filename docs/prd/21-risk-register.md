# Frenchies — Risk Register

| Field | Value |
|---|---|
| Document | 21-risk-register.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-20 |
| Owner | Product & Architecture Team |
| Related documents | `20-development-roadmap.md`, `17-security-requirements.md`, `18-non-functional-requirements.md`, `19-testing-and-quality-assurance.md`, `22-open-questions-and-assumptions.md` |

## Table of Contents

1. Purpose & Scope
2. Scoring Model
3. Register
4. Risk Treatment by Phase
5. Cross-Cutting Risks
6. Assumptions & Open Questions
7. Appendix / References

---

## 1. Purpose & Scope

This document is the canonical owner of `RISK-NNN` identifiers (see `index.md`
Ownership Rules). It consolidates every risk referenced by
`20-development-roadmap.md` §4 (the per-phase **(g) Risks** lines), the Phase 1
implementation plan, and the capacity assumptions in roadmap §3.

The roadmap remains the source of *when* a risk bites (phase, task, gate); this
register is the source of *what* the risk is, its treatment and its status.
Adding a risk means adding it here first, then referencing the ID from the
roadmap phase row.

**Numbering note:** IDs run `RISK-001`–`RISK-044` with `RISK-032` unused (reserved
gap — no risk was ever assigned that ID). 43 risks are live.

---

## 2. Scoring Model

Severity and likelihood are **proposals pending risk-owner review** (product
owner + engineering lead), following the same ratification pattern as
`22-open-questions-and-assumptions.md` §5.1. Ratings below are advisory until
ratified; they do not gate any task or OQ.

| Severity | Definition |
|---|---|
| **Critical** | Cross-tenant data exposure, financial misstatement, statutory non-compliance, or total service loss |
| **High** | Material financial loss or fraud, major feature blocked, sustained performance failure |
| **Medium** | Recoverable operational disruption, contained financial error, schedule damage |
| **Low** | Cosmetic, easily reversed, or absorbed by existing controls |

| Likelihood | Definition |
|---|---|
| **Likely** | Expected to occur without mitigation |
| **Possible** | Could occur under normal conditions |
| **Unlikely** | Requires an unusual combination of events |

Exposure = Severity × Likelihood. Critical/High + Likely/Possible risks require a
named treatment in §4 before their phase exits.

---

## 3. Register

Severity/Likelihood values are **proposed (pending ratification)** — see §2.

| ID | Risk | Category | Phase(s) | Sev | Likely | Status |
|---|---|---|---|---|---|---|
| RISK-001 | Cross-tenant data leakage (hierarchy queries, catalog, tickets/leads PII — `UI-F-05` masking) | Security | 1, 2, 3, 8 | Critical | Possible | Open — treatment in §4 |
| RISK-002 | Ledger drift / commission miscalculation (mis-gated release) | Financial | 6, 7 | Critical | Possible | Open — treatment in §4 |
| RISK-003 | Webhook unreliability / gateway downtime | Technical | 5 | High | Likely | Open — treatment in §4 |
| RISK-004 | Replay / duplicate payments | Financial | 5 | Critical | Possible | Open — treatment in §4 |
| RISK-005 | Refund / chargeback abuse | Financial | 5, 6 | High | Possible | Open — treatment in §4 |
| RISK-006 | Commission fraud | Financial | 6 | High | Possible | Open — treatment in §4 |
| RISK-007 | Affiliate fraud (click/conversion manipulation) | Financial | 6 | High | Possible | Open — treatment in §4 |
| RISK-008 | Malicious reseller / white-label phishing (KYC-AUP intake, takedown hooks) | Security | 2, 4 | High | Likely | Open — treatment in §4 |
| RISK-009 | GST invoicing errors | Compliance | 5 | Critical | Possible | Open — treatment in §4 |
| RISK-010 | WhatsApp template rejection / number ban | Vendor | 9 | Medium | Possible | Open — deferred post-MVP |
| RISK-011 | AI cost runaway | Cost | 9 | High | Possible | Open — treatment in §4 |
| RISK-012 | AI provider outage / pricing change | Vendor | 9 | Medium | Possible | Open — treatment in §4 |
| RISK-013 | AI content safety incident | Security | 9 | High | Unlikely | Open — treatment in §4 |
| RISK-014 | Deferred Laravel-major upgrade defects | Technical | 1 | Medium | Possible | Open — accepted deferral (see §4.1) |
| RISK-015 | spatie teams migration on Laravel 12 | Technical | 1 | Medium | Possible | Open — treatment in §4.1 |
| RISK-016 | laravel/ui removal breaking routes | Technical | 1 | Medium | Likely | Open — treatment in §4.1 |
| RISK-017 | Single-DB scaling ceiling under real load | Technical | 10 | High | Possible | Open — treatment in §4.10 |
| RISK-018 | Index-less / uncached query performance (storefront) | Performance | 3, 4 | High | Likely | Open — treatment in §4 |
| RISK-019 | Queue backlog on order bursts delaying fulfillment | Performance | 7 | High | Possible | Open — treatment in §4 |
| RISK-020 | Key-person dependency (BE ~96% / QA ~94% capacity; support tooling coverage) | People | 1, 8 | High | Likely | Open — treatment in §4.1 / §4.8 |
| RISK-021 | Scope creep on reseller feature asks | Schedule | 2 | Medium | Likely | Open — treatment in §4.2 |
| RISK-022 | Manual payout errors / fraud | Financial | 6 | High | Possible | Open — treatment in §4.6 |
| RISK-023 | Rounding accumulation across ledger splits | Financial | 6 | Medium | Likely | Open — treatment in §4.6 |
| RISK-024 | Double-spend / negative wallet balance | Financial | 6 | Critical | Possible | Open — treatment in §4.6 |
| RISK-025 | Invoice-number concurrency collision | Financial | 5 | High | Possible | Open — treatment in §4.5 |
| RISK-026 | DNS/SSL provisioning delays (custom domains) | Operational | 4 | Medium | Likely | Open — treatment in §4.4 |
| RISK-027 | Cloudflare dependency (single vendor) | Vendor | 4 | Medium | Possible | Open — treatment in §4.4 |
| RISK-028 | S3 storage cost growth | Cost | 4 | Low | Likely | Open — treatment in §4.4 |
| RISK-029 | Backup failure discovered only at restore time | Operational | 10 | Critical | Possible | Open — treatment in §4.10 |
| RISK-030 | DPDP / data-residency non-compliance at launch | Compliance | 10 | Critical | Possible | Open — treatment in §4.10 |
| RISK-031 | Third-party SDK / media-library / partner-API breaking changes | Vendor | 3, 7 | Medium | Possible | Open — treatment in §4 |
| RISK-033 | Demo credential leakage | Security | 3 | High | Possible | Open — treatment in §4.3 |
| RISK-034 | License key leakage / abuse | Security | 7 | High | Possible | Open — treatment in §4.7 |
| RISK-035 | Reverb / realtime streaming sizing | Technical | 1, 9 | Medium | Possible | Open — treatment in §4.1 (G0.2 thresholds) |
| RISK-036 | n8n self-hosted reliability | Operational | 9 | Medium | Possible | Open — treatment in §4.9 |
| RISK-037 | Chargeback evidence gaps | Compliance | 6 | High | Possible | Open — treatment in §4.6 |
| RISK-038 | Reseller churn (early onboarding friction; post-launch) | Business | 2, 10 | High | Possible | Open — treatment in §4.2 / §4.10 |
| RISK-039 | Thin launch catalog content | Business | 3, 10 | High | Likely | Open — treatment in §4.3 / §4.10 |
| RISK-040 | Performance regression without baselines | Performance | 10 | High | Possible | Open — treatment in §4.10 |
| RISK-041 | Cyber incident / account takeover (ATO) | Security | 10 | Critical | Possible | Open — treatment in §4.10 |
| RISK-042 | Email deliverability (transactional + inbound-to-ticket) | Operational | 2, 5, 8 | High | Likely | Open — treatment in §4.8 |
| RISK-043 | Schedule slip (Phase 8 straddles MVP gate) | Schedule | 8, 10 | High | Likely | Open — treatment in §4.8 |
| RISK-044 | Merchant/payment onboarding delay | Business | 5 | Medium | Possible | Open — treatment in §4.5 |

---

## 4. Risk Treatment by Phase

Treatments are drawn from `20-development-roadmap.md` §4 task rows and
`docs/plans/phase-1-implementation-plan.md`. Where the roadmap names a control,
it is cited; where no control exists yet, the gap is stated explicitly.

### 4.1 Phase 1 — Foundation and multi-tenancy

| ID | Treatment / control |
|---|---|
| RISK-014 | **Accepted deferral:** Laravel-major upgrade defects are out of scope for Phase 1 (baseline is Laravel 12). Revisit at the post-MVP maintenance window. Note: local T1.1 upgrade work is paused pending a PHP ≥ 8.3 runtime — see `docs/plans/t1.1-upgrade-log.md`. |
| RISK-015 | Pin spatie teams to a Laravel-12-compatible version; canonical 12-role / 136-permission seed rewrite lands in **T1.3** (replacing the 5-role demo seeder). |
| RISK-016 | Route parity check in **T1.2** (laravel/ui removal): diff route inventory pre/post Inertia migration. |
| RISK-001 | Negative isolation tests + tenancy scope audit (Phase 1 plan: G5-adjacent checks; regression suites owned by `19-testing-and-quality-assurance.md` §5). |
| RISK-035 | Reverb/realtime sizing thresholds defined at gate **G0.2**; split/upgrade decision follows OQ-026 measured-threshold rule. |
| RISK-020 | Pair programming on tenancy core; mitigation per roadmap §3: FE assists with test-automation authoring, DevOps absorbs CI/perf-test ops to relieve BE/QA capacity saturation. |

### 4.2 Phase 2 — Reseller management

| ID | Treatment / control |
|---|---|
| RISK-008 | KYC/AUP intake begins at reseller onboarding; acceptable-use + takedown hooks (also enforced on white-label surfaces, §4.4). |
| RISK-001 | Hierarchy-query isolation tests (closure-table scoping) per `05-reseller-hierarchy.md`. |
| RISK-038 | Onboarding UX treated as churn control — design-partner feedback loop during closed beta. |
| RISK-042 | Email deliverability monitoring starts with reseller invitation flows (see §5). |
| RISK-021 | Scope control: reseller feature asks route through the gate register (`20-development-roadmap.md` §10) rather than into the phase scope. |

### 4.3 Phase 3 — Products and demos

| ID | Treatment / control |
|---|---|
| RISK-033 | Demo credentials are per-tenant scoped and rotatable; demo access is a `FR-DEMO` acceptance rule. |
| RISK-001 | Catalog tenancy checks (`tenant_products` distribution authorizations) — no cross-tenant catalog visibility. |
| RISK-039 | Launch catalog content plan is a Phase 10 exit criterion (§4.10); content production starts before Phase 3 exit. |
| RISK-031 | Third-party SDK/media-library changes absorbed via version pinning + abstraction boundary; partner API changes tracked in §4.7. |
| RISK-018 | Index coverage review for catalog/storefront queries; query plans captured as performance baselines (feeds RISK-040). |

### 4.4 Phase 4 — White-label storefront and CMS

| ID | Treatment / control |
|---|---|
| RISK-026 | DNS/SSL provisioning SLA published to resellers; Cloudflare API retries + clear failure states (custom-domain flow, `OQ-020` ratified 2026-11-20 unblocks T4.2). |
| RISK-027 | Cloudflare dependency documented as single-vendor; mitigation is runbook + exportable config (no multi-CDN in MVP scope). |
| RISK-028 | S3 lifecycle policies + per-tenant storage metrics reviewed at gate; alert on cost trend. |
| RISK-008 | Acceptable-use + takedown hooks on white-label surfaces (extends §4.2). |
| RISK-018 | Uncached storefront query performance — full-page/edge caching rules with measured thresholds. |

### 4.5 Phase 5 — Orders and payments

| ID | Treatment / control |
|---|---|
| RISK-003 | Webhook idempotency + retry/backoff + gateway health monitoring (`17-security-requirements.md` webhook controls). |
| RISK-004 | Idempotency keys on payment capture; replay window enforcement (test family in `19-testing-and-quality-assurance.md` §7). |
| RISK-044 | Merchant onboarding checklist + sandbox-first integration path. |
| RISK-009 | GST invoicing validated against `07-billing-and-financial-architecture.md` rules; invoice tests are P0 in the suite catalogue. |
| RISK-025 | Invoice-number generation under DB-level concurrency control (unique constraint + sequence allocation). |
| RISK-005 | Refund/chargeback abuse controls: velocity limits, evidence capture (extends §4.6). |
| RISK-042 | Transactional email deliverability (order/receipt mail) — see §5. |

### 4.6 Phase 6 — Accounting and commissions

| ID | Treatment / control |
|---|---|
| RISK-002 | Double-entry ledger invariants; reconciliation job + drift alerting; commission release gated on ledger state. |
| RISK-006 | Commission fraud: hold periods, anomaly flags, manual review queue (`08-affiliate-and-commission-system.md`). |
| RISK-007 | Affiliate fraud: click-velocity, conversion-rate anomaly (>3σ / >90%), bot-filter, self-referral detection → severity-scored flags. |
| RISK-022 | Manual payouts require maker-checker approval + payout reconciliation. |
| RISK-023 | Rounding policy fixed at ledger-entry level; accumulation monitored via reconciliation. |
| RISK-024 | Wallet balance constrained non-negative at DB level; double-spend prevented by row-level locking + idempotent debit. |
| RISK-037 | Chargeback evidence captured at order time (not retroactively) — evidence pack is a fulfillment-adjacent artifact. |
| RISK-005 | Refund clawback reverses commissions (see §4.5). |

### 4.7 Phase 7 — Fulfillment and licensing

| ID | Treatment / control |
|---|---|
| RISK-034 | License keys hashed/rotated; activation limits enforced (`09-fulfillment-and-licensing.md`). |
| RISK-019 | Queue depth alerting + horizontal worker scaling; fulfillment SLA monitored against order-burst synthetic load. |
| RISK-031 | Partner API contract tests; versioned integration surface. |
| RISK-002 | Commission release gating re-verified end-to-end at fulfillment completion. |

### 4.8 Phase 8 — CRM and support

| ID | Treatment / control |
|---|---|
| RISK-042 | Inbound email-to-ticket parsing failure has a fallback (manual ticket intake); deliverability monitored as a launch blocker for ticket creation. |
| RISK-020 | Support tooling coverage: no single-person ownership of support runbooks (pairs with §4.1 capacity mitigation). |
| RISK-043 | Phase 8 straddles the MVP gate — schedule slip tracked in the gate register (`20-development-roadmap.md` §10); MVP includes the support-core slice per charter §10. |
| RISK-001 | PII masking rules `UI-F-05` enforced on tickets/leads; cross-tenant ticket visibility tests. |

### 4.9 Phase 9 — AI and automation

| ID | Treatment / control |
|---|---|
| RISK-011 | Per-tenant AI usage caps + usage-based billing (`11-ai-and-automation.md`); spend alerts at configurable thresholds. |
| RISK-012 | Provider abstraction allows OpenAI ↔ Gemini swap; no single-provider lock-in in the agent layer. |
| RISK-013 | Content-safety filtering + human review queue for generated content; `ai_enabled` flag as global kill-switch. |
| RISK-010 | WhatsApp template pre-approval workflow; email + in-app remain the MVP notification path (P1.5, see roadmap §12 A-6). |
| RISK-036 | n8n self-hosted: health checks + restart policy; workflows degrade to manual path on outage. |
| RISK-035 | Reverb/streaming scale re-evaluated against G0.2 thresholds (carried from §4.1). |

### 4.10 Phase 10 — Testing and launch

| ID | Treatment / control |
|---|---|
| RISK-029 | Backup restore drill: staging snapshot ≤ 60 min restore, RPO ≤ 15 min (roadmap §4.10 exit criterion; NFR §7). |
| RISK-030 | DPDP/data-residency checklist signed before GA; residency is a launch gate (`OQ-032` process, counsel-dependent). |
| RISK-017 | Single-DB scaling ceiling validated by load test against capacity assumptions (`18-non-functional-requirements.md` §11). |
| RISK-040 | Performance baselines captured in Phase 1 (G0.2) and re-run at launch; regression fails the release gate. |
| RISK-041 | Incident/ATO response: runbooks, MFA/ATO controls (`17-security-requirements.md`), post-incident review ≤ 48 h (roadmap §9). |
| RISK-038 / RISK-039 | Closed-beta feedback + catalog content completion are MVP-GA exit criteria (roadmap §4.10). |
| RISK-043 | Schedule slip: any phase-start slip moves dependent phases 1:1 (roadmap §4.1 rule 4) — quality is not compressed. |

---

## 5. Cross-Cutting Risks

| ID | Why it recurs across phases | Standing treatment |
|---|---|---|
| RISK-001 | Named in phases 1, 2, 3, 8 — tenancy isolation is the product's core invariant | Isolation is a permanent regression suite (`19-testing-and-quality-assurance.md` §5) and a G5 gate item; every new domain adds negative tests before its phase exits |
| RISK-042 | Named in phases 2, 5, 8 — email is the connective tissue for invites, receipts and tickets | Single deliverability dashboard; OQ-025 (email provider) must be decided before Phase 2 T2.6 |
| RISK-008 | Named in phases 2, 4 — phishing risk exists wherever a tenant-controlled surface is public | KYC/AUP intake + acceptable-use + takedown hooks are one control set, applied at both entry points |
| RISK-031 | Named in phases 3, 7 — external API/SDK churn | Version pinning + contract tests + abstraction boundaries at every integration |
| RISK-020 | Named in phases 1, 8 — capacity saturation (BE ~96%, QA ~94%) and single-person coverage | Pairing, FE test-automation assist, DevOps absorbs CI/perf ops; no single-owner runbooks |
| RISK-005 | Named in phases 5, 6 — refund flows hit both payments and commissions | Refund clawback reverses commissions; shared test family across both phases |

---

## 6. Assumptions & Open Questions

1. **[ASSUMPTION]** Severity/likelihood ratings in §3 are proposals. They require
   ratification by the product owner + engineering lead before being treated as
   authoritative; until then they are advisory only (same governance pattern as
   `22-open-questions-and-assumptions.md` §5.1).
2. **[ASSUMPTION]** Phase attribution in §3 comes from where the risk is named in
   `20-development-roadmap.md` §4. A risk named in one phase may still manifest
   earlier; §5 lists the standing controls for the recurring ones.
3. **Open question (local):** should risk review be a standing agenda item at each
   phase gate (`20-development-roadmap.md` §10)? Currently no review cadence is
   specified in the PRD. Recommendation: add a register review to every gate
   checklist when the gate process is first executed (Phase 1 exit).
4. **Open question (local):** `RISK-032` is an unused reserved ID. Either assign it
   or formally retire it at the first register review to keep the numbering
   audit-clean.

---

## 7. Appendix / References

- Source of phase/task/gate context: `20-development-roadmap.md` §4 per-phase
  **(g) Risks** lines and §9 Release Strategy.
- Phase 1 mitigations: `docs/plans/phase-1-implementation-plan.md` (risk line for
  T1.x tasks).
- Security controls: `17-security-requirements.md` (SEC-NNN).
- NFR/backup/DR: `18-non-functional-requirements.md` §3/§6/§7 (NFR-NNN).
- Test families backing treatments: `19-testing-and-quality-assurance.md`
  (TEST-NNN).
- Decision dependencies: `22-open-questions-and-assumptions.md` (OQ-020 custom
  domains, OQ-025 email provider, OQ-026 Redis topology, OQ-032 residency).
- ID ownership: `index.md` Ownership Rules — `RISK-*` belongs to this document.

## Ratified P0 decisions (2026-11-20)

P0 OQs affecting risk treatment were ratified 2026-11-20 (see
`22-open-questions-and-assumptions.md` §5.1): OQ-020 unblocks Phase 4 custom-domain
work (RISK-026/027 treatment), OQ-022/OQ-026 fix the tenancy layer and Redis
topology underpinning RISK-001 and RISK-035. Remaining risk ratings (§3 severity/
likelihood) are **not yet ratified** and are pending the review in §6.
