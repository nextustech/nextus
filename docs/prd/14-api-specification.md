# API Specification — REST Conventions, Endpoint Catalog, Webhooks, API Keys & Integrations

| Field | Value |
|---|---|
| Document | 14-api-specification.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-01 |
| Owner | Product & Architecture Team |
| Related documents | 03-functional-requirements.md, 06-product-marketplace.md, 07-billing-and-financial-architecture.md, 09-fulfillment-and-licensing.md, 11-ai-and-automation.md, 12-crm-and-support.md, 13-database-design.md, 17-security-requirements.md |

## Table of contents

1. Scope, Relationship to FR-API & Document Role
2. Global Conventions
3. Authentication & Authorization
4. Error Envelope & HTTP Status Mapping
5. Rate Limiting
6. API Versioning & Deprecation Policy
7. Endpoint Catalog
   - 7.1 Public Storefront API
   - 7.2 Inbound Gateway & Channel Webhooks
   - 7.3 License Validation API
   - 7.4 Customer Portal API
   - 7.5 Reseller API
   - 7.6 Central Admin API (Internal)
   - 7.7 AI API
8. Outbound Webhooks (Platform → Merchant Endpoints)
9. API Keys Management
10. Integration Connections Management
11. Example Requests & Responses
12. Developer Documentation (OpenAPI 3.1)
13. Sandbox Mode
14. Assumptions & Open Questions

---

## 1. Scope, Relationship to FR-API & Document Role

This document is the **API contract** for Frenchies: conventions, authentication, the full
endpoint catalog, webhook contracts (inbound and outbound), API-key lifecycle, integration
connection management, examples, and documentation/sandbox requirements.

**FR-API ownership:** functional requirement IDs with the `FR-API` prefix are **defined
exclusively in `03-functional-requirements.md`**. This document does **not** define FR-API
IDs. Each endpoint group below maps to an FR-API domain defined there; during final
assembly, concrete FR-API IDs from `03-functional-requirements.md` are referenced in the
"FR-API ref" columns without redefinition:

| Endpoint group (§7) | FR-API domain (defined in `03-functional-requirements.md`) |
|---|---|
| 7.1 Public storefront | FR-API: storefront catalog, cart/checkout, demo requests |
| 7.2 Inbound webhooks | FR-API: gateway webhook receivers (with `FR-PAYMENT`, doc 07) |
| 7.3 License validation | FR-API: license validation endpoint (with `FR-LICENSE`, doc 09) |
| 7.4 Customer portal | FR-API: portal auth, orders, invoices, subscriptions, tickets |
| 7.5 Reseller API | FR-API: reseller CRUD, pricing overrides, wallet/payout reads, staff, CMS |
| 7.6 Central admin API | FR-API: tenant approvals, distribution grants, payments/refunds, payouts, platform settings |
| 7.7 AI API | FR-AI endpoints (defined in `11-ai-and-automation.md`; contract here) |
| §8–§10 (webhooks, keys, integrations) | FR-API: outbound webhooks, API keys, integration connections |

**Consumers:** reseller server-side integrations, customer-portal SPA, storefront
BFF (Inertia server layer), the embeddable chat widget, central-admin SPA, payment
gateways, n8n (via outbound webhooks), and platform-internal jobs.

---

## 2. Global Conventions

- **Base URL:** `https://api.frenchies.com/api/v1` (production). Storefront/portal BFF
  calls may use the tenant domain with the same path (`https://{tenant}.frenchies.com/api/v1/...`).
- **Media type:** JSON only. Requests with bodies must send
  `Content-Type: application/json`; others receive HTTP 415. Responses are
  `application/json; charset=utf-8` except SSE streams (§7.7) and PDF downloads (302 to
  signed S3 URL).
- **Field naming:** **snake_case** everywhere (matches MySQL schema and Laravel JSON
  casting). Examples: `order_id`, `created_at`, `per_page`.
- **Identifiers:** resource IDs are prefixed ULID strings (`ord_01J…`, `prod_01J…`,
  `conv_01J…`) returned in `id` fields; raw DB auto-increment IDs are never exposed on
  public/storefront surfaces.
- **Dates/times:** RFC 3339 UTC timestamps (`2026-11-01T09:30:00Z`) unless a field name
  ends with `_date` (date-only, `YYYY-MM-DD`).
- **Money:** `{ "amount": "14999.00", "currency": "INR" }` — string decimal, minor-unit-free
  `DECIMAL(15,2)` representation per charter §7.11; `currency` defaults to `INR`.
- **Success envelope:** single resources: `{ "data": { ... } }`; collections:
  `{ "data": [ ... ], "meta": { ... } }`. `meta` always includes `request_id`.
- **Partial content:** list endpoints project fields; full resources are returned by
  detail endpoints only.

---

## 3. Authentication & Authorization

| Surface | Mechanism | Notes |
|---|---|---|
| Machine / reseller / admin API | `Authorization: Bearer <token>` — Laravel Sanctum personal access tokens | Token abilities = API-key scopes (§9); spatie role/permission checks applied on top for user tokens |
| Customer portal SPA | Sanctum token issued by login/OTP (`Bearer`), default expiry 7 days | OTP session grants are 15-min scoped tokens with ability `portal:otp` |
| Storefront BFF (guest + customer) | First-party session cookie (Sanctum SPA mode) + CSRF token for state changes; guest carts use an `X-Cart-Token` (signed, 48 h) | BFF is the only surface allowed cookie auth |
| Chat widget | `X-Widget-Key: wk_live_…` + signed HttpOnly visitor cookie (`fv`) | Public credential; tenant/agent-scoped; no data privileges beyond own conversation (FR-AI-024) |
| Inbound gateway webhooks | Provider signature verification (§7.2) | No bearer auth; authenticity from signature |
| Platform admin staff | Sanctum token + central-tenant role check (charter §5 roles) | Every admin mutation writes `audit_logs` |

