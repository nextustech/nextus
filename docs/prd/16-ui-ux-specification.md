# UI/UX Specification

| Field | Value |
|---|---|
| Document | 16-ui-ux-specification.md |
| Version | 1.0 |
| Status | Draft for Review |
| Date | 2026-11-01 |
| Owner | Product & Architecture Team |
| Related documents | 02-user-roles-and-permissions.md, 03-functional-requirements.md, 10-white-label-cms.md, 12-crm-and-support.md, 15-user-workflows.md, 17-security-requirements.md, 18-non-functional-requirements.md, 22-open-questions-and-assumptions.md |

## Table of Contents

1. Purpose, Scope & Design Principles
2. Global Design System (applies to all six interfaces)
   2.1 App Shell · 2.2 Navigation · 2.3 Notification Tray (Reverb realtime) · 2.4 Global Search · 2.5 Tables/Pagination/Filters · 2.6 Forms · 2.7 Buttons/Modals/Toasts · 2.8 Empty/Loading/Error States & Error Pages · 2.9 Tokens (color/type/elevation) · 2.10 White-Label Theming Variables · 2.11 Accessibility Baseline (WCAG 2.2 AA) · 2.12 Responsive Strategy
3. Interface A — Super Admin Panel (UI-A-01…18)
4. Interface B — Reseller Dashboard (UI-B-01…20)
5. Interface C — Reseller Storefront (UI-C-01…10)
6. Interface D — Customer Portal (UI-D-01…09)
7. Interface E — Affiliate Dashboard (UI-E-01…06)
8. Interface F — Support Agent Dashboard (UI-F-01…07)
9. Screen Index
10. Assumptions & Open Questions

---

## 1. Purpose, Scope & Design Principles

This document specifies every primary screen of the six Frenchies interfaces with
implementation-ready detail (screen ID, roles, components, forms, validation,
permissions, states, responsiveness), plus the shared design system. Screen IDs
`UI-<A..F>-NN` are owned here per charter §8 and are the reference tokens used by
`15-user-workflows.md`, `03-functional-requirements.md`, and QA test plans.

Design principles (each is testable; violating one is a review defect):

1. **Permission-visible UI.** Every action button renders only when the user holds the
   slug defined in `02-user-roles-and-permissions.md`; hidden controls are backed by a
   server-side 403 (never security by hiding alone).
2. **Every state is designed.** Each screen specifies empty, loading, and error states;
   shipping a screen without the three states fails the definition of done.
3. **Server truth, URL state.** Lists reflect server-side queries; filters, sort, and
   page live in the URL so any list view is shareable and back/forward-safe.
4. **Two-click rule for frequent tasks.** Any action a role performs at least daily
   (reply ticket, open order, approve payout) is reachable within two navigations from
   the interface dashboard.
5. **Money is exact.** All amounts render with ISO currency and thousand separators
   (default `INR` ₹, `en-IN` formatting); no abbreviated "1.2k" for ledger-relevant
   figures; `DECIMAL(15,2)` values render with 2 decimals (charter §7.11).
6. **Destructive = confirmed.** Irreversible actions require typed-confirmation modals
   (§2.7).
7. **Realtime without surprise.** Live updates (Reverb) never reorder a row under the
   user's pointer mid-interaction; changed rows flash-highlight for 1.5 s instead.

## 2. Global Design System

Stack: Vue 3 + Inertia + TypeScript + Tailwind CSS 4 (charter §3). The design system
is implemented as Tailwind theme tokens + a shared component library; the interfaces
differ only in branding context and navigation composition.

### 2.1 App Shell (interfaces A, B, F; portal shell for D and E)

| Part | Specification |
|---|---|
| Layout | Fixed sidebar (256 px) + topbar (56 px) + scrollable content area (max-width 1440 px, padding 24 px; 16 px below `lg`). |
| Sidebar | Brand slot (logo: platform mark for A/F, reseller logo for B/D/E), primary nav groups with 12 px uppercase group labels, active item = brand-50 background + brand-700 text + 3 px left indicator; collapsible to a 64 px icon rail (state persisted per user in `localStorage`). |
| Topbar | Left: breadcrumb trail (§2.2). Center: global search trigger (§2.4). Right: notification bell with unread badge, help link, user menu (profile, theme preference P2, logout), and — for platform staff — tenant switcher showing current tenant name + "Switch tenant" (§7.4 of doc 02). |
| Impersonation banner | When impersonating: sticky 40 px amber-600 banner above topbar — "Impersonating <user> in <tenant> · expires in mm:ss · Stop" (doc 02 §7.4). |
| Skip link | "Skip to content" is the first tab stop on every page (WCAG 2.4.1). |
| Footer | Interface A/F: none. B: "Powered by Frenchies" unless branding setting hides it (legal constraint doc 02/§white-label). D/E: reseller branding footer + platform legal line where required. |

### 2.2 Navigation & Breadcrumbs

- Primary navigation is the sidebar; each item maps to an Inertia route and renders
  only when its permission slug resolves.
- Breadcrumbs: `Interface > Section > Record` (record = human name, ≤40 chars,
  ellipsized). Breadcrumbs are links to each ancestor; the last item is `aria-current="page"`.
- Browser tab title pattern: `<Page> · <Tenant or Platform> · Frenchies`.
- Deep links restore filter/sort/page state from the URL (§2.5).

### 2.3 Notification Tray (Laravel Reverb realtime)

- Trigger: topbar bell with numeric badge (99+ cap). Panel: 380 px wide dropdown
  (full-screen sheet below `md`), items grouped by day, each item: icon by type,
  title (≤80 chars), 2-line body, relative time, unread dot, deep link to the source
  screen (e.g., UI-B-06 order row).
- Realtime: per-user private channel `user.{id}.notifications` and per-tenant channel
  `tenant.{id}.broadcast` via Reverb. Acceptance target: an event published server-side
  appears in the tray ≤ 2 s while the socket is open; the badge increments without a
  page refresh.
- Fallback: if the socket is disconnected for > 10 s, the client polls
  `/notifications/poll` every 60 s and shows a subtle "Live updates reconnecting…"
  chip in the topbar; on reconnect it re-syncs unread counts in one request.
- Tray actions: mark one read (click), "Mark all read", link to full notification
  history (per interface). Delivery failures surface only in
  `notifications.logs_view` screens (UI-A-16/ UI-B-20), never in user trays.
- Event types routed to trays: `order.paid`, `order.refunded`, `payout.updated`,
  `commission.released`, `ticket.message.created`, `ticket.assigned`,
  `fulfillment.updated`, `license.activated`, `tenant.status_changed`,
  `subscription.expiring`, `ai.suggestion.ready`.
- Accessibility: badge uses `aria-live="polite"` on count changes; panel is a proper
  menu with arrow-key navigation and Escape-to-close returning focus to the bell.

### 2.4 Global Search

- Trigger: topbar input (placeholder "Search…") + `Ctrl/Cmd+K` palette. Minimum 2
  characters; 250 ms debounce; max 8 results per entity type, 5 types per response.
- Server-side search restricted to the user's permissions and tenant scope; result
  groups labeled (Orders, Tickets, Customers, Products, Invoices…); each row shows
  type icon, primary text, secondary context ("#ORD-1042 · ₹12,499 · Priya S."), and
  tenant chip for platform staff.
- Keyboard: `↑/↓` move, `Enter` opens, `Esc` closes; palette has `role="dialog"` and
  `aria-activedescendant` wiring.
- No-results state: "No matches for '<query>' in your access scope" (never leaks
  existence of out-of-scope records).
- Rate guard: ≤ 1 request per 250 ms per user; 429 shows inline "Slow down" chip for
  5 s.

### 2.5 Tables, Pagination, Filters (interfaces A, B, D, E, F)

| Aspect | Specification |
|---|---|
| Structure | Semantic `<table>` with `<th scope>`; sticky header below topbar; row height 48 px (40 px compact, P2); zebra = none; hover = neutral-50. |
| Columns | Money columns right-aligned with currency symbol; dates as `DD MMM YYYY, hh:mm IST` with UTC tooltip; status columns use badge component (§2.7); ID columns monospace with click-to-copy. |
| Sort | Server-side; click `<th>` toggles asc/desc (triarrows via `aria-sort`); default sort and its direction shown on load. |
| Pagination | Server-side; page sizes 25/50/100 (default 25); "Showing X–Y of Z"; URL-synced (`?page=&per=&sort=&dir=`); infinite scroll is NOT used. |
| Filters | Filter bar above table: quick text search + dropdown facets (status, date range, owner, tenant for platform staff); active filters render as removable chips; "Clear all"; state URL-synced; result count "N results". |
| Bulk actions | Checkbox column (Shift+click range); selection bar docks at bottom with count + bulk verbs permitted by slug (export, approve, close…); bulk destructive actions confirm with affected count. |
| Row actions | Right-aligned kebab menu (max 5 items + divider + view); each item carries its own permission gate. |
| Exports | "Export" button rendered when `*.export` slug exists; clicking enqueues a CSV job and toasts "Export queued — you'll get a notification when it's ready". |
| Density/ saved views | P2 (flagged in §10); not MVP. |

### 2.6 Forms

- Layout: labels above inputs; required = asterisk + `aria-required`; help text below
  label; two-column grid for ≤8 fields, single column beyond; sticky footer with
  primary/secondary actions on forms longer than one viewport.
- Validation: client-side on blur + on submit (mirror of server rules); server errors
  map to fields via `aria-invalid="true"` + `aria-describedby` error text; a summary
  banner ("Fix 3 fields") links to each invalid field; never clear sibling field values
  on a server error.
- Money fields: currency prefix, digit grouping as typed, two-decimal max, rejects
  negatives unless the form is an adjustment form (which mandates a ≥10-char reason).
- Date fields: date picker + ISO fallback input; timezone shown next to the label
  (tenant timezone, default IST).
- Unsaved-changes guard: navigating away with dirty state opens a confirm dialog
  ("Discard changes?" — Discard/Keep editing). CMS page editor additionally autosaves
  drafts every 30 s with a "Draft saved hh:mm" caption (UI-B-11).
- Long forms (product editor, checkout) render section accordions with per-section
  validity dots.

### 2.7 Buttons, Modals, Drawers, Toasts, Badges

- Buttons: `primary` (one per view), `secondary`, `ghost`, `danger`; sizes S/M/L; all
  loading states swap label for spinner + `aria-busy="true"` and prevent double
  submit; min touch target 40 px (AA 2.5.8 exceeds 24 px minimum).
- Confirmation modal for destructive/irreversible actions (tenant suspend, payout
  approve, refund execute, product delete, license revoke): title names the entity,
  body states the exact consequence + amount/count, requires typing the entity
  identifier (e.g., tenant slug or payout ID), `danger` button disabled until match;
  Escape cancels and returns focus.
- Drawers (right-side, 480 px) for quick previews (row detail); full pages for
  authoritative editing; drawer has focus trap and `role="dialog"`.
- Toasts: top-right stack, max 3, 380 px; success auto-dismiss 4 s; info 6 s; error
  persists until dismissed and includes a retry action when the operation is
  retryable; toasts are announced via `aria-live` (polite; assertive for errors).
- Badges (status): fixed palette mapping — neutral (draft/pending), blue (active),
  amber (on_hold/pending review), green (paid/fulfilled/active), red
  (failed/suspended/refunded), purple (trial); every badge pairs color with text (no
  color-only status, WCAG 1.4.1).

### 2.8 Empty, Loading, Error States & Error Pages

| State | Specification |
|---|---|
| Empty | Shared `EmptyState` component: 48 px line icon, heading (e.g., "No tickets yet"), one-sentence explanation of what fills this list, primary CTA (permission-gated) — e.g., UI-B-05 "Add your first customer". Filtered-empty variant: "No matches — try removing filters" + "Clear filters". |
| Loading | Skeletons matching the final layout (same grid/row counts: 8 KPI cards, 10 table rows, form field blocks); shimmer disabled under `prefers-reduced-motion`; if a request exceeds 8 s, skeletons are replaced by "Still loading…" + Retry button; no full-page spinners except initial auth bootstrap. |
| Inline error | Error card in content area: heading "Couldn't load <resource>", one-line cause, Retry button, collapsible technical detail (exception id for support). |
| 403 | White-label page: "You don't have access to this area" + "Return to dashboard" + support contact link (reseller-configurable text on storefront/portal). |
| 404 | White-label "Page not found" with search box + popular links. |
| 500 | "Something went wrong" + correlation ID (copyable, matches `audit_logs`) + Retry; reseller-configurable on storefront/portal (UI-B-11). |
| Maintenance | Tenant-suspended storefront page (white-label, reseller-configurable message) per doc 02 §7.5. |

### 2.9 Tokens (color, typography, elevation, motion)

- Color: neutral scale 50–950; interface defaults — A/F: indigo-600 primary; B/D/E:
  tenant brand via §2.10; semantic: success-600, warning-600, danger-600, info-600.
  All text/background pairs ship ≥ 4.5:1 contrast; ≥ 3:1 for large text and UI borders
  (verified with automated contrast tests in CI).
- Type: Inter with system-font fallback stack; scale 12/14/16/20/24/30 px; base 14 px
  in dashboards, 16 px in storefront/portal; line-height 1.5 body, 1.2 headings.
- Spacing: 4 px grid (4/8/12/16/24/32/48/64). Radius: 6 px controls, 8 px cards,
  full-round avatars/pills. Elevation: 3 shadow levels (card, dropdown, modal).
- Motion: 150–200 ms ease-out for hover/expand; row-highlight flash 1.5 s; all
  non-essential motion disabled under `prefers-reduced-motion: reduce`.

### 2.10 White-Label Theming Variables (storefront, portal, affiliate; light branding on B/D)

| Variable | Applied to |
|---|---|
| `--sf-brand-500/600/700` | Primary buttons, links, active states, price accents. |
| `--sf-accent` | Secondary highlights (badges, banners). |
| `--sf-logo-url`, `--sf-favicon-url` | Topbar/portal logo, browser tab icon. |
| `--sf-font-heading` | Heading font (tenant upload or Google font pick). |
| `--sf-radius` | Corner-radius scale (6–12 px). |
| `--sf-banner` | Optional announcement bar. |

- Resolution: host → `tenant_domains` → `tenant_settings`/`website_themes` →
  variables injected as a cached CSS custom-property block (15-minute app+edge cache);
  storefront/portal/affiliate surfaces are fully white-labeled; reseller dashboard
  (B) shows the tenant logo in the sidebar/topbar slot with the Frenchies platform
  theme otherwise.
- Legality constraint (charter §4): invoices (PDF), payment descriptor, and checkout
  "Sold by" always show the platform legal entity line — these are not themable and
  carry a fixed footnote style.
- Contrast guard: if a tenant's brand color fails 4.5:1 with white text, the theming
  engine darkens `--sf-brand-600` automatically and warns the owner in UI-B-12.

### 2.11 Accessibility Baseline (WCAG 2.2 AA — applies to every screen)

1. Contrast ≥ 4.5:1 text, ≥ 3:1 UI components/focus indicators (1.4.3, 1.4.11).
2. Visible focus ring: 2 px brand outline, 2 px offset, on every interactive element
   (2.4.7); focus order matches visual order (2.4.3); skip-to-content first (2.4.1).
3. All inputs have programmatic labels; errors identified by text + `aria-invalid` +
   `aria-describedby` (3.3.1, 3.3.3); required fields announced.
4. Keyboard operability everywhere, including tables (row actions reachable), menus,
   modals (focus trap + restore), and the Cmd+K palette (2.1.1); no keyboard traps
   (2.1.2).
5. Target size ≥ 24×24 px for all controls (2.5.8); primary mobile CTAs ≥ 44 px high.
6. Status/messages announced via `aria-live` regions (4.1.3); toasts polite/assertive
   per §2.7.
7. Reflow at 320 px width without horizontal scrolling (1.4.10); text-spacing overrides
   resilient (1.4.12).
8. Meaning never conveyed by color alone (1.4.1); charts include text summaries.
9. Page language `en-IN`; every page has a unique `<title>` and one `<h1>`.

### 2.12 Responsive Strategy

| Breakpoint | Token | Behavior |
|---|---|---|
| < 640 px | `sm` | Mobile: single column; storefront/portal/affiliate are mobile-first (designed at 320 px first); tables → card lists (each row becomes a card with labeled key/value pairs, actions in a footer); sidebar → off-canvas drawer with scrim; tray/search → full-screen sheets. |
| 640–767 px | `md` | Large phone/small tablet: 2-up KPI grids; filter bar collapses to "Filters (N)" button; checkout summary becomes an accordion. |
| 768–1023 px | `lg` | Tablet: sidebar auto-collapses to icon rail; 3-up KPI grids; tables regain table layout with column-priority hiding (ID/dates drop first). |
| 1024–1279 px | `xl` | Desktop: full 256 px sidebar; 4-up KPI grids. |
| ≥ 1280 px | `2xl` | Wide: content max-width 1440 px centered; dashboards may show 6-up KPI grids. |

- Priority surfaces for mobile-first: Interface C (incl. checkout), Interface D,
  Interface E; Interfaces A/B/F are desktop-first with the responsive fallbacks above.
- Browser support baseline: last 2 versions of Chrome/Edge/Firefox/Safari + iOS
  Safari ≥ 16 + Chrome Android (P1 for C/D/E).

## 3. Interface A — Super Admin Panel

Shell: platform theme (indigo-600), tenant switcher in topbar, impersonation banner
(§2.1). Screens below are the authoritative list; sub-tabs are noted per screen.

### UI-A-01 · Platform Dashboard

| Field | Detail |
|---|---|
| Purpose | One-glance platform health: revenue, tenants, orders, payouts queue depth. |
| User roles | Super Admin, Platform Admin (non-financial cards), Finance Admin (financial cards). |
| Navigation | Sidebar → Overview → Dashboard (default landing after login). |
| Main components | 6 KPI cards (Today GMV, MTD revenue, active tenants, pending approvals, payouts awaiting action, failed payments 24 h); two charts (revenue 30-day line, orders by tenant bar, top 10); "Attention needed" list (pending reseller approvals, failed webhooks, SLA breaches, suspended tenants count). |
| Forms & fields | None (filters only: date-range preset 7/30/90 days, tenant facet for platform staff). |
| Buttons & actions | Cards deep-link to owning screens (UI-A-04, UI-A-09, UI-A-11); chart hover tooltip; "Refresh" re-fetches without full reload. |
| Validation rules | Date range max 365 days; server rejects larger ranges with inline error. |
| Permissions | `reports.view` (all cards); financial cards require `reports.financial_view`. |
| Empty state | "No activity in this period" per card; attention list shows green check "Nothing needs attention". |
| Loading state | 6 KPI skeletons + 2 chart skeletons (fixed height 280 px); charts render from cached payload ≤ 7 days old while fresh fetch runs. |
| Error state | Per-card error card with Retry; page shell never fully fails. |
| Responsive | `md`: 2-up cards, charts stacked; `sm`: cards 1-up, charts in collapsible accordions, attention list first. |

### UI-A-02 · Tenant Management

| Field | Detail |
|---|---|
| Purpose | List, filter, and act on every tenant (platform + reseller tenants). |
| User roles | Super Admin (all actions), Platform Admin (create/approve/update), Finance/Support managers (read). |
| Navigation | Sidebar → Tenancy → Tenants. |
| Main components | Standard table (§2.5): tenant name, slug, type badge (platform/reseller), status badge, plan, parent reseller, wallet balance, created; filter facets: type, status, plan, parent; saved-row quick actions. |
| Forms & fields | "Create tenant" drawer: name, slug (auto from name), type (reseller), parent reseller (searchable select), GSTIN (optional), owner email (invitation sent on create). |
| Buttons & actions | View; Approve (pending); Suspend (confirm modal, typed slug); Resume; Edit; Impersonate tenant user (opens user picker, requires `tenants.impersonate`); Manage domains (UI-A-03 tab). |
| Validation rules | Slug: `^[a-z0-9-]{3,60}$`, unique, not `central`; GSTIN regex when provided (15-char, doc 07); owner email valid + not already owner of another pending tenant. |
| Permissions | `tenants.view` (list), `tenants.create/update/approve` (actions), `tenants.suspend/resume/delete` (Super Admin), `tenants.impersonate`. |
| Empty state | "No tenants match" / first-run "No reseller tenants yet — approve your first reseller" → UI-A-04. |
| Loading state | 10 row skeletons; action buttons show inline spinners and rows stay in place until server confirms. |
| Error state | Inline row error toast with Retry; suspend failures (e.g., unpaid payouts) show blocking reason dialog. |
| Responsive | `lg`: table; below `lg`: card list (name, status, plan, actions kebab). |

### UI-A-03 · Tenant Detail & Approval

| Field | Detail |
|---|---|
| Purpose | 360° tenant record: profile, domains, settings, users, status history, approve/suspend workflow. |
| User roles | Super Admin, Platform Admin (approval), Finance Admin (financial tabs read-only). |
| Navigation | Tenants → row click; breadcrumbs `Tenants > <name>`. |
| Main components | Header (name, slug, status badge, created, plan, actions); tabs — Overview, Domains, Settings, Users, Financial summary, Status history (from `audit_logs`); domain table with verification state (DNS TXT/CNAME check, "Verify" recheck button). |
| Forms & fields | Overview edit: display name, GSTIN, contact, timezone; Domains tab: add domain form (hostname); Settings tab: key/value pairs from `tenant_settings` (maintenance message, auto-activate sub-resellers toggle). |
| Buttons & actions | Approve tenant (pending → active, confirm modal); Suspend (typed-slug confirm + reason field ≥ 20 chars, mailed to owner); Resume; Delete (Super Admin, typed confirm, blocks if payouts unpaid); Verify domain. |
| Validation rules | Domain: valid FQDN, ≤ 253 chars, not already bound to another tenant, HTTPS cert provisioning state shown; suspension reason mandatory (≥ 20 chars). |
| Permissions | `tenants.view/update/approve`; suspend/resume/delete = Super Admin; domains = `tenants.domains_manage`. |
| Empty state | Per-tab empties ("No custom domains — using <slug>.frenchies.com"). |
| Loading state | Tab content skeletons; domain verification shows determinate progress ("Checking DNS…"). |
| Error state | Verification failure shows exact failed check (record missing / propagation wait) with retry; approval errors (missing GSTIN on billed tenant) block with actionable message. |
| Responsive | Tabs scroll horizontally below `lg`; domain table → card list below `md`. |

### UI-A-04 · Reseller Onboarding Queue

| Field | Detail |
|---|---|
| Purpose | Triage pending reseller applications and parent-created sub-resellers awaiting review. |
| User roles | Platform Admin (approve), Super Admin. |
| Navigation | Sidebar → Tenancy → Onboarding queue (badge shows count). |
| Main components | Kanban-free queue table: applicant business, type (direct/child of <parent>), submitted age, completeness score (fields provided / required), risk flags (duplicate GSTIN, disposable email, parent suspended); row expand shows application answers + documents. |
| Forms & fields | Approval panel: assigned reviewer (internal note), activation mode (activate now / request more info), message to applicant (pre-filled template). |
| Buttons & actions | Approve → provisions tenant (queued) + owner invitation; Request info (status `info_requested`, templated email); Reject (reason required, ≥ 20 chars, mailed); Open parent (UI-A-05). |
| Validation rules | Cannot approve if GSTIN duplicate of an active tenant unless Super Admin overrides with reason; auto-activated children (doc 02 §7.2.3) appear here only as an audit list. |
| Permissions | `resellers.approve`, `tenants.approve`; queue viewing `resellers.view`. |
| Empty state | "Queue empty — no pending applications". |
| Loading state | Row skeletons; approve action shows 2-step progress (validating → provisioning) since provisioning is queued. |
| Error state | Provisioning failure moves row to "Failed" tab with exception ID + "Retry provisioning" (Super Admin). |
| Responsive | Table ≥ `md`; card list below with approve/reject as footer buttons. |

### UI-A-05 · Reseller Hierarchy Explorer

| Field | Detail |
|---|---|
| Purpose | Visualize the multi-level reseller closure tree with subtree aggregates (platform view of what parents may also see). |
| User roles | Super Admin, Platform Admin (read), Product Manager (read, for authorization targeting). |
| Navigation | Sidebar → Tenancy → Hierarchy. |
| Main components | Collapsible tree (unlimited depth, indent 24 px/level, lazy-loads children on expand): node = reseller name, status badge, depth chip, subtree stats (tenants, orders 30 d, GMV 30 d, commission pool); right panel shows selected node profile + direct-children table. |
| Forms & fields | Search-by-name jump; status filter; "Highlight" mode overlays suspended/negative-balance nodes with warning ring. |
| Buttons & actions | Expand/collapse all; Open tenant (UI-A-03); Move node (Super Admin only: re-parent with full-subtree impact warning + typed confirm); Export tree CSV (`reports.export`). |
| Validation rules | Move dialog blocks cycles (A cannot become descendant of its own descendant) with explicit path preview. |
| Permissions | `resellers.hierarchy_view`; move = `resellers.hierarchy_manage` + Super Admin. |
| Empty state | "No reseller hierarchy yet". |
| Loading state | Skeleton tree with 3 pre-shaped rows; deep loads show inline spinner per node. |
| Error state | Node load failure shows retry chip inline without collapsing siblings. |
| Responsive | Tree becomes indented list with per-node detail sheet below `md`. |

### UI-A-06 · Central Product Catalog

| Field | Detail |
|---|---|
| Purpose | Manage central-owned products: content, status, pricing, distribution reach. |
| User roles | Product Manager (Full), Platform Admin (Full), Finance (read). |
| Navigation | Sidebar → Catalog → Products. |
| Main components | Table: name, SKU, type (one-time/subscription/package), status badge (draft/published/archived), price (INR), margin %, authorized resellers count, demos count, updated; facets: status, type, category, fulfillment model. |
| Forms & fields | Quick-create drawer: name, category, type, base price, billing period (if subscription). |
| Buttons & actions | Open editor (UI-A-07); Publish/Unpublish (confirm with sell-impact note: "3 resellers currently selling"); Duplicate; Archive; Manage distribution (UI-A-08). |
| Validation rules | SKU `^[A-Z0-9-]{3,40}$` unique; price ≥ ₹0.00 with max 2 decimals; publish blocked until one price row + one fulfillment rule exist (exact messages listed in doc 06). |
| Permissions | `products.view/create/update/publish/delete`; pricing fields require `products.pricing_manage`. |
| Empty state | "No products yet — create your first product" → editor. |
| Loading state | 10 row skeletons; status toggles optimistic with rollback toast on failure. |
| Error state | Row-level error toast with Retry; taxonomy load failure degrades to free-text category input. |
| Responsive | Card list below `lg`. |

### UI-A-07 · Product Editor

| Field | Detail |
|---|---|
| Purpose | Authoritative single-product editing surface (content, media, pricing, variants, demos, documents, versions). |
| User roles | Product Manager, Platform Admin. |
| Navigation | Catalog → row click → Editor; deep tab URLs (`?tab=pricing`). |
| Main components | Section accordions with validity dots — Basics (name, slug, description rich text, category, tags), Media (drag-drop uploader with progress + alt-text fields), Pricing (base price, `product_pricing` rows per currency/tax mode, margin calculator), Variants & packages (`product_variants`, `product_packages` builder), Addons (`product_addons` link table), Demos (list → UI demo editor per doc 06), Documents (`product_documents`), Fulfillment (model select: manual/API/license, provisioning instructions), Versions (timeline + rollback), SEO (meta title/description, slug preview). |
| Forms & fields | All fields above; autosave draft every 30 s ("Draft saved 14:05" caption); version comment field on publish. |
| Buttons & actions | Save draft; Preview storefront (opens UI-C-03 in new tab with draft token); Publish (validates all sections; typed confirm for re-publish after price increase > 10%); Request review (Platform Admin for PM-authored products, P2 toggle); Archive. |
| Validation rules | Slug: `^[a-z0-9-]{3,80}$` unique per tenant; rich text ≤ 50,000 chars, sanitized allowlist; media ≤ 25 MB, MIME allowlist (png/jpg/webp/pdf/mp4); price increments ≥ ₹1; variant name unique within product; addon link loops rejected. |
| Permissions | `products.update`, `products.publish`, `products.variants_manage`, `products.pricing_manage`, `demos.*`. |
| Empty state | Tabs show first-run hints ("No media — drag files here"). |
| Loading state | Form skeleton per section; uploader shows per-file progress bars. |
| Error state | Server field errors per §2.6; publish failure lists every blocking section with anchors. |
| Responsive | Accordions single-column below `xl`; media grid 4→2→1 columns. |

### UI-A-08 · Distribution Authorizations

| Field | Detail |
|---|---|
| Purpose | Grant/revoke reseller rights to sell central (or parent-owned) products with per-reseller price overrides and commission terms (`tenant_products`). |
| User roles | Product Manager, Platform Admin (Full); Super Admin overrides. |
| Navigation | Catalog → Distribution (global list) or Product editor → Distribution tab. |
| Main components | Two views — By product (resellers table: reseller tenant, price override, commission %, status, since) and By reseller (products table with the same fields); pending-request panel (reseller-initiated requests from UI-B-03). |
| Forms & fields | Grant form: reseller (searchable), override price (or "use base"), commission terms (total % within platform pool rules, doc 08), effective-from date, note. |
| Buttons & actions | Authorize; Edit terms; Revoke (confirm modal listing SKUs currently sellable by that reseller and affected active subscriptions); Approve/Decline request. |
| Validation rules | Override price must be ≥ reseller's cost floor (base × minimum margin setting); commission % ≤ platform maximum for the level; effective date not in the past > 1 day. |
| Permissions | `products.authorize`, `products.pricing_manage`, `resellers.view`. |
| Empty state | "No distribution grants — authorize a reseller to start selling". |
| Loading state | Table skeletons; grant submit shows inline spinner on the modal button until 201. |
| Error state | Conflict (terms changed by another admin) → "Terms were updated by <user> — reload" banner. |
| Responsive | Views stack below `md`; pending requests pinned on top. |