**Scopes (API keys):** `catalog:read`, `catalog:write`, `orders:read`, `orders:write`,
`customers:read`, `customers:write`, `leads:read`, `leads:write`, `quotations:read`,
`quotations:write`, `ledger:read`, `payouts:read`, `tickets:read`, `tickets:write`,
`invoices:read`, `ai:conversations:read`, `ai:agents:write`, `webhooks:manage`,
`integrations:manage`, `reports:read`. Scope checks run before tenant-scope resolution;
a missing scope returns HTTP 403 with code `missing_scope`.

**Tenant resolution:** for tenant-scoped tokens the tenant is bound at token creation
(tenant context embedded in the token record, not client-supplied). Client-supplied
`tenant_id` parameters are rejected everywhere.

---

## 4. Error Envelope & HTTP Status Mapping

All non-2xx JSON responses use one envelope:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": {
    "price": ["The price field must be a number greater than 0."]
  },
  "request_id": "req_01JC8Y6M7QK2V0X5T9N4"
}
```

- `message`: human-readable summary. `code`: stable machine code (snake_case).
- `errors`: field→messages map; empty object `{}` when not a validation error.
- `request_id`: echoed on every response (also in the `X-Request-Id` header) and required
  in support reports; correlates to application logs.

| HTTP | `code` examples | When |
|---|---|---|
| 400 | `bad_request` | Malformed JSON, invalid query syntax |
| 401 | `unauthenticated` | Missing/invalid/expired token |
| 403 | `forbidden`, `missing_scope`, `tenant_scope_denied` | Authenticated but not permitted |
| 404 | `not_found` | Resource absent **or** outside caller's tenant scope (deliberately indistinguishable, FR-AI-070 / FR-TENANT policy) |
| 405 | `method_not_allowed` | Unsupported method on known path |
| 409 | `conflict`, `idempotency_conflict`, `state_conflict` | Duplicate slug/email; Idempotency-Key reuse with different payload; illegal state transition |
| 410 | `gone` | Conversation closed, entity purged |
| 415 | `unsupported_media_type` | Non-JSON body on JSON endpoints |
| 422 | `validation_failed`, `model_not_in_catalogue` | Business-rule validation |
| 429 | `rate_limited` | Quota exceeded (see §5) |
| 500 | `server_error` | Unhandled failure — body contains only `message`, `code`, `request_id` (never stack traces) |
| 503 | `provider_unavailable` | AI provider outage / maintenance mode |

---

## 5. Rate Limiting

| Tier | Default limit | Applies to |
|---|---|---|
| Public / storefront | 60 req/min per IP + fingerprint | §7.1, widget AI conversations |
| Storefront BFF authenticated | 120 req/min per session | cookie-authenticated BFF calls |
| Authenticated (portal, reseller, admin, API keys) | 300 req/min per token | §7.4–§7.6 |
| License validation | 120 req/min per API key | §7.3 |

Limits are configurable per API-key tier (§9) and per tenant in `platform_settings`.
Limiter: Redis token bucket (Horizon-compatible middleware). Every response carries:

```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 297
X-RateLimit-Reset: 1762000060
```

429 body adds `Retry-After` (seconds) and uses the standard envelope with
`code = "rate_limited"`:

```json
{
  "message": "Too many requests.",
  "code": "rate_limited",
  "errors": {},
  "request_id": "req_01JC8Z1H3PP8Q0",
  "retry_after": 22
}
```

Burst policy: sustained-rate enforcement over 10-second windows; write endpoints
(POST/PATCH/DELETE) share a secondary per-token budget of 60 writes/min.

---

## 6. API Versioning & Deprecation Policy

- Version is in the path: `/api/v1`. Within a version, changes are **additive only** (new
  endpoints, new optional request fields, new response fields). Removing/renaming fields,
  changing types, or tightening validation = new major version (`/api/v2`).
- Deprecation signaling: deprecated endpoints/methods return
  `Deprecation: true` (or an HTTP-date) and `Sunset: <HTTP-date>` headers plus a
  `Link: <https://developers.frenchies.com/deprecations>; rel="deprecation-policy"` header.
- Minimum support window: **180 days** between `Deprecation` announcement and `Sunset`,
  announced via developer-dashboard notice and email to key owners.
- Sunset responses after the date return HTTP 410 with code `deprecated_version`.
- A machine-readable changelog (`https://developers.frenchies.com/changelog.json`) and a
  diff report per release are published; breaking changes require opt-in migration guides.

---

## 7. Endpoint Catalog

Notation: params listed as `name (type, in)`. All paths relative to `/api/v1`. "Auth"
column shows the minimum: `public`, `widget`, `session` (BFF cookie), `portal token`,
`reseller token/key + scope`, `admin role`, `signature` (provider webhook).

### 7.1 Public Storefront API

| Method | Path | Auth | Description | Key params |
|---|---|---|---|---|
| GET | `/storefront/catalog/categories` | public | Category tree for the tenant storefront | — |
| GET | `/storefront/catalog/products` | public | Paginated published products (own + granted) | `filter[category_id]`, `filter[price_min]`, `filter[price_max]`, `q`, `sort` (`price`, `-created_at`), `page`, `per_page` |
| GET | `/storefront/catalog/products/{product}` | public | Product detail incl. variants, media, demo flag | — |
| GET | `/storefront/catalog/products/{product}/demos` | public | Active demo entries (`product_demos`) | — |
| POST | `/storefront/demo-requests` | public | Create demo request (leads/CRM sink per doc 06) | `product_id`, `name`, `email`, `phone?`, `message?` |
| POST | `/storefront/cart` | session | Create cart + `X-Cart-Token` for guests | `coupon_code?`, `affiliate_code?` |
| GET | `/storefront/cart` | session | Read cart with totals (tax lines per doc 07) | — |
| POST | `/storefront/cart/items` | session | Add item | `product_id`, `variant_id?`, `quantity` |
| PATCH | `/storefront/cart/items/{item}` | session | Update quantity | `quantity` |
| DELETE | `/storefront/cart/items/{item}` | session | Remove item | — |
| POST | `/storefront/coupons/validate` | session | Validate coupon → discount preview | `code` |
| POST | `/storefront/checkout` | session | Convert cart → order (`status = pending`), return payable amount | `billing_profile` (name, email, phone, GSTIN?), `return_url` |
| GET | `/storefront/checkout/{order}` | session | Checkout/order status polling | — |
| POST | `/storefront/checkout/{order}/payment` | session | **Create gateway payment order** (Razorpay/Cashfree); **`Idempotency-Key` required** | `gateway`, `method?` (upi/card/netbanking), `callback_url?` |
| POST | `/storefront/checkout/{order}/verify` | session | **Verify payment signature** after gateway callback; **`Idempotency-Key` required** | `gateway`, signature fields from gateway client SDK |