### UI-A-09 · Payments & Transactions

| Field | Detail |
|---|---|
| Purpose | Monitor all gateway payments (Razorpay/Cashfree), webhook events, settlement reconciliation. |
| User roles | Finance Admin (Full), Platform Admin (read), Super Admin. |
| Navigation | Sidebar → Finance → Payments. |
| Main components | Tabs — Payments (table: payment ID, order ref, tenant, customer, amount, gateway, method, status badge captured/failed/pending, captured_at), Webhook events (`payment_webhook_events`: gateway + event id, received, processed, duplicate flag), Settlements (batch import + reconciliation state per doc 07); row expand shows gateway payload excerpt + ledger postings preview. |
| Forms & fields | Reconciliation form: settlement batch reference, amount, date, gateway file upload (CSV); manual "mark reconciled" with reason. |
| Buttons & actions | Open order (UI-A-10 context); Reconcile (per §2.6 forms); Replay webhook (Super Admin, idempotency respected — duplicates rejected with explanation); Export. |
| Validation rules | Settlement amount must equal sum of unlinked captures in batch (±₹0 tolerance) else partial-link mode requires manual selection; replay blocked for events already posted to ledger. |
| Permissions | `payments.view`, `payments.reconcile`; replay = Super Admin; exports `payments` + `reports.export`. |
| Empty state | "No payments in range". |
| Loading state | Row skeletons; webhook detail lazy-loads. |
| Error state | Gateway timeout on fetch → cached 5-min snapshot banner "Showing data from hh:mm". |
| Responsive | Card list below `lg`; webhook tab hidden below `md` (desktop ops task). |

### UI-A-10 · Refunds Management

| Field | Detail |
|---|---|
| Purpose | Execute and track refunds end-to-end incl. commission clawback preview. |
| User roles | Finance Admin (execute), Platform Admin (read requests), Super Admin. |
| Navigation | Finance → Refunds (badge: pending requests). |
| Main components | Tabs — Requests (from resellers/customers, ordered by age), Processing, Completed/Failed; refund table: refund ID, order, requester, amount, reason category, clawback preview (per beneficiary), status; row detail shows original payment, ledger reversal preview (doc 07/08), and communication history. |
| Forms & fields | Execute refund: amount (≤ capturable balance, partial allowed), reason category (select), note (≥ 10 chars), clawback acknowledgment checkbox after preview renders. |
| Buttons & actions | Approve & execute (typed-confirm modal with amount + order ID; separate user from requester per doc 02 §7.6); Decline (reason); Notify requester (auto on status change). |
| Validation rules | Amount > 0 and ≤ refundable balance; reason required; execution blocked if gateway unreachable (queued retry with visible state, max 3 attempts then `failed` + alert). |
| Permissions | `payments.refund_execute` + `orders.refund`; viewing `payments.view`. |
| Empty state | "No refund requests". |
| Loading state | Clawback preview shows "Calculating reversals…" skeleton; button spinners during gateway call (up to 30 s with progress copy). |
| Error state | Gateway failure keeps request `processing` with retry countdown; terminal failure shows gateway message + "Retry" (audited). |
| Responsive | Card list below `lg`; execute modal full-screen sheet below `md`. |

### UI-A-11 · Payouts Approval

| Field | Detail |
|---|---|
| Purpose | Approve/reject/mark-paid manual payouts for resellers and affiliates (charter §7.8). |
| User roles | Finance Admin (approve/reject/mark-paid), Super Admin. |
| Navigation | Finance → Payouts (badge: awaiting approval). |
| Main components | Queue table: payout ID, beneficiary type (reseller/affiliate), beneficiary, amount, method (bank/UPI), requested at, risk flags (new bank details < 48 h, negative balance after payout); row expand: bank details (masked, reveal = audited), balance breakdown (available/pending/commission), prior payouts. |
| Forms & fields | Approve: internal note; Reject: reason (≥ 20 chars, mailed); Mark paid: transfer reference (UTR), paid date, attachment upload (PDF/JPG ≤ 5 MB). |
| Buttons & actions | Approve (typed-confirm with amount), Reject, Mark paid, Open beneficiary wallet (UI-A-13), Export queue. |
| Validation rules | Amount ≤ available balance at approval time (re-checked server-side; stale rows show "balance changed — refresh"); requester = approver blocked (doc 02 AC-07); UTR ≥ 8 chars unique. |
| Permissions | `payouts.view/approve/reject/mark_paid`. |
| Empty state | "Nothing awaiting approval — nice work". |
| Loading state | Queue skeletons; approval shows two-step (validating balance → posting ledger). |
| Error state | Ledger posting failure after gateway-free approval flips status `approved` with `ledger_error` flag + alert banner + "Retry posting" (Super Admin). |
| Responsive | Card list below `lg`; risk-flagged rows pinned first regardless of sort. |

### UI-A-12 · Commission Oversight

| Field | Detail |
|---|---|
| Purpose | Monitor, approve release of, and adjust hierarchical + affiliate commissions (docs 07/08 rules). |
| User roles | Finance Admin (approve/adjust), Platform Admin (read), Product Manager (per-product read). |
| Navigation | Sidebar → Finance → Commissions. |
| Main components | Summary strip (pending pool, released today, clawback MTD, hold-period config readout); tabs — Pending / Available / Clawed back / Adjustments; table: commission ID, order, beneficiary (level chain A→B→C chips), pool %, amount, hold release date, fulfillment gate state; row detail: split breakdown per charter §7.5 example. |
| Forms & fields | Manual release: scope (single/filtered batch) + reason; adjustment: amount ±, direction, reason ≥ 10 chars; Rules editor (own tab): hold days, per-level split %, fulfillment-gate toggle. |
| Buttons & actions | Release eligible (batch, confirms count + total), Adjust (typed-confirm), Export, Open order. |
| Validation rules | Split percentages across levels must sum to 100 %; hold days 0–90; release blocked while fulfillment gate unmet (shows which gate); adjustments never make cumulative beneficiary balance negative without explicit "create recoverable debit" acknowledgment (charter §7.7). |
| Permissions | `commissions.view_all/approve/adjust/rules_manage`; exports `commissions.export`. |
| Empty state | "No commission records in this state". |
| Loading state | Summary strip skeleton; batch release shows progress ("Processing 240 of 512"). |
| Error state | Partial batch failure lists failed IDs with per-row retry; rules save conflicts show "modified by <user>" banner. |
| Responsive | Chain chips collapse to "3 levels" tooltip below `lg`; tables → cards below `md`. |

### UI-A-13 · Wallets & Ledger Inspector

| Field | Detail |
|---|---|
| Purpose | Inspect double-entry ledger truth (`journal_entries`/`journal_lines`) vs wallet projections; post audited manual adjustments. |
| User roles | Finance Admin (Full), Super Admin. |
| Navigation | Finance → Wallets & Ledger. |
| Main components | Tabs — Wallets (tenant/affiliate balances: available/pending/commission columns, derived per charter §7.2), Journal (entries with balanced-lines indicator, filter by type/date/tenant), Adjustments log; entry detail drawer: balanced debit/credit lines, source document link (order/payout/refund), reversal link. |
| Forms & fields | Adjustment form: wallet (searchable), direction (credit/debit), amount > 0, reason ≥ 10 chars, reference; second-approver selector when amount > threshold (doc 02 §7.6). |
| Buttons & actions | Post adjustment (typed-confirm), Open wallet transactions, Reverse entry (Super Admin, creates counter-entry, never deletes), Export. |
| Validation rules | Lines must balance (UI blocks submit on imbalance — server enforces anyway); amount ≤ threshold without second approval; reason mandatory. |
| Permissions | `wallets.view_all/adjust/export`, `audit.view`. |
| Empty state | "No journal entries match filters". |
| Loading state | Entry list skeleton; detail drawer lazy-loads lines. |
| Error state | Unbalanced-entry alert (data integrity incident) pins a red banner with entry ID + "Create incident" link to support. |
| Responsive | Ledger tables desktop-only density; below `md` simplified card view (summary line + expandable lines). |

### UI-A-14 · Subscription Plan Manager

| Field | Detail |
|---|---|
| Purpose | Define reseller-facing platform subscription plans (limits, prices, trial) and manage plan assignments. |
| User roles | Platform Admin (Full), Finance Admin (read), Super Admin. |
| Navigation | Sidebar → Monetization → Plans. |
| Main components | Plan cards grid (name, price/mo, tenant count on plan, limits summary, status) + comparison table; plan detail editor with tabs — Pricing (₹/month, ₹/year, setup fee), Limits (sub-resellers max, products max, staff seats, storage GB, AI credits), Features (checkbox matrix mapping to feature flags), Trial (days), Migrations (tenants on this plan). |
| Forms & fields | As above; every limit numeric ≥ 0; "unlimited" toggle per limit. |
| Buttons & actions | Create plan; Publish change (impact modal listing tenants affected by limit/price changes); Assign/Move tenant (select tenants, effective date); Archive plan (blocked while tenants assigned unless a replacement is chosen). |
| Validation rules | Plan slug unique; annual price ≤ 12× monthly; trial ≤ 60 days; limit decreases below current usage warn with per-tenant list ("4 tenants exceed proposed limit"). |
| Permissions | `subscriptions.plans_manage` (edit), `subscriptions.view` (read), `tenants.view` (assignments). |
| Empty state | "No plans — create your first reseller plan". |
| Loading state | Card skeletons; migration counts lazy-load. |
| Error state | Publish conflict shows diff modal (mine vs theirs) for concurrent edits. |
| Responsive | Cards 3→2→1 columns; comparison table horizontally scrollable with sticky first column. |

### UI-A-15 · AI Admin

| Field | Detail |
|---|---|
| Purpose | Configure platform AI providers, global agents, knowledge sources, usage meters and AI billing (doc 11). |
| User roles | Super Admin (settings), Platform Admin (agents/knowledge), Finance Admin (usage read). |
| Navigation | Sidebar → AI → Overview / Agents / Knowledge / Usage & Billing. |
| Main components | Overview: provider status cards (OpenAI, Gemini — connection state, latency, spend MTD), global guardrails summary; Agents table (`ai_agents`: name, scope platform/tenant, model, status, conversations 7 d); Knowledge sources list (`ai_knowledge_sources`: type, sync state, last indexed); Usage: meter charts (tokens/day by model, cost by tenant), `ai_usage_logs` table. |
| Forms & fields | Provider config: API key (write-only field with "replace" semantics), model defaults, monthly spend cap per tenant and platform-wide; Agent editor: name, model, system prompt, tool toggles, escalation threshold; Usage billing: enable usage-based charges, markup %, free-tier credits per plan. |
| Buttons & actions | Test connection; Save (keys masked after save); Sync source; Archive agent; Export usage; Set spend cap (typed-confirm when raising an existing cap). |
| Validation rules | Spend cap ≥ ₹0 and ≤ platform ceiling; prompt ≤ 20,000 chars; markup 0–200 %; test connection timeout 15 s with explicit failure reason. |
| Permissions | `ai.agents_manage/knowledge_manage/settings_manage`; usage `ai.usage_view`. |
| Empty state | "No providers configured — add OpenAI or Gemini to enable AI features". |
| Loading state | Connection test shows determinate spinner with step text; usage charts skeleton. |
| Error state | Provider failure shows retry + last error snippet; usage feed failure degrades to last successful snapshot with age banner. |
| Responsive | Provider cards 2→1 columns; tabs scroll horizontally below `md`. |

### UI-A-16 · Platform Settings

| Field | Detail |
|---|---|
| Purpose | Global platform configuration: general, security, notifications, integrations, feature flags. |
| User roles | Super Admin (all groups), Platform Admin (general, notifications, integrations), Finance Admin (tax defaults read). |
| Navigation | Sidebar → Settings → (General / Security / Notifications / Integrations / Feature flags). |
| Main components | Tabbed settings forms with per-key descriptions and "changed from <old value>" diffs in `audit_logs` preview; Security tab: MFA enforcement toggle, password policy, session lifetime, IP allowlist editor; Notifications tab: default templates link (UI-A-… templates within this screen), sender identity; Integrations tab: WhatsApp Cloud API, email provider, n8n webhook URL, S3 storage prefixes (per doc 03). |
| Forms & fields | Grouped key/value forms with typed inputs per key (boolean toggles, numeric steppers, selects); every save shows a side-by-side diff modal. |
| Buttons & actions | Save group (typed-confirm for Security group), Discard, View change history (per-key `audit_logs`). |
| Validation rules | Numeric bounds per key (e.g., session lifetime 5–1440 min, commission hold 0–90 d); webhook URL must be https; email sender domain must be verified. |
| Permissions | `settings.platform_manage` (general), `settings.security_manage` (security), `settings.integrations_manage`, `notifications.templates_manage`. |
| Empty state | Not applicable (defaults shown); first-run banner highlights keys differing from recommended defaults. |
| Loading state | Form skeleton; save shows inline spinner + success toast with "View history" link. |
| Error state | Validation errors per §2.6; integration save failures show provider-specific error code + doc link. |
| Responsive | Two-column (form + help panel) ≥ `xl`; stacked below. |

### UI-A-17 · Audit Logs

| Field | Detail |
|---|---|
| Purpose | Searchable, tamper-evident trail of sensitive actions across tenants (doc 17). |
| User roles | Super Admin (cross-tenant), Platform Admin (central scope read). |
| Navigation | Sidebar → Security → Audit logs. |
| Main components | Filter bar (actor, action slug, tenant, subject type/ID, date range, impersonation-only toggle); table: timestamp, actor, tenant chip, action, subject, IP/user-agent, summary; row detail drawer: before/after JSON diff (redacted secrets), correlation ID, related rows links. |
| Forms & fields | Filters only (URL-synced); redaction notice banner ("Secrets are redacted at write time"). |
| Buttons & actions | Open subject; Open actor; Export (`audit.export`); Copy correlation ID. |
| Validation rules | Date range ≤ 90 days per query; export capped at 100,000 rows with async job notice. |
| Permissions | `audit.view`; export `audit.export`. |
| Empty state | "No audit events match". |
| Loading state | Row skeletons; drawer diff lazy-loads. |
| Error state | Query timeout (> 10 s) suggests narrower filters with one-click preset chips. |
| Responsive | Desktop-first; below `md`, drawer becomes full-screen with collapsed diff. |

### UI-A-18 · Users, Roles & Permissions

| Field | Detail |
|---|---|
| Purpose | Manage platform staff, cross-tenant grants, seeded role templates, and the permission catalogue flags. |
| User roles | Super Admin (all), Platform Admin (central invitations). |
| Navigation | Sidebar → Security → Users & roles; tabs — Users, Roles, Permission catalogue, Invitations. |
| Main components | Users table (name, email, roles per team with team chips, MFA state, last active, status); Roles tab: 6 seeded platform roles with slug lists (read-only cards) + future custom platform roles (P2); Permission catalogue: 136-slug table with module, reseller-assignable flag toggles (Super Admin), affected-custom-role count when flipping; Invitations tab: pending/sent/accepted/revoked with resend/revoke. |
| Forms & fields | Invite user: email, roles (multi-select), cross-tenant grant builder (team picker + role + optional expiry); edit user: roles per team, deactivate; catalogue flag flip: reason ≥ 10 chars + impact preview ("affects 14 custom roles in 9 tenants"). |
| Buttons & actions | Invite, Resend, Revoke, Assign/Remove role per team, Force logout (queued session invalidation, doc 02 AC-13), Open tenant. |
| Validation rules | Cannot remove your own last Super Admin role (block with "assign another Super Admin first"); email valid + unique per team (doc 02 §10); grant expiry ≤ 365 days. |
| Permissions | `users.view/invite/update/deactivate/roles_assign`; catalogue flag = Super Admin only. |
| Empty state | "No users in this team". |
| Loading state | Table skeletons; role chips lazy-load for > 50 users per page note. |
| Error state | Concurrent role edit shows diff + reload banner; force-logout failure shows queued state honestly ("will apply on next request"). |
| Responsive | Card list below `lg`; catalogue table desktop-only with export fallback below `md`. |

## 4. Interface B — Reseller Dashboard

Shell: platform theme with tenant logo slot (§2.10); sidebar grouped: Overview, Sell,
Storefront, People, Finance, Support, Settings. Screens are permission-filtered per
doc 02 (Reseller / Sub-Reseller / Employee / Sales Agent see only granted items).

### UI-B-01 · Reseller Dashboard

| Field | Detail |
|---|---|
| Purpose | Business pulse for the reseller tenant: sales, orders, commissions, subtree aggregates. |
| User roles | Reseller, Sub-Reseller, Reseller Employee (read), Sales Agent (own-pipeline subset). |
| Navigation | Sidebar → Overview → Dashboard (default landing). |
| Main components | KPI cards (Sales today/MTD, orders 30 d, wallet available, commissions pending, open tickets, active subscriptions); sales trend line chart (30 d); "Recent activity" feed (orders, payouts, ticket replies); subtree aggregate strip (resellers only: child tenants, subtree GMV 30 d — doc 02 §7.2); quick actions row. |
| Forms & fields | Date-range preset selector only (7/30/90 days, MTD). |
| Buttons & actions | Cards deep-link to UI-B-06/09/10/15; quick actions: Add product, Add customer, Create quotation, View payout request. |
| Validation rules | Range ≤ 365 days. |
| Permissions | `reports.view`; wallet card `wallets.view`; subtree strip `resellers.hierarchy_view`; quick actions per respective slugs. |
| Empty state | Onboarding checklist empty state: "Set up your store in 4 steps" (branding, domain, catalog, payment-enabled test order) with per-step completion ticks. |
| Loading state | KPI + chart skeletons (fixed heights); activity feed loads independently. |
| Error state | Per-card error + Retry; degraded mode shows last snapshot with age banner. |
| Responsive | `md`: 2-up cards; `sm`: 1-up, subtree strip collapsible, activity feed first. |

### UI-B-02 · My Catalog & Pricing

| Field | Detail |
|---|---|
| Purpose | Manage products this tenant sells: own products plus authorized upstream products with price overrides and margins. |
| User roles | Reseller, Sub-Reseller, Employee (view/edit per grant), Sales Agent (view only). |
| Navigation | Sidebar → Sell → Catalog. |
| Main components | Tabs — My products (owned), Authorized (from platform/parent: upstream name, my price, my margin %, commission terms chip), Bundles; table: product, source chip (Owned/Platform/Parent <name>), price, cost floor, margin, status, sales 30 d; margin heat coloring (below-minimum margin flagged amber). |
| Forms & fields | Add own product: guided wizard (Basics → Price → Fulfillment → Publish) reusing doc 06 rules; override price form: my price, tax mode (inclusive/exclusive), margin preview ("Cost ₹x → margin y%"). |
| Buttons & actions | New product; Edit; Publish/Unpublish (own only); Request more stock of authorized product; Open upstream product page; Archive. |
| Validation rules | Override price ≥ cost floor (upstream cost + minimum margin setting); cannot edit upstream content (fields disabled with "owned by <tenant>" tooltip); publish requires price + fulfillment model. |
| Permissions | `products.view/create/update/publish/pricing_manage` (own), authorized rows read + override where `tenant_products` terms allow. |
| Empty state | "No products yet — add your own or request access to platform products" (two CTAs → wizard / UI-B-03). |
| Loading state | Row skeletons; wizard steps load lazily. |
| Error state | Upstream terms sync failure shows stale-terms banner with last-synced timestamp; per-row toast on override rejection. |
| Responsive | Card list below `lg`; wizard single-column below `md`. |

### UI-B-03 · Distribution Requests

| Field | Detail |
|---|---|
| Purpose | Request distribution of upstream products and manage incoming authorization offers (from parent/platform). |
| User roles | Reseller, Sub-Reseller. |
| Navigation | Sidebar → Sell → Distribution. |
| Main components | Tabs — My requests (product, upstream owner, status: pending/approved/declined/expired, requested price, terms offered), Offers to me (incoming `tenant_products` grants with proposed terms, acceptance window), Active grants (terms + history). |
| Forms & fields | New request: product search (marketplace of upstream products), proposed price, proposed commission terms, business note (≤ 500 chars). |
| Buttons & actions | Send request; Accept offer (creates active grant); Decline offer; Renew expiring grant; Cancel pending request. |
| Validation rules | One open request per product; proposed price ≥ cost floor; note sanitized; acceptance after expiry requires re-offer (blocked with message). |
| Permissions | `products.authorize` (request side), `products.view`. |
| Empty state | "No requests — browse the platform catalog to request products". |
| Loading state | Skeleton tabs; accept action two-step (validating terms → activating). |
| Error state | Terms changed before acceptance → conflict dialog with new terms diff requiring re-accept. |
| Responsive | Offer cards stack full-width below `md`. |

### UI-B-04 · Sub-Resellers Tree

| Field | Detail |
|---|---|
| Purpose | Create and monitor the sub-reseller subtree; parent visibility per doc 02 §7.2 (aggregates only — no child mutations). |
| User roles | Reseller, Sub-Reseller (own subtree). |
| Navigation | Sidebar → People → Sub-resellers. |
| Main components | Tree/list toggle: node = name, status, depth, joined date, subtree aggregates (orders 30 d, GMV 30 d, commission earned); right panel: child profile + aggregate breakdown; "How hierarchy works" explainer link (commission split visualization per charter §7.5). |
| Forms & fields | Add sub-reseller: business name, owner name, owner email, city, GSTIN (optional), note; invite-and-create flow triggers provisioning (doc 02 §7.2.3). |
| Buttons & actions | Invite sub-reseller; Open child (read-only profile sheet); Suspend my ability to add children? (no — platform only, shown as info); Export aggregates. |
| Validation rules | Owner email unique per tenant; business name ≤ 100 chars; depth unlimited but UI warns at > 6 levels ("readability"); cannot invite email of existing user in own team. |
| Permissions | `resellers.create/hierarchy_manage/hierarchy_view/approve` (direct children), `reports.view` (aggregates). |
| Empty state | "No sub-resellers yet — invite your first partner" + explainer of subtree benefits. |
| Loading state | Tree skeleton; aggregates lazy-load per node. |
| Error state | Auto-activation off for this parent (platform review) shows honest status "Pending platform review" with submitted timestamp, not an error. |
| Responsive | Tree → indented list with detail sheet below `md`. |

### UI-B-05 · Customers

| Field | Detail |
|---|---|
| Purpose | CRUD on this tenant's customers, groups, and lifecycle state (doc 03/12). |
| User roles | Reseller, Sub-Reseller, Employee (per grant), Sales Agent (edit, no export). |
| Navigation | Sidebar → People → Customers. |
| Main components | Table: name, email/phone, GSTIN, group chips, lifetime value, orders count, status (active/blocked), last order; facets: group, status, tag, spend range; row expand: contact card, recent orders, tickets, subscription count. |
| Forms & fields | Add/edit: name, email, phone (E.164), GSTIN (validated when country=IN), billing address (address line, city, state, PIN, country), tags, group multi-select, notes; import CSV wizard (upload → map columns → dry-run report → commit). |
| Buttons & actions | New customer; Edit; Merge duplicates (P2); Block/Unblock (confirm); Export (`customers.export`); Open in portal-as view? (no — staff view only, opens customer record). |
| Validation rules | Email valid + unique per tenant (existing email offers link to merge/block instead of hard fail); phone E.164; GSTIN regex when present; CSV import max 10,000 rows with per-row error report. |
| Permissions | `customers.*` per grant; delete blocked if orders exist → archive offered instead (doc 02 §3.2). |
| Empty state | "No customers yet — add manually or they'll appear after first checkout". |
| Loading state | Row skeletons; import shows per-stage progress. |
| Error state | Import commit partial failure → downloadable per-row error CSV + "retry failed rows". |
| Responsive | Card list below `lg`; import wizard desktop-recommended notice below `md`. |

### UI-B-06 · Orders

| Field | Detail |
|---|---|
| Purpose | Operate this tenant's orders: status flow, fulfillment hand-off, cancellations, refund requests. |
| User roles | Reseller, Sub-Reseller, Employee (per grant), Sales Agent (read). |
| Navigation | Sidebar → Sell → Orders. |
| Main components | Table: order #, customer, items count, total (INR), payment status, fulfillment status, placed at; status badge vocabulary per charter enumerations (pending, paid, processing, fulfilled, partially_refunded, refunded, cancelled, on_hold); row detail page: line items, payment timeline, fulfillment timeline, invoices, notes; bulk status update bar. |
| Forms & fields | Manual order creation: customer picker, product/variant picker with price, discount field (permission-checked), tax auto-compute (doc 07), payment terms (invoice later / pay link); cancel form: reason select + note; refund request form: amount, reason ≥ 20 chars → routes to central Finance (doc 02 (b)). |
| Buttons & actions | Open; Mark processing/fulfilled (confirm for bulk); Cancel; Request refund; Resend invoice; Copy pay link; Export. |
| Validation rules | Manual order requires ≥ 1 line item; discount ≤ tenant max discount setting; cancel blocked after fulfillment (must use refund flow); status transitions validated against state machine in doc 03. |
| Permissions | `orders.view/create/update/cancel` per grant; refund request uses `orders.update` + server-side request routing. |
| Empty state | "No orders yet — they appear when customers check out". |
| Loading state | Row skeletons; bulk updates show per-row completion states. |
| Error state | Payment-status drift (webhook late) shows "payment confirmed, refreshing…" auto-retry ≤ 3 then manual refresh banner. |
| Responsive | Card list below `lg`; detail page timeline vertical below `md`. |

### UI-B-07 · Leads & Pipeline

| Field | Detail |
|---|---|
| Purpose | CRM kanban + list for leads captured by sales agents and storefront forms (doc 12). |
| User roles | Sales Agent, Reseller, Sub-Reseller, Employee (per grant). |
| Navigation | Sidebar → Sell → Leads. |
| Main components | View toggle — Kanban (columns per `leads.stage`: new/contacted/qualified/proposal/won/lost, WIP count per column, drag-and-drop with keyboard alternative "Move to…" menu) and Table; lead card: name, company, value estimate, source, age, owner avatar; lead detail drawer: contact fields, activity timeline (`lead_activities`), linked quotations. |
| Forms & fields | New lead: name, phone/email, source (select), value estimate, notes; activity forms: call note, follow-up task (due date), stage-change comment. |
| Buttons & actions | Add lead; Drag/Move stage; Assign owner (owner-holders only); Convert to customer (creates `customers` row + link); Create quotation (→ UI-B-08); Archive lost. |
| Validation rules | Stage transitions skip-ahead > 1 column requires comment; follow-up date ≥ today; convert blocked if email already a customer (offers link). |
| Permissions | `crm.view/leads_manage`; assignment requires owner-equivalent grant. |
| Empty state | "No leads yet — add manually or connect storefront capture" + capture form link. |
| Loading state | Kanban columns skeleton (fixed card heights); drag shows optimistic move with rollback toast. |
| Error state | Concurrent stage change → card snaps back with "moved by <user>" toast. |
| Responsive | Kanban → vertical stage accordions below `md`; table → cards. |

### UI-B-08 · Quotations

| Field | Detail |
|---|---|
| Purpose | Build, approve, send, and convert quotations (doc 12) with discount-threshold governance. |
| User roles | Sales Agent (create/edit/send), Reseller/Sub-Reseller (approve above threshold), Employee (per grant). |
| Navigation | Sidebar → Sell → Quotations. |
| Main components | Table: quote #, customer, amount, discount %, status (draft/sent/approved/rejected/expired/converted), valid until, owner; editor: line-item builder (product picker or custom item, qty, unit price, discount %, tax), terms block, notes, PDF preview pane; approval banner when threshold exceeded. |
| Forms & fields | Customer picker, items, discount %, validity days (1–120), payment terms select, terms & conditions text (saved templates). |
| Buttons & actions | Save draft; Send by email; Download PDF (white-label per doc 10); Approve (holders of `crm.quotations_approve`); Request approval (agents); Convert to order (creates paid/pending order per doc 03 flow); Duplicate. |
| Validation rules | ≥ 1 line item; discount ≤ 100 %; total > ₹0; approval required when total > tenant threshold (default ₹25,000 — doc 02 Assumption 4) or discount > tenant max; expired quotes cannot convert. |
| Permissions | `crm.quotations_view/manage`, approval `crm.quotations_approve`. |
| Empty state | "No quotations — create one from a lead or blank". |
| Loading state | Editor skeleton; PDF preview lazy-loads with "Generating…" state. |
| Error state | Email send failure → draft preserved + "Copy email link" fallback. |
| Responsive | Editor single-column below `xl`; preview full-screen modal below `md`. |

### UI-B-09 · Wallet & Payout Requests