### 7.2 Inbound Gateway & Channel Webhooks

| Method | Path | Auth | Description | Key params |
|---|---|---|---|---|
| POST | `/webhooks/razorpay` | signature | Razorpay events (payment captured/failed, refund). Verify `X-Razorpay-Signature` = HMAC-SHA256 of raw body with webhook secret. Idempotent via `payment_webhook_events` (gateway + event id unique, charter §7.10) | raw body |
| POST | `/webhooks/cashfree` | signature | Cashfree events; verify `x-webhook-signature` (base64 RSA-SHA256 of `x-webhook-timestamp + "." + raw body` with Cashfree public key) and reject if timestamp skew > 5 min | raw body |
| GET/POST | `/webhooks/whatsapp` | signature/verify-token | WhatsApp Cloud API webhook: GET performs `hub.challenge` verification with the tenant's verify token; POST receives inbound message events (per `integration_connections`) | `hub.mode`, `hub.verify_token`, `hub.challenge` |

All receivers return HTTP 200 fast (< 5 s); heavy processing (commissions, wallets,
notifications) is queued. Raw body must be available for signature verification — request
caching/proxy buffering disabled for these paths.

### 7.3 License Validation API

| Method | Path | Auth | Description | Key params |
|---|---|---|---|---|
| POST | `/licenses/validate` | API key + `licenses:validate` (120/min tier) | Validate a license key for software activation/entitlement checks | `license_key`, `product_code`, `instance_hash?`, `version?` |
| POST | `/licenses/activate` | API key | Activate an installation against the license's activation limit | `license_key`, `instance_hash` |
| POST | `/licenses/deactivate` | API key | Release an activation slot | `license_key`, `instance_hash` |

### 7.4 Customer Portal API

| Method | Path | Auth | Description | Key params |
|---|---|---|---|---|
| POST | `/portal/auth/login` | public | Email/password login → portal token | `email`, `password` |
| POST | `/portal/auth/otp/request` | public | Send one-time login OTP (rate-limited 3/hour/email) | `email` |
| POST | `/portal/auth/otp/verify` | public | Verify OTP → short-lived portal token | `email`, `otp` |
| POST | `/portal/auth/logout` | portal token | Revoke current token | — |
| GET | `/portal/profile` | portal token | Customer profile | — |
| PATCH | `/portal/profile` | portal token | Update profile | `name?`, `phone?`, `gstin?`, `password?` |
| GET | `/portal/orders` | portal token | Order history | `filter[status]`, `page`, `per_page` |
| GET | `/portal/orders/{order}` | portal token | Order detail + items + fulfillment state | — |
| GET | `/portal/invoices` | portal token | Invoice list | `filter[from]`, `filter[to]` |
| GET | `/portal/invoices/{invoice}/download` | portal token | PDF download — returns 302 to signed S3 URL (dompdf per charter §3) | — |
| GET | `/portal/subscriptions` | portal token | Active/past subscriptions | — |
| POST | `/portal/subscriptions/{subscription}/cancel` | portal token | Cancel at period end | `reason?` |
| POST | `/portal/subscriptions/{subscription}/upgrade` | portal token | Plan change → prorated order (payment via §7.1 flow) | `plan_id` |
| GET | `/portal/licenses` | portal token | Licenses + activations | — |
| GET | `/portal/tickets` | portal token | Support tickets | `filter[status]`, `page` |
| POST | `/portal/tickets` | portal token | Create ticket | `subject`, `body`, `department_id?`, `attachment_ids?` |
| GET | `/portal/tickets/{ticket}` | portal token | Ticket thread | — |
| POST | `/portal/tickets/{ticket}/messages` | portal token | Reply to ticket | `body`, `attachment_ids?` |
| GET | `/portal/notifications` | portal token | In-app notifications | `filter[unread]=true` |
| POST | `/portal/notifications/{notification}/read` | portal token | Mark read | — |

### 7.5 Reseller API

Tenant-scoped: reseller user token (role check) **or** API key (scope check). All rows
returned are scoped to the caller's tenant (`own + granted` semantics for products).