| Field | Detail |
|---|---|
| Purpose | View wallet balances/transactions and request manual payouts (charter §7.8). |
| User roles | Reseller, Sub-Reseller (own tenant wallet), Finance of tenant = owner/staff per grant. |
| Navigation | Sidebar → Finance → Wallet & payouts. |
| Main components | Balance cards (available / pending / commission — derived, doc 02 charter §7.2 projection), transaction table (`wallet_transactions`: date, type, amount ±, balance-after, reference link to order/payout/commission), payout request form card, payout history table (requested, approved, paid, rejected + reason). |
| Forms & fields | Payout request: amount (≤ available, min threshold shown live), method (bank/UPI), destination (from saved details or new: account holder, IFSC, account no/UPI VPA), purpose declaration checkbox. |
| Buttons & actions | Request payout (confirm modal with amount + destination summary); Manage bank details (audited change, 48 h hold flag shown to Finance); Download statement (CSV); Open reference rows. |
| Validation rules | Amount ≥ min threshold and ≤ available at submit; IFSC `^[A-Z]{4}0[A-Z0-9]{6}$`; VPA regex `^[a-zA-Z0-9._-]+@[a-zA-Z]+$`; account number 9–18 digits; new details trigger 48 h review window notice. |
| Permissions | `wallets.view/export`, `payouts.view/request/bank_details_manage`. |
| Empty state | "No transactions yet — earnings appear after your first paid order". |
| Loading state | Balance cards skeleton; request submit two-step (validating balance → creating request). |
| Error state | Balance-changed race → inline refresh notice; request rejected state shows Finance reason verbatim. |
| Responsive | Cards 3→1 columns; tables → card lists below `md`. |

### UI-B-10 · Commissions

| Field | Detail |
|---|---|
| Purpose | Understand earned commissions: own sales share, subtree shares, hold/release timeline (doc 08). |
| User roles | Reseller, Sub-Reseller, Employee (read per grant), Sales Agent (own-linked read). |
| Navigation | Sidebar → Finance → Commissions. |
| Main components | Summary strip (pending, available, released MTD, clawback MTD); table: commission ID, order, product, my level chip (Seller/Parent/Grandparent), rate, amount, hold release date, state; expandable split explainer per order ("Total pool 20% → seller 50% / parent 30% / grandparent 20%" per charter §7.5); subtree view for parents (aggregates only). |
| Forms & fields | Filters only (state, date, product). |
| Buttons & actions | Open order; Export; "Why pending?" helper popover per row (hold days remaining / fulfillment gate unmet). |
| Validation rules | None user-input beyond filters; explainer renders only platform-configured splits (read-only). |
| Permissions | `commissions.view/export`; subtree aggregates need `resellers.hierarchy_view`. |
| Empty state | "No commissions yet — earned on paid, eligible orders". |
| Loading state | Summary + row skeletons. |
| Error state | Clawback notice banner when selected order refunded ("commission reversed — see wallet"). |
| Responsive | Split explainer becomes tooltip below `lg`; tables → cards below `md`. |

### UI-B-11 · Storefront CMS

| Field | Detail |
|---|---|
| Purpose | White-label storefront content: pages, menus, media, blog/KB articles (doc 10). |
| User roles | Reseller, Sub-Reseller, Employee (per grant). |
| Navigation | Sidebar → Storefront → Pages & content. |
| Main components | Tabs — Pages (`website_pages`: title, slug, status, template, updated), Menus (nested drag-drop tree builder), Media (`media_library` grid with search/type filter/usage count), Blog & KB (article list with publish states); page editor: section-based blocks (hero, features, pricing table, FAQ, form, rich text) with live preview pane at desktop/tablet/mobile widths. |
| Forms & fields | Page: title, slug, SEO (meta title ≤ 60 chars, description ≤ 160), template select, section blocks editor; menu: label, URL/internal target, order, parent; media: file upload with alt text required. |
| Buttons & actions | New page; Publish/Unpublish; Duplicate; Preview (opens storefront with draft token); Save draft (autosave 30 s); Set homepage (one page only, confirm swaps). |
| Validation rules | Slug unique per tenant + reserved-name blocklist (checkout, cart, api, admin, portal); media ≤ 25 MB, MIME allowlist; menu internal targets must resolve to existing pages; alt text required before publish (a11y gate). |
| Permissions | `cms.*` per grant. |
| Empty state | "No pages yet — start from a template" (template gallery: About, Services, Contact, Blog index). |
| Loading state | Editor section skeletons; media uploads per-file progress. |
| Error state | Slug conflict inline; publish failure lists failing blocks (e.g., image missing). |
| Responsive | Preview pane collapses to toggle below `xl`; media grid 6→3→2 columns. |

### UI-B-12 · Domains & Branding

| Field | Detail |
|---|---|
| Purpose | White-label identity: logo, colors, favicon, custom domain + SSL state (doc 10). |
| User roles | Reseller, Sub-Reseller (owner-level; employee grant possible). |
| Navigation | Sidebar → Storefront → Branding & domain. |
| Main components | Branding panel: logo uploader (SVG/PNG with live 3 placements preview: sidebar, storefront header, invoice), favicon uploader, color pickers bound to `--sf-*` variables (§2.10) with live storefront preview iframe, font picker, banner text; Domain panel: current `*.frenchies.com` address, custom domain rows with DNS instruction cards (A/CNAME/TXT values to copy), verification + SSL status timeline (Pending DNS → Verifying → SSL issued → Active). |
| Forms & fields | As above; domain add form (hostname); maintenance-mode toggle + message (shown when tenant suspended). |
| Buttons & actions | Upload logo/favicon; Save brand; Add domain; Re-check DNS; Remove domain (typed confirm); Open storefront. |
| Validation rules | Logo ≤ 2 MB (SVG sanitized), favicon ≤ 500 KB; brand colors auto-checked for 4.5:1 text contrast (§2.10 engine warns + suggests darkened variant); domain FQDN valid, unique platform-wide, verified before SSL issuance. |
| Permissions | `settings.tenant_manage`, `cms.themes_manage`, `tenants.domains_manage` is platform-only — reseller domain actions use `settings.tenant_manage` (doc 02 §5.7). |
| Empty state | "Using default Frenchies subdomain — add your logo to start branding". |
| Loading state | Upload progress bars; DNS check shows per-step timeline advancing via polling ≤ 30 s. |
| Error state | DNS mismatch lists actual vs expected record values verbatim; SSL failure shows retry + support escalation CTA. |
| Responsive | Two panels stack below `lg`; preview iframe becomes tap-to-open below `md`. |

### UI-B-13 · Staff & Roles

| Field | Detail |
|---|---|
| Purpose | Invite staff, assign seeded or custom roles, manage support departments membership (doc 02 §8/§10). |
| User roles | Reseller, Sub-Reseller (owner; employee may hold `users.invite`). |
| Navigation | Sidebar → People → Staff. |
| Main components | Users table (name, email, role chips, departments, status, last active); Roles tab: seeded templates (employee, sales agent, support agent) as cards + custom roles (`reseller.custom.*`) with slug lists; Custom role builder: module-grouped permission list with platform-restricted rows locked and badged (doc 02 §8.2); Invitations tab (pending/accepted/expired with resend/revoke). |
| Forms & fields | Invite: email, role (template or custom), departments multi-select (if support), welcome note; custom role: name, slug (auto), permission checkboxes (curated subset), cap warnings at 40 permissions. |
| Buttons & actions | Invite staff; Edit role assignments; Create/edit/delete custom role; Resend/Revoke invitation; Deactivate user (confirm). |
| Validation rules | Email unique within tenant; blocked slugs rejected server-side with 422 "Platform restricted" (doc 02 AC-06); owner cannot remove own owner role; max 10 custom roles per tenant. |
| Permissions | `users.*` per grant; `users.roles_assign` validated against curated subset. |
| Empty state | "No staff yet — invite your team". |
| Loading state | Table skeletons; role builder slug list lazy-loads by module. |
| Error state | Restricted-slug attempt inline error with link to doc of what resellers can grant; invite conflict (email exists in tenant) offers existing-user role edit. |
| Responsive | Card list below `lg`; role builder single-column below `md`. |

### UI-B-14 · Affiliates

| Field | Detail |
|---|---|
| Purpose | Run this tenant's affiliate program: applications, rates, links oversight, performance (doc 08). |
| User roles | Reseller, Sub-Reseller (owner-level). |
| Navigation | Sidebar → People → Affiliates. |
| Main components | Tabs — Affiliates (table: name, email, status, rate type flat/%, rate value, clicks 30 d, conversions 30 d, earned, joined), Program settings (default rate, cookie window days, terms text, landing page select), Payout oversight (affiliate payout requests routed to central Finance — read mirror). |
| Forms & fields | Add/invite affiliate: name, email, rate type, rate value, cookie window override; program settings form; approve/reject dialog with note. |
| Buttons & actions | Invite; Approve/Decline application; Suspend/Reactivate (confirm); Edit rate (effective date); Copy affiliate registration link. |
| Validation rules | Rate % 0–100; flat ≥ ₹0; cookie window 1–90 days; suspending affiliate with pending commissions shows clawback-freeze notice. |
| Permissions | `affiliates.*` within own tenant. |
| Empty state | "No affiliates yet — share your registration link". |
| Loading state | Tab skeletons; approve shows optimistic badge with rollback toast. |
| Error state | Program settings conflict shows reload banner on concurrent edit. |
| Responsive | Card list below `lg`. |

### UI-B-15 · Support Tickets (reseller view)

| Field | Detail |
|---|---|
| Purpose | Handle this tenant's customer tickets with the agent tooling (also reachable in Interface F). |
| User roles | Reseller, Sub-Reseller, Employee/Support Agent (per department grants). |
| Navigation | Sidebar → Support → Tickets. |
| Main components | Same queue + detail components as UI-F-01/F-02 scoped to this tenant's `support_departments`; SLA countdown chips; assigned-to-me tab default for agents. |
| Forms & fields | As Interface F (composer, internal notes, macros). |
| Buttons & actions | Reply, note, assign, close, escalate (per slugs); create ticket on behalf of customer. |
| Validation rules | Reply ≥ 1 char after trim; attachment ≤ 10 MB × 5; close blocked with open customer-side reply window? (no — close allowed, reopening by customer within 72 h auto-reopens). |
| Permissions | `support.tickets_view/reply/assign/close/notes_manage` + department membership. |
| Empty state | "No tickets — customers can reach you via the portal or contact page". |
| Loading state | Queue skeleton; composer autosends draft to server every 20 s ("Draft saved"). |
| Error state | Send failure preserves composer content + retry (never lose typed reply). |
| Responsive | Desktop table → card queue below `md`; detail single column. |

### UI-B-16 · Fulfillment Jobs

| Field | Detail |
|---|---|
| Purpose | Execute this tenant's fulfillment tasks: manual tasks + API-driver provisioning + events (doc 09). |
| User roles | Reseller, Sub-Reseller, Employee (per grant). |
| Navigation | Sidebar → Sell → Fulfillment. |
| Main components | Kanban/list by status (queued/running/done/failed) with SLA age chips; job card: order ref, product, driver (manual/API/license), age; job detail: required actions checklist, provisioning instructions (rich text from product), API driver log output, related license, event timeline (`fulfillment_events`). |
| Forms & fields | Complete manual job: outcome note, attachments (delivery proof), license key (auto-pulled from issuer or manual entry); retry failed API job: shows provider error + corrected-credentials option. |
| Buttons & actions | Start; Complete; Retry (failed API); Reassign (owner); Issue license (→ UI-B-17); Open order. |
| Validation rules | Complete requires outcome note ≥ 10 chars when driver = manual; manual key entry validated against license format or marked "external". |
| Permissions | `fulfillment.view/jobs_manage`, `licenses.issue`. |
| Empty state | "No fulfillment jobs — created automatically on paid orders". |
| Loading state | Kanban skeletons; API log stream shows live-append spinner. |
| Error state | Failed jobs pinned with provider error snippet + retry; > 3 failures flag "needs attention" for owner. |
| Responsive | List view below `md`. |

### UI-B-17 · Licenses

| Field | Detail |
|---|---|
| Purpose | Manage license keys issued to this tenant's customers: issue, activate, revoke (doc 09). |
| User roles | Reseller, Sub-Reseller, Employee (per grant). |
| Navigation | Sidebar → Sell → Licenses. |
| Main components | Table: key (masked, reveal = audited), product, customer, activations (x of max), status (active/expired/revoked), issued, expires; detail drawer: activation list (device/domain, IP, activated at), usage chart; bulk-issue panel (CSV of customers or group). |
| Forms & fields | Issue: customer, product variant, max activations (1–10), validity (days/unlimited), note; bulk issue: CSV upload with dry-run; revoke: reason ≥ 10 chars. |
| Buttons & actions | Issue; Revoke (typed confirm); Deactivate activation; Copy key (audited); Export. |
| Validation rules | Customer must have matching paid order OR staff override flag with note; max activations ≤ product cap; duplicate bulk rows skipped with report. |
| Permissions | `licenses.view/issue/revoke/deactivate`. |
| Empty state | "No licenses issued". |
| Loading state | Row skeletons; bulk issue per-row progress report. |
| Error state | Revoke failure (already revoked) shows idempotent "already revoked" info, not error. |
| Responsive | Card list below `lg`. |

### UI-B-18 · Marketing & Coupons

| Field | Detail |
|---|---|
| Purpose | Grow sales: coupons, campaigns, promo assets for own storefront (docs 10/12). |
| User roles | Reseller, Sub-Reseller (owner-level), Employee (per grant, view possible). |
| Navigation | Sidebar → Growth → Marketing. |
| Main components | Tabs — Coupons (`coupons`: code, type %/flat, value, usage cap/used, per-customer cap, product scope, window, status), Campaigns (email campaigns: name, audience segment, status draft/scheduled/sent, open/click stats), Assets (`marketing.assets_manage`: banners/promo images bound to CMS blocks). |
| Forms & fields | Coupon: code (auto-suggest), type, value, cap, window dates, applicable products/groups, stacking policy select; Campaign: name, audience (all customers / group / segment), subject line, preheader, body blocks, schedule datetime. |
| Buttons & actions | Create coupon; Pause/expire; Duplicate; Launch campaign (confirm shows audience count); Preview email (inbox-simulator screenshot); Upload asset. |
| Validation rules | Coupon code `^[A-Z0-9_-]{3,30}$` unique & not reserved (FRENCHIES*); value ≤ 100 % for % type; window end > start; campaign requires ≥ 1 recipient after segmentation (else blocked with "audience empty"). |
| Permissions | `billing.coupons_manage`, `marketing.*` per grant. |
| Empty state | "No coupons — create one to boost first sales". |
| Loading state | Tab skeletons; campaign stats load async with "stats populate within 1 h of send" caption. |
| Error state | Email provider bounce-rate > 5% shows warning banner on sent campaign. |
| Responsive | Cards stack below `lg`; campaign editor single-column below `xl`. |

### UI-B-19 · Invoices & Billing Documents

| Field | Detail |
|---|---|
| Purpose | Access this tenant's customer invoices and own platform-subscription invoices (doc 07). |
| User roles | Reseller, Sub-Reseller, Employee (read per grant). |
| Navigation | Sidebar → Finance → Invoices. |
| Main components | Tabs — Customer invoices (`invoices`: number, customer, order, amount, GST breakdown, status draft/issued/void/paid, issued date; PDF download), My platform invoices (subscription charges to Frenchies: plan, amount, status), Document settings summary (GSTIN, legal "sold by" line read-only preview). |
| Forms & fields | Filters (status, date, customer, GSTIN presence); reissue request note (staff action for corrected invoices, doc 07). |
| Buttons & actions | Download PDF (white-label + legal line); Reissue (permission-checked, original voided with link); Resend by email; Export. |
| Validation rules | Reissue allowed once per invoice for GST corrections within the same FY (else blocked with accountant-advice message); void blocked if payment captured (must refund first). |
| Permissions | `billing.view/invoices_manage`. |
| Empty state | "No invoices issued yet". |
| Loading state | Row skeletons; PDF generation shows per-row spinner then auto-download. |
| Error state | PDF failure → retry + HTML fallback view. |
| Responsive | Card list below `lg`. |

### UI-B-20 · Settings & API

| Field | Detail |
|---|---|
| Purpose | Tenant business settings: profile, tax, notifications, security, API keys/webhooks (doc 03). |
| User roles | Reseller, Sub-Reseller (owner; API tab owner-only). |
| Navigation | Sidebar → Settings → (Profile / Tax / Notifications / Security / API). |
| Main components | Profile: business name, GSTIN, address, support email/phone, timezone, currency (INR fixed MVP); Tax: GSTIN, place-of-supply state, inclusive/exclusive price mode, HSN defaults; Notifications: per-event email/in-app toggles with sample preview; Security: own password, MFA enrollment (TOTP QR), active sessions list with "log out other devices"; API: `api_keys` table (name, prefix masked, last used, revoke), `api_webhooks` (URL, event multi-select, secret, deliveries log with redeliver). |
| Forms & fields | As above; API key creation shows secret exactly once in a copy dialog ("You won't see this again"). |
| Buttons & actions | Save per tab; Create/revoke key; Add webhook; Test webhook (sends signed ping, shows response); Redeliver failed delivery. |
| Validation rules | GSTIN regex when present; webhook URL https + ≤ 5 webhooks per tenant; secret ≥ 32 chars auto-generated; notification toggles require ≥ 1 channel per critical event (payment received). |
| Permissions | `settings.tenant_manage`; API tab `api.keys_manage/webhooks_manage/logs_view`; security tab self-service (no special slug for own credentials). |
| Empty state | API tab: "No keys — create one to integrate your systems". |
| Loading state | Tab skeletons; secret-once dialog blocks background close accidentally (confirm on dismiss). |
| Error state | Webhook test failure shows HTTP status + response excerpt (truncated 500 chars). |
| Responsive | Single-column forms below `md`. |

## 5. Interface C — Reseller Storefront