| Method | Path | Auth (scope) | Description | Key params |
|---|---|---|---|---|
| GET | `/reseller/profile` | token | Tenant profile + branding summary | — |
| PATCH | `/reseller/profile` | token | Update branding-visible fields (name, tagline, colors per doc 10) | `name?`, `tagline?`, `branding?` |
| GET | `/reseller/products` | `catalog:read` | Own + granted products | `filter[ownership]=own|granted|all`, `filter[status]`, `q`, `sort`, `page` |
| POST | `/reseller/products` | `catalog:write` | Create reseller-owned product | `name`, `slug`, `category_id`, `description`, `pricing{base_price, currency, price_type}`, `status` |
| GET | `/reseller/products/{product}` | `catalog:read` | Product detail | — |
| PUT | `/reseller/products/{product}` | `catalog:write` | Full update (own products only) | as create |
| DELETE | `/reseller/products/{product}` | `catalog:write` | Soft-delete own unpublished product | — |
| PUT | `/reseller/products/{product}/pricing` | `catalog:write` | Price override for granted product / set own pricing | `price_type`, `price`, `compare_at_price?` |
| GET | `/reseller/customers` | `customers:read` | List customers | `filter[status]`, `q`, `page` |
| POST | `/reseller/customers` | `customers:write` | Create customer | `name`, `email`, `phone?`, `gstin?` |
| GET/PATCH/DELETE | `/reseller/customers/{customer}` | `customers:read/write` | Read / update / soft-delete | — |
| GET | `/reseller/leads` | `leads:read` | List leads (incl. `source = chatbot`) | `filter[source]`, `filter[status]`, `filter[owner_id]`, `q`, `page` |
| POST | `/reseller/leads` | `leads:write` | Create lead | `name`, `email`, `need`, `source` |
| GET/PATCH | `/reseller/leads/{lead}` | `leads:read/write` | Lead detail / update (incl. AI recommendations, doc 11) | — |
| POST | `/reseller/leads/{lead}/activities` | `leads:write` | Log activity (call/note/chat) | `type`, `note` |
| GET | `/reseller/quotations` | `quotations:read` | List quotations | `filter[status]`, `filter[lead_id]`, `page` |
| POST | `/reseller/quotations` | `quotations:write` | Create draft quotation (incl. AI-drafted, doc 11 FR-AI-039) | `lead_id`, `items[]`, `valid_until?`, `notes?` |
| GET | `/reseller/quotations/{quotation}` | `quotations:read` | Quotation detail | — |
| PATCH | `/reseller/quotations/{quotation}` | `quotations:write` | Update draft | items/fields |
| POST | `/reseller/quotations/{quotation}/send` | `quotations:write` | Email quotation to customer | `to_email?` |
| POST | `/reseller/quotations/{quotation}/convert` | `quotations:write` | Convert to order (payment follows §7.1) | — |
| GET | `/reseller/orders` | `orders:read` | Orders where tenant is selling reseller | `filter[status]`, `filter[from]`, `filter[to]`, `sort`, cursor pagination |
| GET | `/reseller/orders/{order}` | `orders:read` | Order detail + items + payments summary | — |
| GET | `/reseller/wallet` | `ledger:read` | Derived balances (available/pending/commission) | — |
| GET | `/reseller/wallet/transactions` | `ledger:read` | Wallet projection of ledger postings | `filter[type]`, cursor pagination |
| GET | `/reseller/commissions` | `ledger:read` | Commission records by order/level | `filter[status]`, `filter[from]` |
| POST | `/reseller/payout-requests` | `payouts:write` | Request payout (min threshold per doc 07); **`Idempotency-Key` required** | `amount`, `currency`, `method` (bank/upi), `destination` |
| GET | `/reseller/payout-requests` | `payouts:read` | Payout request status history | `filter[status]`, `page` |
| GET | `/reseller/affiliates` | token | Affiliates of this tenant | `filter[status]` |
| POST | `/reseller/affiliates` | token | Invite affiliate | `name`, `email`, `commission_type`, `commission_value` |
| GET | `/reseller/affiliates/{affiliate}/performance` | token | Clicks/conversions/earnings | `filter[from]`, `filter[to]` |
| GET | `/reseller/staff` | token (owner/manager) | Staff list + roles | — |
| POST | `/reseller/staff/invitations` | token (owner/manager) | Invite employee (doc 03) | `email`, `role`, `permissions[]` |
| GET | `/reseller/cms/pages` | token | List CMS pages (doc 10) | `filter[status]` |
| POST | `/reseller/cms/pages` | token | Create page draft | `title`, `slug`, `sections[]`, `status` |
| PATCH/DELETE | `/reseller/cms/pages/{page}` | token | Update / soft-delete | — |
| POST | `/reseller/reports/export` | `reports:read` | Queue async export (orders/commissions/leads) | `type`, `format=csv|xlsx`, `filters{}` |
| GET | `/reseller/reports/exports/{export}` | `reports:read` | Export status + signed download URL | — |
| GET | `/reseller/webhooks` | `webhooks:manage` | List outbound webhook endpoints | — |
| POST | `/reseller/webhooks` | `webhooks:manage` | Register endpoint | `url`, `events[]`, `description?` |
| POST | `/reseller/webhooks/{webhook}/rotate-secret` | `webhooks:manage` | Rotate signing secret (24 h overlap) | — |
| POST | `/reseller/webhooks/{webhook}/test` | `webhooks:manage` | Send test event | `event` |
| DELETE | `/reseller/webhooks/{webhook}` | `webhooks:manage` | Disable endpoint | — |
| GET | `/reseller/webhooks/{webhook}/deliveries` | `webhooks:manage` | Delivery logs (90-day retention) | `filter[status]`, `filter[event]`, cursor pagination |
| POST | `/reseller/webhooks/deliveries/{delivery}/redeliver` | `webhooks:manage` | Manual redelivery from DLQ | — |
| GET | `/reseller/api-keys` | token (owner) | List keys (prefix, scopes, last used; secret never returned) | — |
| POST | `/reseller/api-keys` | token (owner) | Create key — full secret returned **once** | `name`, `scopes[]`, `rate_tier`, `expires_at?` |
| POST | `/reseller/api-keys/{key}/rotate` | token (owner) | Rotate with overlap window | `overlap_hours` (1–72) |
| DELETE | `/reseller/api-keys/{key}` | token (owner) | Revoke immediately | — |
| GET | `/reseller/api-keys/{key}/usage` | token (owner) | Requests/24 h, rate-limit hits, top routes | `filter[from]`, `filter[to]` |
| GET | `/reseller/integrations` | `integrations:manage` | List connections (status, health; secrets masked) | `filter[provider]` |
| POST | `/reseller/integrations` | `integrations:manage` | Create/update connection | `provider`, `name`, `credentials{}`, `settings{}` |
| POST | `/reseller/integrations/{integration}/test` | `integrations:manage` | Test action (email send / WhatsApp test / provider round-trip / n8n ping) | `action` |
| DELETE | `/reseller/integrations/{integration}` | `integrations:manage` | Remove connection | — |

### 7.6 Central Admin API (Internal)

All routes require a central-tenant staff token with the corresponding charter §5 role;
every mutation writes `audit_logs`.

| Method | Path | Auth (role) | Description | Key params |
|---|---|---|---|---|
| GET | `/admin/tenants` | Platform Admin | List tenants (platform + reseller) | `filter[type]`, `filter[status]`, `q`, `page` |
| GET | `/admin/tenants/{tenant}` | Platform Admin | Tenant detail incl. hierarchy position | — |
| POST | `/admin/tenants/{tenant}/approve` | Platform Admin | Approve reseller onboarding | `notes?` |
| POST | `/admin/tenants/{tenant}/suspend` | Super Admin | Suspend tenant (feature freeze) | `reason` |
| POST | `/admin/tenants/{tenant}/reinstate` | Super Admin | Reinstate suspended tenant | `notes?` |
| GET | `/admin/reseller-approvals` | Platform Admin | Approval queue | `filter[status]` |
| POST | `/admin/distribution-grants` | Product Manager | Grant reseller sell rights (`tenant_products`) | `tenant_id`, `product_id`, `price_overrides?`, `commission_terms?` |
| PATCH/DELETE | `/admin/distribution-grants/{grant}` | Product Manager | Amend / revoke grant | — |
| GET | `/admin/payments` | Finance Admin | Cross-tenant payment search | `filter[status]`, `filter[gateway]`, `filter[from]`, `filter[to]` |
| POST | `/admin/refunds` | Finance Admin | Initiate full/partial refund; **`Idempotency-Key` required** | `payment_id`, `amount`, `reason` |
| GET | `/admin/payout-requests` | Finance Admin | Payout approval queue | `filter[status]`, `filter[type]=reseller|affiliate` |
| POST | `/admin/payout-requests/{payout}/approve` | Finance Admin | Approve + post payout (doc 07) | `bank_ref`, `notes?` |
| POST | `/admin/payout-requests/{payout}/reject` | Finance Admin | Reject with reason | `reason` |
| GET | `/admin/platform-settings` | Super Admin | Read settings (secrets masked) | `filter[group]` |
| PUT | `/admin/platform-settings` | Super Admin | Update settings incl. AI model catalogue | `settings{}` |
| GET | `/admin/audit-logs` | Super Admin | Search audit logs | `filter[actor_id]`, `filter[action]`, `filter[from]`, cursor pagination |
| GET | `/admin/ai/usage` | Platform Admin | Cross-tenant AI usage dashboard (doc 11) | `filter[tenant_id]`, `filter[from]`, `filter[to]`, `group_by` |

### 7.7 AI API

Contract for the endpoints implemented per `11-ai-and-automation.md`.

| Method | Path | Auth | Description | Key params |
|---|---|---|---|---|
| POST | `/ai/conversations` | widget key | Start conversation (agent resolved by widget key; greeting returned) | `visitor_id?`, `page_url?`, `channel=widget|portal` |
| GET | `/ai/conversations/{conversation}` | widget key + visitor cookie / portal token | Conversation header + recent messages | `per_page` |
| POST | `/ai/conversations/{conversation}/messages` | widget key + visitor cookie / portal token | Send user message; non-stream JSON reply (tools may execute: catalog, lead capture, KB, order status, ticket) | `message`, `attachments?` |
| POST | `/ai/conversations/{conversation}/stream` | same | Same as above but **SSE stream** (`message.start`, `message.delta`, `tool.call`, `message.done`, `error` events) | `message` |
| POST | `/ai/conversations/{conversation}/otp` | widget key | Request one-time OTP for order-status identification (FR-AI-021/022) | `email` |
| POST | `/ai/conversations/{conversation}/handoff` | widget key | Trigger human handoff (ticket + transcript email; P2 Reverb ping) | `reason?` |
| GET | `/ai/conversations/{conversation}/transcript` | portal token / reseller token | Transcript download (own tenant only) | `format=txt|pdf` |
| POST | `/ai/assistant/generate` | reseller token (`ai:agents:write` for admin-scope ops; any `ai:use` role for calls) | Generic assistant generation (proposal, marketing copy, WhatsApp draft) | `type=proposal|social|email|whatsapp`, `lead_id?`, `product_ids[]?`, `brief?` |
| POST | `/ai/assistant/quotations/draft` | reseller token | Generate quotation draft (doc 11 FR-AI-039) | `lead_id`, `product_ids[]` |
| POST | `/ai/assistant/analyze` | reseller token | NL question → allowlisted metric query result | `question`, `period?` |
| GET | `/ai/assistant/digest` | reseller token | Follow-up suggestions digest (P2) | `date?` |
| GET/POST | `/ai/agents` | reseller token (`ai:manage`) | List / create agents | fields per doc 11 §6.1 |
| GET/PATCH/DELETE | `/ai/agents/{agent}` | reseller token (`ai:manage`) | Read / update / disable agent | — |
| POST | `/ai/agents/{agent}/test` | reseller token (`ai:manage`) | Test invocation (does not persist to tenant conversations; logged as test) | `message` |
| GET/POST | `/ai/knowledge-sources` | reseller token (`ai:manage`) | List / create sources (upload PDF/DOCX or website config) | `type`, `file` or `url`, `config{}` |
| POST | `/ai/knowledge-sources/{source}/reindex` | reseller token (`ai:manage`) | Trigger re-parse/embedding | — |
| DELETE | `/ai/knowledge-sources/{source}` | reseller token (`ai:manage`) | Delete source + chunks | — |
| GET | `/ai/conversations` (reseller prefix `/reseller/ai/conversations`) | reseller token | Tenant-scoped transcript review | `filter[agent_id]`, `filter[from]`, cursor |
| GET | `/ai/usage-logs` (reseller) | reseller token | Tenant usage + cap progress | `filter[from]`, `filter[to]`, `group_by=model` |
| GET | `/ai/usage-logs` (admin, §7.6) | Platform Admin | Cross-tenant usage | as above + `filter[tenant_id]` |

---

## 8. Outbound Webhooks (Platform → Merchant Endpoints)