Public, white-label surface served per host (§2.10); mobile-first (§2.12); no app
shell — its own header (logo, nav menu from `website_menus`, cart icon with count,
account icon) + footer (menus, legal links, "Sold by <reseller> · Payments processed
by Frenchies" legal line).

### UI-C-01 · Home

| Field | Detail |
|---|---|
| Purpose | Convert visitors: brand hero, featured products, trust signals, CTAs (CMS-driven per doc 10). |
| User roles | Guest (public); personalized greeting + account menu when logged-in customer. |
| Navigation | Storefront root (`https://<tenant-domain>/`). |
| Main components | Announcement banner (`--sf-banner`), hero block (CMS: headline, subtext, CTA), featured products grid (≤ 8, CMS-selected), testimonials/reviews strip, trust badges (secure payments, support hours), blog teasers (≤ 3), newsletter block (P2). |
| Forms & fields | Newsletter (P2): email only. |
| Buttons & actions | Hero CTA → product/listing; product cards → UI-C-03; "View all products". |
| Validation rules | N/A (CMS content validated in UI-B-11). |
| Permissions | Public. |
| Empty state | First-run default theme content ("Welcome to <tenant> — we're setting up our catalog"). |
| Loading state | LCP-critical: server-rendered HTML with image `loading=lazy` below fold; CLS budget 0.1 (doc 18). |
| Error state | White-label 500 page (§2.8) with reseller support contact. |
| Responsive | Hero stacks, product grid 4→2→1 columns; announcement banner marquee disabled under reduced motion. |

### UI-C-02 · Product Listing

| Field | Detail |
|---|---|
| Purpose | Browse the tenant catalog with filters and sorting. |
| User roles | Guest / logged-in customer. |
| Navigation | Header nav / home CTA / search results; URL `/products?category=&sort=`. |
| Main components | Filter sidebar/sheet (category tree, price range, type, tags), sort select (relevance, price asc/desc, newest, popular), product grid cards (image, name, badge new/sale, price with tax-mode note, rating if reviews enabled), pagination (numbered, 12/page), result count, breadcrumb. |
| Forms & fields | Filters only (URL-synced). |
| Buttons & actions | Apply/clear filters; quick-add (only for simple products without required options); open product. |
| Validation rules | Price range min ≤ max (auto-swap with notice); unknown filter values ignored silently. |
| Permissions | Public (published products only; unpublished never leak). |
| Empty state | "No products match — clear filters" or catalog-empty "New products coming soon" with contact CTA. |
| Loading state | Grid skeleton 12 cards; filter changes keep grid visible with subtle overlay. |
| Error state | Filter request failure restores previous result set + toast. |
| Responsive | Filters become bottom-sheet drawer below `md`; grid 4→2 columns. |

### UI-C-03 · Product Detail (with Demo section)

| Field | Detail |
|---|---|
| Purpose | Sell one product: content, variants, demos, pricing, FAQ, reviews, buy CTA. |
| User roles | Guest / logged-in customer. |
| Navigation | Listing/search → `/products/{slug}`. |
| Main components | Gallery (images/video lightbox), title + rating + price block (tax-mode line "Inclusive of 18% GST"), variant/option selector, quantity, Add to cart + Buy now, demo section (per doc 06: tabs for sandbox embed / demo video / screenshot carousel / live preview link with watermark), description rich text, feature list, fulfillment expectations ("Delivered within 24 h of payment"), documents (brochure PDF), FAQ accordion, related products, reviews list (moderated), support contact card. |
| Forms & fields | Variant select; quantity (1–product cap); optional order-note textarea (≤ 500 chars) added to cart line. |
| Buttons & actions | Add to cart (toast + cart badge increment, no redirect); Buy now (→ checkout with item); Start demo (opens sandbox in new tab with session watermark); Download sample. |
| Validation rules | Selected variant required before add; out-of-stock variant disabled with strikethrough; quantity ≤ per-customer cap; demo session ≤ 15 min anonymous. |
| Permissions | Public; drafts/unpublished return 404. |
| Empty state | Demo tab shows "No demo available — contact us for a walkthrough" when absent. |
| Loading state | Above-fold content server-rendered; demo iframe lazy with "Loading demo…" placeholder. |
| Error state | Demo sandbox failure → retry + fallback screenshot set; add-to-cart failure (price changed) → price-change dialog with re-accept. |
| Responsive | Gallery swipe carousel below `md`; sticky bottom buy bar (price + add to cart) on mobile. |

### UI-C-04 · Cart

| Field | Detail |
|---|---|
| Purpose | Review items, apply coupons, proceed to checkout (guest or account). |
| User roles | Guest / logged-in customer (persisted server-side per `carts`). |
| Navigation | Header cart icon → `/cart`. |
| Main components | Line-item rows (product, variant, unit price, qty stepper, remove, note), coupon field + applied-coupon chip, order summary (subtotal, discount, tax by place of supply, total — doc 07), trust footer, upsell strip (P2: addons from `product_addons`). |
| Forms & fields | Coupon code (uppercase input, apply button); quantity steppers. |
| Buttons & actions | Update cart; Remove line (undo toast 6 s); Proceed to checkout; Continue shopping. |
| Validation rules | Qty 1–cap with inline clamp message; coupon errors shown verbatim ("Expired", "Minimum order ₹2,000 not met"); price-change-on-update shows diff modal before applying. |
| Permissions | Public; cart scoped to session + account merge on login (doc 03). |
| Empty state | "Your cart is empty" + Browse products CTA. |
| Loading state | Summary skeleton; coupon apply shows inline spinner. |
| Error state | Session expiry keeps cart server-side; re-login restores with "Cart restored" toast. |
| Responsive | Summary becomes sticky accordion below `md`; remove via swipe + button. |

### UI-C-05 · Checkout

| Field | Detail |
|---|---|
| Purpose | Convert cart to paid order via Razorpay/Cashfree with GST-correct invoicing (doc 07). |
| User roles | Guest (guided account creation) / logged-in customer. |
| Navigation | Cart → `/checkout`; direct "Buy now" entry. |
| Main components | Stepper (Account → Billing → Payment → Done); Account step (login/register inline, email verification gate), Billing step (name, email, phone, GSTIN optional, address: line/city/state (place-of-supply select)/PIN/country), Payment step (gateway selector Razorpay/Cashfree, method availability, saved methods P2, T&C checkbox with links), right summary rail (items, coupon, tax split CGST/SGST/IGST, total, secure-payment badges). |
| Forms & fields | As above; state select drives tax mode; GSTIN field with live validation. |
| Buttons & actions | Continue (per-step validation); Pay ₹<exact amount> (submits, opens gateway overlay/redirect, disables button + spinner); Back preserves step data. |
| Validation rules | Email format + verification required before payment; phone E.164; PIN 6 digits IN; GSTIN regex (auto-flags B2B IGST when place of supply differs); T&C required; server re-validates price + stock atomically before payment order creation (mismatch → return to cart with specific diff). |
| Permissions | Public (payment creation server-side, idempotency key per cart). |
| Empty state | Empty cart → redirect to cart with notice. | 
| Loading state | Payment-step handoff shows full-screen "Contacting <gateway>…" with timeout fallback (15 s) to retry/choose other gateway. |
| Error state | Gateway failure → retry same payment order (idempotent); abandoned return → "Payment pending" recovery link emailed; server price-change → explicit re-accept step. |
| Responsive | Single column below `md`, summary as top accordion; gateway overlay full-screen on mobile; 320 px no horizontal scroll (WCAG 1.4.10). |

### UI-C-06 · Order Success

| Field | Detail |
|---|---|
| Purpose | Confirm payment, hand off to portal, set fulfillment expectations. |
| User roles | Customer (post-payment). |
| Navigation | Checkout terminal → `/order/{ref}/success` (refresh-safe, never re-charges). |
| Main components | Success icon + heading, order reference + copy button, amount paid + method, invoice download (when issued), "What happens next" timeline (fulfillment SLA, license delivery, email confirmations), portal CTA, support link, upsell strip (P2). |
| Forms & fields | None. |
| Buttons & actions | Download invoice; Go to my products (portal); Continue shopping; Email receipt resend. |
| Validation rules | Route shows paid state only for verified payment (webhook-confirmed); pending shows polling state (below). |
| Permissions | Owner of the order only (tokenized link for guests, 30-day expiry). |
| Empty state | N/A (unknown ref → 404 with search). |
| Loading state | If webhook pending: auto-poll 5 s × 12 with honest "Confirming payment…" state, then "Check email for confirmation". |
| Error state | Payment failed → "Payment not completed" + retry-pay CTA + no order mutation. |
| Responsive | Timeline vertical; CTAs full-width below `md`. |

### UI-C-07 · Auth (Login / Register / Forgot password)

| Field | Detail |
|---|---|
| Purpose | Customer identity for portal access (doc 03 auth flows). |
| User roles | Guest. |
| Navigation | Header "Login" → `/login`, `/register`, `/forgot-password`. |
| Main components | Centered card (white-label), logo, email+password form, social login (P3), register adds name+phone+password+T&C, forgot-password email form, reset form (token), email-verification notice page, back-to-store link. |
| Forms & fields | Email, password; register: name, phone, password + confirm; reset: new password + confirm. |
| Buttons & actions | Log in; Create account; Send reset link; Resend verification email (60 s cooldown button state); Show/hide password. |
| Validation rules | Password ≥ 10 chars with strength meter (weak blocked), not in common-password blocklist (doc 17); email unique at register (enumeration-safe response: "check your email"); rate limit 5 attempts/15 min per email+IP with generic error. |
| Permissions | Public; authenticated users redirect to portal. |
| Empty state | N/A. |
| Loading state | Button spinners; redirect preserves `?redirect=` validated against same-host paths only. |
| Error state | Invalid credentials → identical generic message for unknown-email and wrong-password; locked-out → "Try again in N minutes". |
| Responsive | Card full-width with 16 px gutters below `sm`; 44 px touch targets. |

### UI-C-08 · Contact

| Field | Detail |
|---|---|
| Purpose | Capture presales/support inquiries into the tenant's CRM/ticket system (doc 12). |
| User roles | Guest / logged-in customer. |
| Navigation | Footer/nav "Contact" → `/contact`. |
| Main components | Contact form (name, email, phone optional, topic select presales/support/billing, message ≥ 20 chars, reCAPTCHA P2), business details card (from tenant settings: email, phone, hours, address), optional map embed, WhatsApp click-to-chat when enabled (doc 03). |
| Forms & fields | As above; prefill name/email when logged in (read-only fields shown with "from your account" hint). |
| Buttons & actions | Send message; Copy email; WhatsApp chat. |
| Validation rules | Email valid; message 20–2,000 chars; honeypot field for spam; ≤ 3 submissions/hour per IP. |
| Permissions | Public (writes create a lead or ticket per tenant routing setting). |
| Empty state | N/A. |
| Loading state | Submit spinner → success panel replaces form ("Message sent — we reply within <SLA>"). |
| Error state | Send failure preserves content + retry; duplicate detection ("You recently sent a similar message"). |
| Responsive | Form single-column; details card above form below `md`. |

### UI-C-09 · Blog / KB Public Pages

| Field | Detail |
|---|---|
| Purpose | SEO/content marketing and self-service help from CMS articles (doc 10). |
| User roles | Guest / customer. |
| Navigation | Footer/nav "Blog"/"Help" → `/blog`, `/help`, article pages. |
| Main components | Index: article cards (title, excerpt, date, category chip, read-time), category filter, pagination, search box (public KB only); Article: title, meta, rich content with anchored headings, table of contents ≥ 5 headings, author/date, share buttons, "Was this helpful?" thumbs (P2), related articles, contact CTA. |
| Forms & fields | Search box (min 2 chars). |
| Buttons & actions | Open article; filter; search; helpful vote. |
| Validation rules | Search ≤ 100 chars; helpful vote one per session. |
| Permissions | Public (`published` pages only, tenant-scoped). |
| Empty state | "No articles yet — check back soon" + contact CTA. |
| Loading state | List skeleton; article server-rendered for SEO (meta/OG tags). |
| Error state | Unknown slug → white-label 404 (§2.8). |
| Responsive | Cards 3→1 columns; TOC collapses to dropdown below `md`. |

### UI-C-10 · Legal & Policy Pages

| Field | Detail |
|---|---|
| Purpose | Render mandatory legal content: terms, privacy, refund/cancellation policy, shipping/delivery (GST compliance, doc 07). |
| User roles | Guest / customer. |
| Navigation | Footer links → `/terms`, `/privacy`, `/refunds`, `/shipping`. |
| Main components | Prose layout with anchored side-TOC (≥ `lg`), last-updated date, reseller legal entity block, platform payments disclosure line (non-removable, §2.10), print stylesheet. |
| Forms & fields | None. |
| Buttons & actions | Anchor links; print. |
| Validation rules | N/A (content authored in UI-B-11; policy pages cannot be unpublished while payments enabled — CMS enforces). |
| Permissions | Public. |
| Empty state | Default platform template text used when reseller hasn't customized (never blank). |
| Loading state | Server-rendered. |
| Error state | White-label 404. |
| Responsive | TOC becomes top dropdown below `lg`. |

## 6. Interface D — Customer Portal

White-labeled app shell (§2.1) with tenant branding; customer sees only own rows
(doc 02 AC-08).

### UI-D-01 · Portal Dashboard

| Field | Detail |
|---|---|
| Purpose | Account home: active products, upcoming renewals, open tickets, recent invoices. |
| User roles | Customer. |
| Navigation | `/portal` (login gate). |
| Main components | Welcome header (name, tenant brand), KPI cards (active products/services, next renewal date+amount, open tickets, unpaid invoices), recent orders list (5), quick actions, announcement slot (reseller CMS). |
| Forms & fields | None. |
| Buttons & actions | Deep-links to UI-D-02…08; "Renew now" fast-path when renewal < 7 days. |
| Validation rules | N/A. |
| Permissions | `customer.buyer` + ownership filters. |
| Empty state | "Welcome! Your account is new — browse products or contact support" with CTAs. |
| Loading state | Card skeletons. |
| Error state | Per-card error + Retry. |
| Responsive | Cards 3→2→1 columns. |

### UI-D-02 · My Products & Services

| Field | Detail |
|---|---|
| Purpose | List everything the customer owns: licenses, subscriptions, service deliverables with status and actions (doc 09). |
| User roles | Customer. |
| Navigation | Portal sidebar → My products. |
| Main components | Card/table toggle: product, type chip (license/subscription/service), status (active/expiring/deactivated), key masked, next event (renewal/expiry), quick actions; expandable detail: activation count, latest fulfillment notes, related order/invoice links. |
| Forms & fields | None on list; actions route to UI-D-03. |
| Buttons & actions | Manage (→ detail); Renew (→ checkout with saved item); Download deliverable; Deactivate device (confirm). |
| Validation rules | N/A client-side beyond confirmations. |
| Permissions | `licenses.view`, `subscriptions.view` + ownership. |
| Empty state | "No products yet — your purchases appear here after payment". |
| Loading state | Card skeletons. |
| Error state | Degraded license-service call shows last-known state with "as of hh:mm" caption. |
| Responsive | Cards single-column below `md`. |

### UI-D-03 · License Detail & Activation

| Field | Detail |
|---|---|
| Purpose | Inspect one license: key, activations, device management (doc 09). |
| User roles | Customer. |
| Navigation | My products → row click. |
| Main components | Key block (masked, reveal/copy with audit note), status + validity, max/current activations, activation table (device/domain, IP, activated at, deactivate button), activation instructions per product, support shortcut. |
| Forms & fields | Activation code entry (if offline activation), device name label (optional, ≤ 40 chars). |
| Buttons & actions | Reveal key (re-mask 15 s); Copy key; Activate device; Deactivate (immediate + confirm for maxed seats: "Deactivating frees a seat"). |
| Validation rules | Activation blocked at max seats with clear message; revoked key shows read-only state with reason. |
| Permissions | `licenses.view/activate/deactivate` + ownership. |
| Empty state | N/A (reached from list). |
| Loading state | Button spinners; deactivation optimistic with rollback. |
| Error state | Key-service timeout → retry without page reload. |
| Responsive | Key block wraps; table → stacked rows below `md`. |

### UI-D-04 · Orders

| Field | Detail |
|---|---|
| Purpose | Customer's order history with status, invoices, reorder, support. |
| User roles | Customer. |
| Navigation | Portal sidebar → Orders. |
| Main components | Table/cards: order #, date, items, total, status badge (charter vocabulary), invoice link, fulfillment status; detail view: line items, status timeline, invoice download, related tickets, reorder button for one-time products. |
| Forms & fields | Filters (status, date). |
| Buttons & actions | Open; Download invoice; Reorder; Request help (pre-fills ticket with order context); pay pending invoice (if `pending`). |
| Validation rules | Reorder skips discontinued items with notice listing skips. |
| Permissions | `orders.view` + ownership. |
| Empty state | "No orders yet" + Browse products CTA. |
| Loading state | Row skeletons. |
| Error state | Payment retry for pending orders reuses idempotent flow (UI-C-05). |
| Responsive | Card list below `md`. |

### UI-D-05 · Invoices

| Field | Detail |
|---|---|
| Purpose | GST invoices: view/download/pay (doc 07). |
| User roles | Customer. |
| Navigation | Portal sidebar → Invoices. |
| Main components | Table: number, date, order ref, amount, GSTIN breakdown summary, status (issued/paid/void), download; invoice preview modal (HTML render of PDF layout incl. "Sold by" legal line); GST details panel for their records. |
| Forms & fields | Filters; "Update GSTIN for future invoices" shortcut (→ UI-D-09). |
| Buttons & actions | Download PDF; Pay now (unpaid); Email me copy. |
| Validation rules | Void invoices non-actionable, shown greyed with tooltip. |
| Permissions | `billing.view` + ownership. |
| Empty state | "No invoices yet". |
| Loading state | PDF spinner per row. |
| Error state | PDF failure → HTML fallback view + retry. |
| Responsive | Card list below `md`; preview full-screen. |

### UI-D-06 · Subscriptions

| Field | Detail |
|---|---|
| Purpose | Manage recurring purchases: renew, cancel, resume, payment method for renewals (doc 03). |
| User roles | Customer. |
| Navigation | Portal sidebar → Subscriptions. |
| Main components | Cards: plan/product, price + cycle, status (active/past_due/cancelled at date/grace), next renewal date + amount, auto-renew toggle state; detail drawer: renewal timeline, invoice history, cancellation survey (optional). |
| Forms & fields | Cancel: reason select + optional note; pause: duration select (1/2/3 months, if tenant allows). |
| Buttons & actions | Renew now; Cancel (confirm modal: "Access continues until <date>; you will not be charged"); Resume (before end date); Change payment at next renewal (info). |
| Validation rules | Cancel scheduled at cycle end only (no partial refund unless tenant policy allows — shows policy text from UI-C-10); pause ≤ 3 months. |
| Permissions | `subscriptions.manage` + ownership. |
| Empty state | "No active subscriptions". |
| Loading state | Card skeletons; actions spinner + state badge update. |
| Error state | Renewal payment failure shows past_due banner + Pay retry. |
| Responsive | Cards single-column below `md`. |

### UI-D-07 · Support Tickets

| Field | Detail |
|---|---|
| Purpose | Customer-facing ticket lifecycle: create, converse, rate (doc 12). |
| User roles | Customer. |
| Navigation | Portal sidebar → Support. |
| Main components | Ticket list (subject, dept, status, last update, SLA countdown for first response on new); new-ticket form (department select, subject, body, attachments, related order/product select); thread view: chronological messages (customer vs agent styling), internal notes NEVER rendered (server-enforced), status header, CSAT rating panel after close. |
| Forms & fields | As above; reply box with attachment (≤ 10 MB × 5 files). |
| Buttons & actions | Submit ticket; Reply; Reopen (within 72 h of close); Rate (1–5 stars + comment); Close. |
| Validation rules | Subject 5–120 chars; body ≥ 20 chars; attachment MIME/size allowlist; reopen blocked after 72 h with "create a new ticket" CTA. |
| Permissions | `support.tickets_view/reply` + ownership. |
| Empty state | "No tickets — how can we help?" + new-ticket CTA + KB link. |
| Loading state | Thread skeleton; reply spinner preserving typed content on failure. |
| Error state | Send failure retry; upload failure per-file with remove/retry. |
| Responsive | List → thread single column; composer sticky bottom below `md`. |

### UI-D-08 · Payments History

| Field | Detail |
|---|---|
| Purpose | Gateway payment records for the customer's orders with receipts and dispute entry point (doc 07). |
| User roles | Customer. |
| Navigation | Portal sidebar → Payments. |
| Main components | Table: payment ref, order, amount, method (card/UPI/netbanking masked), gateway, status (captured/failed/refunded/partially_refunded), date; expandable: gateway reference, failure reason (verbatim gateway message), linked refund rows. |
| Forms & fields | Filters (status, date range ≤ 12 months). |
| Buttons & actions | Retry failed payment for pending order; Download receipt; Raise dispute (creates billing ticket prefilled). |
| Validation rules | Retry only for unpaid orders (else blocked with "already paid" info). |
| Permissions | `payments.view` + ownership. |
| Empty state | "No payments yet". |
| Loading state | Row skeletons. |
| Error state | Gateway status fetch failure shows last-synced state + age. |
| Responsive | Card list below `md`. |

### UI-D-09 · Profile & Security

| Field | Detail |
|---|---|
| Purpose | Customer identity, business/billing details, password/MFA, sessions, communication prefs (doc 17). |
| User roles | Customer. |
| Navigation | Portal sidebar → Profile. |
| Main components | Tabs — Profile (name, phone, billing address, GSTIN, company name), Security (password change, MFA TOTP enrollment with QR + recovery codes, active sessions with device/IP/last-seen + logout-others), Notifications (per-event email/in-app toggles), Danger (delete request → verification flow per policy). |
| Forms & fields | As above; GSTIN live regex; address with place-of-supply impact note ("Changing state affects future tax"). |
| Buttons & actions | Save; Enable/Disable MFA; Regenerate recovery codes (confirm, old invalid); Log out other devices; Submit delete request. |
| Validation rules | Current password required for password change and MFA changes; phone E.164; GSTIN regex; delete request requires typed "DELETE" + recent-login check (< 15 min). |
| Permissions | Self-service only (+ `customers.update` own row, doc 02 (g)). |
| Empty state | N/A (defaults shown). |
| Loading state | Tab skeletons; saves inline spinner + success toast. |
| Error state | Password policy failures inline with exact requirement text. |
| Responsive | Tabs scroll below `md`; forms single-column. |

## 7. Interface E — Affiliate Dashboard

White-labeled compact shell (§2.1) with 5 nav items; scoped to one affiliate program
(doc 08, doc 02 §5.11).

### UI-E-01 · Affiliate Dashboard

| Field | Detail |
|---|---|
| Purpose | Performance snapshot: clicks, conversions, earnings, payout state. |
| User roles | Affiliate. |
| Navigation | `/affiliate` (login gate). |
| Main components | KPI cards (clicks 30 d, conversions 30 d, conversion rate, earnings 30 d, available balance, pending balance); trend chart (clicks vs conversions, 30 d); latest conversions list (5); payout status chip; program notice banner. |
| Forms & fields | Date preset (7/30/90 days). |
| Buttons & actions | Cards deep-link to UI-E-02/03/04; "Create link" quick action. |
| Validation rules | Range ≤ 90 days. |
| Permissions | `affiliates.view`, `reports.view`, `commissions.view` + row ownership (AC-15). |
| Empty state | "No clicks yet — share your link to get started" + link generator CTA. |
| Loading state | Card + chart skeletons. |
| Error state | Per-card error + Retry. |
| Responsive | Cards 3→2→1 columns. |

### UI-E-02 · Links & Coupons

| Field | Detail |
|---|---|
| Purpose | Generate referral links and coupons; track per-link performance (doc 08). |
| User roles | Affiliate. |
| Navigation | Affiliate sidebar → Links & codes. |
| Main components | Links table (name, target page select, URL with copy, clicks, conversions, earnings); coupon list (code, value, uses, status); QR code per link (SVG, downloadable). |
| Forms & fields | New link: name (≤ 40), target (product/page select from program catalog), sub-ID (optional, `^[a-z0-9_-]{1,20}$`); coupon request: code auto-suggest, campaign note. |
| Buttons & actions | Create; Copy link (toast "Copied"); Download QR; Pause link (own); Request coupon (tenant approval). |
| Validation rules | ≤ 20 active links per affiliate; target must be published; sub-ID unique per affiliate. |
| Permissions | `affiliates.links_manage` + ownership. |
| Empty state | "No links yet — create your first referral link". |
| Loading state | Row skeletons; QR lazy-generation spinner. |
| Error state | Copy failure fallback manual-select; coupon request declined shows tenant note. |
| Responsive | Table → cards with copy button below `md`. |

### UI-E-03 · Conversions

| Field | Detail |
|---|---|
| Purpose | Transparent conversion ledger: clicks → orders → commission with attribution (doc 08). |
| User roles | Affiliate. |
| Navigation | Affiliate sidebar → Conversions. |
| Main components | Summary strip (clicks, orders, approved commissions, rejected count with reasons); table: date, order ref (masked customer), amount, commission, status (pending/approved/rejected/clawed back), reject reason verbatim; click log table (date, link, referrer country, device — no PII). |
| Forms & fields | Filters (status, date, link). |
| Buttons & actions | Export own data; Open linked order summary (amount/status only — no customer contact data, doc 02 §5.11). |
| Validation rules | N/A. |
| Permissions | `orders.view` (summary scope), `commissions.view`, `reports.view` + ownership. |
| Empty state | "No conversions recorded yet". |
| Loading state | Row skeletons. |
| Error state | Clawed-back rows explain linkage ("order #X refunded") without PII. |
| Responsive | Card list below `md`. |

### UI-E-04 · Payouts & Requests

| Field | Detail |
|---|---|
| Purpose | Request payouts and track approval/payment states (charter §7.8). |
| User roles | Affiliate. |
| Navigation | Affiliate sidebar → Payouts. |
| Main components | Balance card (available / pending / minimum-threshold progress bar); request form card; history table (amount, method masked, requested, status requested/approved/paid/rejected + reason). |
| Forms & fields | Amount, method (bank/UPI from saved details). |
| Buttons & actions | Request payout (confirm modal); Manage bank details (→ UI-E-05). |
| Validation rules | Amount ≥ threshold (progress bar shows gap: "₹X more to reach ₹Y threshold") and ≤ available; new bank details 48 h hold notice. |
| Permissions | `payouts.request/view/bank_details_manage` + ownership. |
| Empty state | "No payouts yet". |
| Loading state | Form spinner; balance refresh after request. |
| Error state | Rejection shows verbatim reason + "Update details & re-request" CTA. |
| Responsive | Stacked single-column below `md`. |

### UI-E-05 · Payment Details & Profile

| Field | Detail |
|---|---|
| Purpose | Bank/UPI destination, tax identity, password, notification prefs (doc 17). |
| User roles | Affiliate. |
| Navigation | Affiliate sidebar → Profile & payment details. |
| Main components | Bank details card (masked, change history with 48 h hold badges), tax card (PAN, GSTIN optional), security card (password change, active sessions), notification toggles (payout updates, program news). |
| Forms & fields | Account holder name, account number + confirm field, IFSC, or VPA; PAN regex; password change with current password. |
| Buttons & actions | Save; Change details (confirm modal: "New details are held for 48 h for security"); Log out other devices. |
| Validation rules | Account number + confirm must match; IFSC/VPA regex (doc 02/UI-B-09); PAN `^[A-Z]{5}[0-9]{4}[A-Z]$`; holder name matches tax name when PAN provided (warning, not block). |
| Permissions | Self-service (+ `payouts.bank_details_manage` own). |
| Empty state | "No payout details on file — add them before your first request". |
| Loading state | Save spinners. |
| Error state | IFSC lookup failure allows manual entry with note. |
| Responsive | Single-column. |

### UI-E-06 · Marketing Assets & Program Terms

| Field | Detail |
|---|---|
| Purpose | Tenant-provided creatives and program rules for compliant promotion (doc 08). |
| User roles | Affiliate. |
| Navigation | Affiliate sidebar → Assets & terms. |
| Main components | Asset grid (banners by size, social images, email copy blocks — each with download/copy-snippet); program terms article (rate details, cookie window, prohibited methods list, payout schedule) rendered from CMS; FAQ accordion. |
| Forms & fields | None. |
| Buttons & actions | Download asset; Copy text snippet; Open terms (anchor links). |
| Validation rules | N/A. |
| Permissions | `marketing.view` (read). |
| Empty state | "The program hasn't uploaded assets yet — ask the program owner". |
| Loading state | Grid skeletons; large asset downloads stream. |
| Error state | Missing asset file → 404 notice per tile with report-link. |
| Responsive | Grid 4→2→1 columns. |

## 8. Interface F — Support Agent Dashboard

App shell (§2.1) for central and reseller agents; department-scoped (doc 02 §5.6,
AC-09). Central agents see tenant chips on every row.

### UI-F-01 · Agent Queue

| Field | Detail |
|---|---|
| Purpose | Triage and claim tickets across granted departments/tenants (doc 12). |
| User roles | Support Agent, Support Manager. |
| Navigation | Sidebar → Queue (tabs: Unassigned, Mine, Department, Breaching SLA, All). |
| Main components | Table/board toggle: ticket #, subject, requester, tenant chip (central), department, priority badge, status, SLA countdown (color-shifts < 25% remaining), assignee avatar, last update; filters (department, priority, status, tag, sort by SLA age); saved-view selector (P2). |
| Forms & fields | Filters only; bulk assign bar (agent multi-select). |
| Buttons & actions | Claim/Assign; Open; Bulk assign; Priority change; Merge (manager). |
| Validation rules | Assignment limited to agents in the ticket's department (server-validated); merge blocked across tenants (central view). |
| Permissions | `support.tickets_view/assign` + department membership. |
| Empty state | "Queue clear — no tickets need attention". |
| Loading state | Row skeletons; SLA countdowns tick client-side from server-provided deadlines. |
| Error state | Stale-assignment race → "Already claimed by <agent>" toast with queue refresh. |
| Responsive | Card queue below `md`; board toggle desktop-only. |

### UI-F-02 · Ticket Detail

| Field | Detail |
|---|---|
| Purpose | Full conversation + tools to resolve: reply, notes, actions, context panels (doc 12). |
| User roles | Support Agent (reply/notes/close), Support Manager (escalate/SLA/reassign). |
| Navigation | Queue → row click; breadcrumbs `Tickets > #1234`. |
| Main components | Header (subject, status/priority/department selects, SLA timer, requester + tenant chips); conversation thread (public replies distinctly styled from customer messages; internal notes amber-tinted and NEVER sent to requester — server-enforced); composer (rich text light, attachments, canned-response insert, AI draft suggestion when enabled with "review before send" state); right rail: requester card, order/license quick facts (UI-F-05 preview), related tickets, tags editor, audit timeline of ticket events. |
| Forms & fields | Reply composer; note composer; fields: priority, department, tags (≤ 10, 25 chars each), custom fields per department (P2). |
| Buttons & actions | Reply (public), Add internal note, Assign to me, Escalate (manager), Close (reason select), Reopen, Request billing action (creates Finance request per doc 02 (b)). |
| Validation rules | Reply ≥ 1 char after trim, ≤ 20,000 chars; close requires internal resolution note ≥ 10 chars (quality gate); escalate requires note. |
| Permissions | `support.tickets_view/reply/assign/close/notes_manage`; escalate `support.tickets_escalate`. |
| Empty state | N/A. |
| Loading state | Thread skeleton; composer autosave draft 20 s ("Draft saved"); reply spinner. |
| Error state | Send failure preserves text; AI suggestion failure hides panel silently with log entry (never blocks reply). |
| Responsive | Rail collapses below `xl` into tabs (Conversation / Context); composer sticky bottom below `md`. |

### UI-F-03 · Internal Notes & Handoff

| Field | Detail |
|---|---|
| Purpose | Collaborate without leaking internals: notes, @mentions, handoff summaries (doc 12). |
| User roles | Support Agent, Support Manager. |
| Navigation | Panel within UI-F-02 (dedicated view for managers: "Notes feed" sidebar item). |
| Main components | Notes feed (all tickets I can see, newest notes first, ticket link, author, @mention chips); note composer with @mention autocomplete (agents in same departments only); handoff button producing an auto-summary block (private) with template: issue, steps tried, next action, owner. |
| Forms & fields | Note text ≥ 2 chars, ≤ 5,000 chars; mention picker. |
| Buttons & actions | Post note; Edit own note (≤ 15 min, "edited" flag); Resolve note thread (author/manager); Create handoff summary; Copy summary. |
| Validation rules | Mentions restricted to agents sharing a department (server-validated); notes never appear in customer-visible exports/API (doc 17 test). |
| Permissions | `support.notes_manage` + ticket visibility. |
| Empty state | "No internal notes on this ticket". |
| Loading state | Feed skeleton; posts optimistic with edit window. |
| Error state | Post failure retry preserving text. |
| Responsive | Notes feed full-screen tab below `xl`. |

### UI-F-04 · Knowledge Base & Canned Responses

| Field | Detail |
|---|---|
| Purpose | Author KB articles and canned responses for fast, consistent replies (docs 12/10). |
| User roles | Support Agent (author), Support Manager (publish). |
| Navigation | Sidebar → Knowledge → Articles / Canned responses. |
| Main components | Articles list (title, status draft/published, category, updated, usage-in-tickets count); editor (title, category, rich content, related product/dept association, SEO fields when published public); canned responses list (shortcut code, title, body, usage count); insert picker inside ticket composer (UI-F-02). |
| Forms & fields | Article as above; canned: code `^[a-z0-9]{2,20}$` unique per tenant, body ≥ 10 chars. |
| Buttons & actions | New; Edit; Publish (manager; agents submit for review); Archive; Copy-to-composer from list. |
| Validation rules | Public articles require alt text on images (a11y gate) and one category; publish blocked without review when author ≠ manager. |
| Permissions | `support.kb_manage`; publish gate also role-checked. |
| Empty state | "No articles yet — document a solution you just solved". |
| Loading state | Editor skeleton; autosave 30 s. |
| Error state | Slug/title conflict inline; publish failure lists missing fields. |
| Responsive | List → cards below `md`; editor single-column. |

### UI-F-05 · Customer 360 (context lookup)

| Field | Detail |
|---|---|
| Purpose | Agent-side read-only context for a requester: account, orders, licenses, subscriptions, prior tickets (doc 03 scoping). |
| User roles | Support Agent, Support Manager (read-only). |
| Navigation | Right rail of UI-F-02 or sidebar → Search customer (global search §2.4 with customer scope). |
| Main components | Identity card (name, email, phone masked until "Reveal" with audit, tenant chip, since); tabs — Orders (last 10 with status), Licenses/Products (status, activations), Subscriptions (state, renewal), Tickets (prior 10 with outcomes), Timeline (merged activity, 90 days); action buttons per ticket context. |
| Forms & fields | Reveal-reason field (≥ 10 chars) for PII unmasking. |
| Buttons & actions | Reveal PII (audited); Open order/license (full record); Start ticket on behalf (creates with attribution). |
| Validation rules | Reveal rate-limited (20/hour/agent) with visible counter; no export of panel data (no export button rendered). |
| Permissions | `customers.view`, `orders.view`, `licenses.view`, `subscriptions.view` (read) within ticket scope; central agents see cross-tenant via department membership only. |
| Empty state | "No orders or products on this account". |
| Loading state | Tab skeletons; lazy tabs. |
| Error state | Context service failure shows "context unavailable" banner without blocking replies. |
| Responsive | Tabs scroll; panel single-column below `md`. |

### UI-F-06 · My Workload & Performance

| Field | Detail |
|---|---|
| Purpose | Agent self-view: assigned tickets, SLA adherence, response times, CSAT (doc 12). |
| User roles | Support Agent (own), Support Manager (own + team toggle). |
| Navigation | Sidebar → Me → Workload. |
| Main components | KPI cards (open assigned, first-response median today, SLA adherence 7 d, CSAT 7 d, replies sent today); assigned ticket table sorted by SLA risk; day timeline (handoff between shifts P2). |
| Forms & fields | None (date preset 7/14/30 days). |
| Buttons & actions | Open ticket; Set availability (online/away — affects auto-assign, P2). |
| Validation rules | N/A. |
| Permissions | Own metrics always; team view requires `support.reports_view`. |
| Empty state | "No assigned tickets". |
| Loading state | Card skeletons. |
| Error state | Per-card error + Retry. |
| Responsive | Cards 3→1 columns; table → cards below `md`. |

### UI-F-07 · Support Reports & SLA (Manager)

| Field | Detail |
|---|---|
| Purpose | Manage queue health: volume trends, SLA breaches, agent load, CSAT drivers, SLA policy editing (doc 12). |
| User roles | Support Manager. |
| Navigation | Sidebar → Reports → Support. |
| Main components | Charts: volume by dept/day, first-response & resolution medians, SLA breach count with top breach causes, CSAT distribution with negative-comment list, agent load bars; SLA policies table (`sla_policies`: name, response/resolution targets, business hours, dept assignments); breaches table with drill-down to tickets. |
| Forms & fields | SLA policy editor: response target (mins), resolution target (hours), business-hours schedule, priority overrides; filters/date presets on charts. |
| Buttons & actions | Edit policy (impact preview: "affects 142 open tickets"); Export; Open breach ticket. |
| Validation rules | Resolution target > response target; business hours valid ranges; policy change diff-confirmed. |
| Permissions | `support.reports_view/sla_manage`. |
| Empty state | "Not enough ticket data for the selected range". |
| Loading state | Chart skeletons; drill-downs lazy. |
| Error state | Aggregation failure per-chart retry. |
| Responsive | Charts 2→1 columns; tables → cards below `md`. |

## 9. Screen Index

| Interface | ID range | Count | Primary roles | Shell |
|---|---|---|---|---|
| A — Super Admin Panel | UI-A-01…18 | 18 | Super Admin, Platform Admin, Finance Admin, Product Manager | Platform app shell, tenant switcher |
| B — Reseller Dashboard | UI-B-01…20 | 20 | Reseller, Sub-Reseller, Employee, Sales Agent | Tenant-branded app shell |
| C — Reseller Storefront | UI-C-01…10 | 10 | Guest, Customer | Public white-label site |
| D — Customer Portal | UI-D-01…09 | 9 | Customer | White-label app shell |
| E — Affiliate Dashboard | UI-E-01…06 | 6 | Affiliate | Compact white-label shell |
| F — Support Agent Dashboard | UI-F-01…07 | 7 | Support Agent, Support Manager | Platform app shell (agents), department-scoped |

Total: 70 specified screens. Support Agent reseller-tenant usage reuses F screens
within the B shell (UI-B-15 note); every screen's permission mapping references
`02-user-roles-and-permissions.md` slugs exclusively.

## 10. Assumptions & Open Questions

1. **Assumption:** dark mode is P2 (charter §10) — tokens in §2.9 are authored so a
   dark palette can be added without component rewrites; MVP ships light only.
2. **Assumption:** storefront theme engine in MVP covers color/logo/favicon/font/banner
   (§2.10); drag-drop page builder stays P2 per charter §10; CMS blocks in UI-B-11 are
   pre-defined section types, not free-form builder.
3. **Assumption:** `en-IN` locale, INR only, IST display with per-tenant timezone
   override possible in MVP settings (multi-language P3).
4. **OQ:** saved filter views (§2.5) and board view for orders — P2 candidate; confirm
   with operations before MVP cut.
5. **Assumption:** PII masking in UI-F-05 (phone reveal with reason + audit) satisfies
   `17-security-requirements.md` intent; exact masking policy owned by doc 17.
6. **OQ:** availability toggle (auto-assign participation) in UI-F-06 is P2; MVP
   auto-assign uses round-robin over department agents.
7. **Assumption:** reCAPTCHA on UI-C-08 is P2; MVP relies on honeypot + rate limits.
8. **Assumption:** tenant-custom 403/404/500 text (§2.8) is stored in
   `tenant_settings` (key additions enumerated by `13-database-design.md`).
9. **OQ:** invoice "reissue once per FY" rule in UI-B-19 — finance to confirm with
   GST counsel (`07-billing-and-financial-architecture.md` owns final rule).

## Ratified P0 decisions (2026-11-20)

Decisions below are ratified in `22-open-questions-and-assumptions.md` §5; see that
section for ratifier, date and launch-gate caveats.

| OQ-002 | Paywall screens reflect three paid tiers + annual option; prices read from `subscription_plans`; no free tier. |
| OQ-008 | Payout request UI shows ₹1,000 minimum and fee-absorption policy before submission. |
| OQ-014 | Refund-policy copy rendered from ratified per-type windows on storefront footer and invoice. |
| OQ-028 | Legal wording slots are platform-controlled and non-editable; reseller logo/name/GSTIN inserted only in defined slots. |