Registered per tenant (`api_webhooks`), delivered from Horizon workers.

**Event catalog:**

| Event | Trigger | `data` summary |
|---|---|---|
| `order.paid` | Verified capture webhook processed | order id, customer, items, amount, selling reseller |
| `order.fulfilled` | Fulfillment job success (doc 09) | order id, fulfillment ids, license keys (if licensing) |
| `fulfillment.failed` | Fulfillment job failure | order id, job id, error |
| `payment.refunded` | Refund posted to ledger (doc 07) | payment id, amount, reason |
| `subscription.renewed` | Successful renewal charge | subscription id, plan, period end |
| `commission.released` | Commissions move `pending → available` | order id, beneficiary type/id, amounts |
| `payout.paid` | Finance Admin approves payout | payout id, beneficiary, amount, bank ref |
| `ticket.created` | Support ticket created (incl. chatbot-created) | ticket id, subject, source |
| `license.expired` | License expiry job | license id, product, customer |
| `lead.created` | Lead created (incl. `source = chatbot`) | lead id, source, name, email |

**Delivery request:**

```
POST {endpoint.url}
Content-Type: application/json
X-Frenchies-Event: order.paid
X-Frenchies-Delivery: del_01JC8Y...
X-Frenchies-Event-Id: evt_01JC8Y...        # stable per event — receivers deduplicate
X-Frenchies-Signature: t=1762000000,v1=9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
```

**Signature scheme (Stripe-style, HMAC-SHA256):** signed payload = `{t}.{raw_body}` where
`t` is the delivery timestamp (Unix seconds). `v1` = hex HMAC-SHA256 of that string with
the endpoint secret. Receivers must: (1) reject deliveries with `|now − t| > 300 s`
(replay window), (2) recompute and compare in constant time, (3) deduplicate on
`X-Frenchies-Event-Id`. Multiple `v1=` values may appear during secret rotation — accept
if **any** matches an active secret.

**Retry schedule:** success = HTTP 2xx within 10 s (timeouts count as failures). Retries at
+1 min, +5 min, +30 min, +2 h, +6 h (5 attempts total, exponential + jitter). After the
final failure the delivery moves to the DLQ state (`status = dead`) and appears in delivery
logs for manual redelivery. 3xx/4xx (except 408/429) are treated as permanent failures
(no retry). 429 honors `Retry-After` up to 10 min.

**Secret rotation:** per-endpoint rotation issues a second active secret with a 24 h overlap
(both valid for verification); the old secret is retired automatically. Delivery logs show
which secret version signed each delivery.

**Delivery logs (`webhook_deliveries`):** per attempt: request headers/body (secrets
redacted), response status + body (truncated 10 KB), duration, attempt number, error.
Retention 90 days, filterable via §7.5 endpoints.

---

## 9. API Keys Management

- Keys belong to a tenant (`api_keys`), created by the Reseller (owner role) or platform
  staff for a tenant. **Prefixes:** `frk_live_…` (production), `frk_test_…` (sandbox).
  Key = prefix + 32-byte random secret; the **full secret is displayed exactly once** at
  creation; only prefix + fingerprint are stored retrievable.
- **Scopes:** subset chosen at creation from the §3 catalogue; scope changes require
  creating a new key (no in-place scope edits).
- **Rotation:** `POST /reseller/api-keys/{key}/rotate` issues a new secret with a
  configurable overlap window (1–72 h) during which both secrets authenticate; the old
  secret is hard-revoked when the window ends.
- **Revocation:** immediate (`DELETE`); in-flight requests complete, cached token checks
  are busted within 60 s (Redis invalidation).
- **Rate tier per key:** `standard` (300/min), `elevated` (1000/min, platform-granted),
  `license` (120/min). Tier upgrades require platform approval (§7.6).
- **Usage dashboards + logs:** per-key request counts, status-code distribution, rate-limit
  hits, top routes over 24 h/7 d; detailed request logs (method, path, status, latency,
  request_id) retained **90 days**, accessible to the key owner for their own keys.

---

## 10. Integration Connections Management

`integration_connections` stores per-tenant (nullable `tenant_id` = platform-level)
credentials. Fields: `provider` (`whatsapp | smtp | n8n | openai | gemini | custom`),
`name`, `credentials` (JSON, Laravel encrypted cast — never returned by any API after
save; reads return masked previews, e.g., `token_****4f2a`), `settings` (non-secret:
WABA display settings, from-email, webhook URLs), `status` (`active | inactive | error`),
`last_health_check_at`, `last_error`.

Provider-specific required credential keys:

| Provider | Required credential/settings fields |
|---|---|
| `whatsapp` | `waba_id`, `phone_number_id`, `access_token` (encrypted), `verify_token`, `webhook_secret` |
| `smtp` | `host`, `port`, `encryption`, `username`, `password` (encrypted), `from_email`, `from_name` |
| `n8n` | `webhook_url`, optional `secret_header_name`/`secret_header_value` (encrypted) |
| `openai` | `api_key` (encrypted), `organization?` |
| `gemini` | `api_key` (encrypted) |

**Health checks:** a hourly Horizon job pings each active connection (provider-authenticated
1-token/1-mailbox round-trip where applicable); failures set `status = error` and notify
tenant admins (in-app only). **Test actions** (synchronous, §7.5): `email_send` (sends a
test email to the actor), `whatsapp_send` (test message to a verified number),
`provider_roundtrip` (AI providers), `n8n_ping` (fires the configured n8n webhook with a
`test` payload). Results are returned inline and written to the connection health log.

---

## 11. Example Requests & Responses

### 11.1 Reseller login (issue API/user token)

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "owner@acme.example", "password": "********" }
```

```json
{
  "data": {
    "token": "1|frs_9Qx…",
    "token_type": "Bearer",
    "expires_at": "2026-12-01T09:30:00Z",
    "user": {
      "id": "usr_01JC8T…",
      "name": "Ravi Kumar",
      "email": "owner@acme.example",
      "tenant_id": "tnt_01JC8S…",
      "tenant_slug": "acme",
      "roles": ["Reseller"]
    }
  },
  "meta": { "request_id": "req_01JC8T0A1B2C3" }
}
```

### 11.2 Create product (reseller)

```http
POST /api/v1/reseller/products
Authorization: Bearer 1|frs_9Qx…
Content-Type: application/json

{
  "name": "SEO Starter",
  "slug": "seo-starter",
  "category_id": "cat_01JC8M…",
  "description": "Monthly SEO package for local businesses.",
  "pricing": { "base_price": "14999.00", "currency": "INR", "price_type": "fixed" },
  "status": "draft"
}
```

```json
{
  "data": {
    "id": "prod_01JC8V…",
    "name": "SEO Starter",
    "slug": "seo-starter",
    "status": "draft",
    "owner_tenant_id": "tnt_01JC8S…",
    "pricing": { "base_price": "14999.00", "currency": "INR", "price_type": "fixed" },
    "created_at": "2026-11-01T09:31:12Z"
  },
  "meta": { "request_id": "req_01JC8V5D6E7F8" }
}
```

### 11.3 List orders (filtered, sorted, paginated)

```http
GET /api/v1/reseller/orders?filter[status]=paid&filter[from]=2026-10-01&sort=-created_at&page=1&per_page=20
Authorization: Bearer 1|frs_9Qx…
```

```json
{
  "data": [
    {
      "id": "ord_01JC9A…",
      "status": "paid",
      "customer": { "id": "cus_01JC9B…", "name": "Priya S.", "email": "priya@example.com" },
      "total": { "amount": "14999.00", "currency": "INR" },
      "created_at": "2026-10-28T11:02:44Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 57,
    "last_page": 3,
    "request_id": "req_01JC9C1D2E3F4"
  }
}
```

### 11.4 Create payment order (idempotent)

```http
POST /api/v1/storefront/checkout/ord_01JC9A…/payment
Content-Type: application/json
Idempotency-Key: 6f0f3a24-9c1d-4a52-9f67-2b3a4c5d6e7f

{ "gateway": "razorpay", "method": "upi" }
```

```json
{
  "data": {
    "payment": {
      "id": "pay_01JC9D…",
      "status": "created",
      "amount": { "amount": "14999.00", "currency": "INR" },
      "gateway": "razorpay",
      "gateway_order": { "id": "order_RzXyAb12CdEf", "key_id": "rzp_live_XXXX" }
    }
  },
  "meta": { "request_id": "req_01JC9D9E8F7G6" }
}
```

Replay with same `Idempotency-Key` + same body → identical response plus header
`Idempotency-Replayed: true` (24 h replay window). Same key + different body → HTTP 409
`idempotency_conflict`.

### 11.5 Gateway webhook payload (Razorpay-style sample)

```http
POST /api/v1/webhooks/razorpay
Content-Type: application/json
X-Razorpay-Signature: 6ec21a3fbd23cf07…

{
  "entity": "event",
  "id": "evt_RzXyAb12",
  "event": "payment.captured",
  "created_at": 1762000000,
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_RzXyAb12",
        "order_id": "order_RzXyAb12CdEf",
        "amount": 1499900,
        "currency": "INR",
        "status": "captured",
        "method": "upi",
        "email": "priya@example.com",
        "contact": "+9198xxxxxx11",
        "notes": { "order_ref": "ord_01JC9A…", "tenant_slug": "acme" }
      }
    }
  }
}
```

Amounts are in minor units in gateway payloads; the platform normalizes to
`DECIMAL(15,2)`. Processing is idempotent via `payment_webhook_events`
(gateway + event id unique — charter §7.10); duplicate delivery returns HTTP 200 with
`{"received": true}` and performs no state change.

### 11.6 License validate response

```http
POST /api/v1/licenses/validate
Authorization: Bearer frk_live_7a3d…
Content-Type: application/json

{ "license_key": "LIC-4F2A-9B1C-77D2", "product_code": "seo-pro", "instance_hash": "sha256:9ab…" }
```

```json
{
  "data": {
    "valid": true,
    "license": {
      "key": "LIC-4F2A-9B1C-77D2",
      "status": "active",
      "product": { "code": "seo-pro", "name": "SEO Pro" },
      "expires_at": "2027-01-31T18:30:00Z",
      "activations": { "used": 2, "limit": 3 }
    }
  },
  "meta": { "request_id": "req_01JC9E1F2G3H4" }
}
```

Invalid/expired key → HTTP 200 with `"valid": false` and a `reason`
(`not_found | expired | suspended | activation_limit`) — validation failures are business
results, not transport errors.

### 11.7 Chatbot message + SSE stream shape

```http
POST /api/v1/ai/conversations/conv_01JC9F…/messages
X-Widget-Key: wk_live_2b8e…
Content-Type: application/json

{ "message": "Do you have SEO packages and what do they cost?" }
```

```json
{
  "data": {
    "conversation_id": "conv_01JC9F…",
    "message": {
      "id": "msg_01JC9G…",
      "role": "assistant",
      "content": "Yes — our SEO Starter plan is ₹14,999 per month (tax extra). It covers keyword research, on-page fixes, and 4 monthly blogs. Want a free demo link?",
      "citations": [],
      "tools_used": ["catalog_lookup"]
    },
    "usage": { "prompt_tokens": 812, "completion_tokens": 61 }
  },
  "meta": { "request_id": "req_01JC9G7H8I9J0" }
}
```

SSE variant (`POST …/stream`, `Accept: text/event-stream`):

```
event: message.start
data: {"conversation_id":"conv_01JC9F…","message_id":"msg_01JC9H…"}

event: tool.call
data: {"tool":"catalog_lookup","status":"completed","result_count":3}

event: message.delta
data: {"delta":"Yes — our SEO Starter plan is "}

event: message.delta
data: {"delta":"₹14,999 per month (tax extra)."}

event: message.done
data: {"message_id":"msg_01JC9H…","tools_used":["catalog_lookup"],"usage":{"prompt_tokens":812,"completion_tokens":61}}
```

### 11.8 Outbound webhook delivery (platform → merchant)

```http
POST https://acme.example/hooks/frenchies
Content-Type: application/json
X-Frenchies-Event: order.paid
X-Frenchies-Delivery: del_01JC9J…
X-Frenchies-Event-Id: evt_01JC9K…
X-Frenchies-Signature: t=1762000123,v1=3a7f9c1e5b2d8f4a6c0e1b7d9f3a5c2e4d6b8a0f1c3e5d7b9a2f4c6e8d0b1a3c

{
  "id": "evt_01JC9K…",
  "type": "order.paid",
  "created_at": "2026-11-01T10:05:23Z",
  "tenant": "acme",
  "data": {
    "order": {
      "id": "ord_01JC9A…",
      "status": "paid",
      "total": { "amount": "14999.00", "currency": "INR" },
      "customer": { "id": "cus_01JC9B…", "email": "priya@example.com" },
      "items": [ { "product_id": "prod_01JC8V…", "name": "SEO Starter", "quantity": 1 } ]
    }
  }
}
```

### 11.9 Cross-cutting error examples

Validation failure (HTTP 422):

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": { "pricing.base_price": ["The base price field must be a number greater than 0."] },
  "request_id": "req_01JC9L1M2N3P4"
}
```

Cross-tenant access attempt (HTTP 404 — indistinguishable from not-found):

```json
{
  "message": "Resource not found.",
  "code": "not_found",
  "errors": {},
  "request_id": "req_01JC9Q1R2S3T4"
}
```

---

## 12. Developer Documentation (OpenAPI 3.1)

- **P1 — OpenAPI 3.1 spec as a build artifact:** the spec is generated **from code
  annotations/attributes** on controllers, form requests, and API resource classes (e.g.,
  attribute-based documentation + response/attribute macros; Spatie-style query-doc
  integration for filters). CI fails if controllers lack required annotations, and the
  generated `openapi-v1.json` is published as a versioned build artifact on every release.
  The spec must include: paths, schemas, auth schemes, scopes, rate-limit headers, error
  envelope, idempotency headers, and webhook event schemas.
- **P1 — spec diff gate:** PRs touching `routes/api.php` must include a spec diff reviewed
  for breaking changes (per §6 additive-only rule).
- **P2 — interactive docs page:** authenticated try-it-out docs (Scalar/Swagger-UI class)
  at `https://developers.frenchies.com/docs`, with "authorize" injecting the caller's
  sandbox key and per-tenant context; includes copyable examples from §11 and the webhook
  signature verification recipe in PHP/Node.

---

## 13. Sandbox Mode

- **Test keys:** every live credential has a `frk_test_` / `wk_test_` counterpart that
  operates against the sandbox environment (`https://sandbox-api.frenchies.com/api/v1`)
  with a seeded **sandbox tenant** (`slug = sandbox-demo`) containing demo catalog, orders
  in every status, subscriptions, licenses, tickets, leads, and AI conversations.
- **No real money, no real providers:** sandbox payments use gateway test modes; AI
  calls use a platform test key with strict caps (10k tokens/month/sandbox tenant);
  WhatsApp/SMTP test-send go to verified test destinations only.
- **Fake gateway events:** e2e tests trigger synthetic gateway webhooks via
  `php artisan frenchies:sandbox:webhook {event} {order_ref}` (CLI) and, on
  non-production environments only (middleware `env:local|staging`),
  `POST /api/v1/sandbox/events` (`{ "event": "payment.captured", "order_ref": "…" }`,
  authenticated with a test key, rate-limited 30/min) — both emit payloads identical in
  shape to §11.5 through the real receiver + signature path.
- **Outbound webhook testing:** sandbox endpoints accept `https://` URLs; a built-in
  request-bin endpoint (`GET /api/v1/sandbox/webhook-sink/{token}`) exposes the last 50
  deliveries with full bodies and signature verification status.
- **Reset:** `php artisan frenchies:sandbox:reset` restores the seeded state; safe to run
  any number of times; refuses to run when `APP_ENV=production`.

---

## 14. Assumptions & Open Questions

1. **FR-API ID mapping:** concrete FR-API IDs from `03-functional-requirements.md` are
   referenced by domain only (§1 table); exact one-to-one endpoint↔FR-API row mapping is
   finalized when doc 03 is assembled (documented here to avoid redefining FR-API IDs).
2. **API hostnames** (`api.frenchies.com`, `developers.frenchies.com`,
   `sandbox-api.frenchies.com`, `ai.frenchies.com`) are placeholders pending DNS/infra
   naming decisions.
3. **Minor-unit normalization:** gateway payloads (paise) vs canonical `DECIMAL(15,2)`
   rupee representation is assumed as specified in §11.5; confirm against both gateways'
   current payload specs at implementation.
4. **SMS OTP delivery** for portal/chatbot OTP flows is not specified (email OTP assumed
   for MVP); SMS provider would add another `integration_connections` provider — flagged
   for `03-functional-requirements.md` / `12-crm-and-support.md` owners.
5. **Elevated rate tier** (1000/min) assumed platform-granted; exact commercial terms tie
   into reseller subscription plans (`07-billing-and-financial-architecture.md`).
6. **Cursor pagination** adoption set for high-volume endpoints only (orders, wallet
   transactions, webhook deliveries, AI conversations, audit logs); if `03-functional-requirements.md`
   mandates cursors more broadly, this catalog expands accordingly.

## Appendix / References

- Functional requirement IDs (FR-API): `03-functional-requirements.md`
- Payment gateway details & ledger: `07-billing-and-financial-architecture.md`
- License semantics: `09-fulfillment-and-licensing.md`
- AI endpoint behaviors (FR-AI): `11-ai-and-automation.md`
- Quotation/ticket resources: `12-crm-and-support.md`
- Table schemas (`api_keys`, `api_webhooks`, `webhook_deliveries`,
  `integration_connections`, `payment_webhook_events`): `13-database-design.md`
