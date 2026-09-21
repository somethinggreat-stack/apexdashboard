# JARVIS API — read-only

A read-only JSON API for the owner's own assistant. It never writes, updates or deletes.

**Base URL:** `https://apexgrowthsolution.com/api/jarvis` — the **dashboard** host, not the chat
subdomain. `chat.apexgrowthsolution.com` serves only the chat and will redirect these paths away.

---

## Turning it on

```env
JARVIS_API_TOKEN=<64 hex characters>
```

Generate with `php -r "echo bin2hex(random_bytes(32));"` and put the **same** value in JARVIS's
own `.env`.

**With no token set, not one route is registered.** `php artisan route:list` shows nothing under
`api/jarvis`, so an un-configured deploy has no JARVIS surface at all — not even a route that
returns 401.

## Calling it

```
Authorization: Bearer <JARVIS_API_TOKEN>
Accept: application/json
```

The token goes in the header only. A token in the query string is **rejected** — URLs end up in
access logs and browser history.

- **Rate limit:** 60 requests/minute per IP (`JARVIS_RATE_LIMIT`).
- **Paging:** `page` (default 1), `per_page` (default 50, **max 200**). List endpoints return
  `{ data: [...], meta: { page, per_page, total } }`.
- **Timestamps:** every one is ISO 8601 UTC, e.g. `2026-09-22T04:15:00+00:00`.
- **Logging:** every request is logged to `storage/logs/jarvis.log` — route, filters, IP, time.
  The token is never logged.

---

## What this API will never return

This database holds consumers' identities. The following never leave the server, and there are
tests that fail the build if they do:

`ssn` · `date_of_birth` · street address (`current_address`, `address_line2`, `zipcode`) ·
`phone` · `email` · credit-monitoring or CFPB usernames, passwords, PINs and security answers ·
ID photo / proof-of-address / collage paths · the intake IP.

`last4_ssn` is **not** returned either — nothing here needs it.

**Allowed:** names, city, state. JARVIS has to be able to say *"Clinecea's client Dominique is
four days overdue"*.

**`business_owner_credentials` is never read, joined or referenced.** A test greps the JARVIS
source for the table name and fails if it appears in code.

**Client notes are omitted entirely.** VAs paste report details and client specifics into them,
so they cannot be treated as PII-free. Detail responses carry `"notes_omitted": true` so JARVIS
knows the field was withheld rather than empty. Flip `jarvis.expose_notes` only after auditing
them.

---

## Endpoints

### `GET /health`
```json
{ "ok": true, "app_version": "...", "db_ok": true, "server_time": "...",
  "counts": { "business_owners": 12, "end_users_active": 340 } }
```

### `GET /summary`
The morning briefing in one call. No parameters.
```json
{ "as_of": "...", "new_clients": 1, "incomplete": 566, "owners_requiring_action": 36,
  "new_client_errors": 0, "round_errors": 0, "in_progress": 0, "done": 0, "on_hold": 0,
  "rounds_due": { "overdue": 195, "due_today": 0, "due_in_3_days": 0 },
  "no_movement_14d": 0,
  "outstanding_invoices": { "count": 25, "total": 5414.00,
                            "collected_total": 0, "owners_affected": 25 },
  "new_leads_7d": 0 }
```

### `GET /alerts`
What should be noticed without being asked, **highest severity first**. Paginated like every
other list (`page`, `per_page`, `meta`).
```json
{ "data": [ { "type": "round_overdue", "severity": "high",
              "end_user_id": 1, "business_owner_id": 1,
              "label": "Dominique Johnson, 2nd Round, 10 days overdue",
              "since": "..." } ],
  "meta": { "page": 1, "per_page": 50, "total": 540 } }
```
`type`: `round_overdue` · `round_error` · `no_movement` · `new_client_error` · `invoice_overdue`.

### `GET /needs-attention`
The dashboard's Needs Attention panel, mirrored exactly — the screen Umair works from.
```json
{ "totals": { "new": 1, "incomplete": 566, "overdue": 195, "owners": 36 },
  "owners": [ { "business_owner_id": 1, "name": "Clinecea Phillips",
                "new": 0, "incomplete": 86, "overdue": 40,
                "priority": "Critical", "round_cycle_days": 30 } ],
  "meta": { "as_of": "...", "source": "App\Services\OwnerSnapshot …" } }
```
`priority` is the **literal label the screen renders** — `Critical` | `High` | `Medium` | `Low` —
and the rows come back in the panel's own order. Nothing here is cached; `meta.as_of` is the
moment it was computed.

`round_cycle_days` is included because a 20-day owner and a 30-day owner mean different things
by "9 days overdue".

### `GET /end-users`
Filters: `business_owner_id`, `status` (active|paused|graduated|cancelled),
`intake_status` (`pending_review|error|round_error|done|null`), `held` (bool),
`overdue` (bool), `no_movement_days` (int), `q`.

Each row is **exactly** these keys:
```json
{ "id": 1, "name": "...", "business_owner_id": 1, "business_owner_name": "...",
  "status": "active", "intake_status": "round_error", "bucket_label": "Round Errors",
  "current_round": "3rd Round", "current_round_started_at": "...",
  "days_left_in_round": -4, "rounds_completed": 2,
  "last_process_step_at": "...", "held_at": null, "created_at": "..." }
```
`bucket_label` is the list the team would find them in: `New Clients`, `New Client Errors`,
`Round Errors`, `Clients Done`, `On Hold`, `Sent for Approval`, `In Progress`.

`days_left_in_round` is negative when overdue, and **null when the round has not been marked
yet** — nothing is due until a round is started on the rounds strip.

### `GET /end-users/{id}`
The row above plus `rounds`, `round_selections`, `process_steps`, `score_history`,
`notes_omitted`, and `sync: { gohighlevel_id, disputefox_id }`.

`process_steps` carry disputed counts **per bureau** (`experian`/`transunion`/`equifax`), because
that is how this system records them.

### `GET /rounds/due?within_days=3`
Same row shape as `/end-users`, sorted by `days_left_in_round` ascending (most overdue first).

### `GET /invoices/outstanding`
Filter: `business_owner_id`.
```json
{ "business_owner_id": 1, "business_owner_name": "...", "amount": 1200.00,
  "collected_total": 730.00, "currency": "USD", "compensation_model": "per_round" }
```

### `GET /business-owners`
Filters: `status`, `q`. The credit-repair companies, not consumers.
Per row: `id, name, status, round_cycle_days, created_at,
end_user_counts {active,paused,graduated,cancelled}, outstanding_invoice_total, collected_total,
last_activity_at`.

`sum(outstanding_invoice_total)` across all owners equals the dashboard's **Total Outstanding**
exactly — there is a test asserting it.

### `GET /team/workload?days=7`
`[{ user_id, name, rounds_advanced, process_steps_logged, hours_logged, last_active_at }]`.
No pay rates, no payout amounts.

### `GET /leads/recent?days=7`
`[{ id, source_table, name, channel, created_at, converted }]` from `prospects`,
`prospect_leads` and `business_leads`. `channel` is WhatsApp | phone | Instagram | website form.
**No phone numbers, no email addresses.**

### `GET /activity?days=1`
`[{ at, user_name, action, subject_type, subject_id, summary }]` — the summary line only.

---

## Source register — where every figure comes from

**The rule: this API never recomputes a figure the application already computes.** Where a
dashboard screen shows a number, the endpoint returns *that* number by calling the same code the
screen calls. Each figure below is marked:

- **Panel** — produced by `App\Services\OwnerSnapshot`, the single implementation behind the
  dashboard's Needs Attention and Business Owner Balances panels. Hold the dashboard to it.
- **API** — no equivalent exists in the application; computed here, definition stated.
- **Model** — an existing Eloquent scope or accessor, the same one the app's screens use.

| Figure | Source | Produced by |
|---|---|---|
| `summary.new_clients` | **Panel** | `OwnerSnapshot::attentionTotals()['new']` |
| `summary.incomplete` | **Panel** | `OwnerSnapshot::attentionTotals()['incomplete']` — via `EndUser::$is_incomplete` |
| `summary.rounds_due.overdue` | **Panel** | `OwnerSnapshot::attentionTotals()['overdue']` |
| `summary.owners_requiring_action` | **Panel** | `OwnerSnapshot::attentionTotals()['owners']` |
| `summary.outstanding_invoices.total` | **Panel** | `OwnerSnapshot::balanceTotals()['outstanding']` → `Client::paymentTotals()['pending']` |
| `summary.outstanding_invoices.collected_total` | **Panel** | `OwnerSnapshot::balanceTotals()['collected']` |
| `summary.rounds_due.due_today` | **API** | `EndUser::$days_left_in_round === 0`. No panel equivalent — the dashboard only counts overdue. |
| `summary.rounds_due.due_in_3_days` | **API** | `EndUser::$days_left_in_round` in 1..3. No panel equivalent. |
| `summary.new_client_errors` | **Model** | `intake_status = 'error'` |
| `summary.round_errors` | **Model** | `EndUser::scopeRoundError()` |
| `summary.in_progress` | **Model** | `EndUser::scopeInProgress()->notHeld()` |
| `summary.done` | **Model** | `EndUser::scopeDone()` |
| `summary.on_hold` | **Model** | `EndUser::scopeOnHold()` |
| `summary.no_movement_14d` | **API** | no process step logged in 14 days. No panel equivalent. |
| `summary.new_leads_7d` | **API** | rows created in `prospects` + `prospect_leads` + `business_leads`. |
| `/needs-attention` (all) | **Panel** | `OwnerSnapshot::attentionRows()` / `attentionTotals()` |
| `/needs-attention[].priority` | **Panel** | `OwnerSnapshot::priority()` — the literal label the screen renders |
| `/business-owners[].outstanding_invoice_total` | **Panel** | `OwnerSnapshot::balanceRows()` |
| `/business-owners[].collected_total` | **Panel** | `OwnerSnapshot::balanceRows()` |
| `/invoices/outstanding` | **Panel** | `OwnerSnapshot::balanceRows()` |
| `end_users[].days_left_in_round` | **Model** | `EndUser::$days_left_in_round` — uses the owner's own `round_cycle_days` (20 or 30), **not** a flat 30 |
| `end_users[].current_round` | **Model** | `EndUser::$current_round` (the rounds strip is the only source) |
| `end_users[].bucket_label` | **API** | `intake_status` mapped to the team's list names; `On Hold` / `Sent for Approval` take precedence |
| `/rounds/due` | **API** | `days_left_in_round <= within_days`. No panel equivalent. |
| `/team/workload` | **API** | counts from `round_selections`, `process_steps`, `time_entries`. No panel equivalent. |
| `/activity` | **Model** | `activity_logs` rows |

**Not computed by the application, and therefore not offered:** invoice-level status, due dates,
currency and days-overdue. The `invoices` table has no such columns — see below.

### Who the API reports on

JARVIS has no session, so it resolves the owner itself. `admins.role` **defaults to `'super'`**,
so "the first super admin" can be a dormant seeded account with no business owners — which would
make every figure a confident zero. Resolution order:

1. `JARVIS_OWNER_ADMIN_ID` if set.
2. The account the app calls the super admin (`config('team.super.email')`), if it owns owners.
3. The super admin who actually owns the most business owners.

Set `JARVIS_OWNER_ADMIN_ID` if the numbers ever look like someone else's.

---

## Where this differs from the original spec

The spec was written without access to this codebase. **Code against this file, not the spec.**

1. **Round length is per business owner, not a flat 30 days.** `clients.round_cycle_days` is 20
   or 30. `days_left_in_round` comes from the existing model attribute the dashboard uses.
   `/needs-attention` rows carry `round_cycle_days` so "9 days overdue" can be read correctly.

2. **`/invoices/outstanding` is per business owner, not per invoice.** The `invoices` table has
   **no status, due date or currency**, so "outstanding" cannot come from it. The figure is the
   one the dashboard shows: rounds reached minus payments recorded. There is therefore no
   `invoice_id`, `issued_at`, `due_at`, `days_overdue` or `status`, and `min_days_overdue` is not
   supported.

3. **Business owners have `business_name`**, returned as `name`.

4. **`process_steps` are per bureau**, not flat.

5. **Notes are omitted entirely**, per the spec's own instruction to omit if unsure.

6. **`activity_logs` has no `subject_type`/`subject_id`** — `subject_id` is always null.

7. **`converted` is inferred**: `prospects.status = 'interested'`,
   `business_leads.status = 'converted'`; `prospect_leads` has no such concept and is `false`.

8. **Soft-deleted rows are excluded everywhere.**

9. **`.env.example`** is a JARVIS-only fragment and says so — this repo has no real env template.

10. **A blank middle name is not the word "None".** Intake stores the literal string; it is
    stripped from the display label only, and the stored value is untouched.

---

## The rules, and the tests that hold them

`tests/Feature/JarvisApiTest.php` (26 tests):

- **Read-only** — every registered `api/jarvis/*` route is asserted to be `GET|HEAD`, and the
  assertion fails if no routes are registered at all (so it can never pass vacuously).
- **Credentials table** — the JARVIS source is stripped of comments and grepped; the table name
  may appear only once, as the entry in `Columns::FORBIDDEN_TABLES` that forbids it.
- **No PII** — a client is created carrying an SSN, DOB, address, password and ID path, then the
  list and detail responses are asserted to contain none of those keys, none of those values, and
  **no bare 9-digit number anywhere in the body**.
- **Allow-lists** — every dangerous column is asserted absent from `Columns::END_USER`, and every
  allow-listed column is asserted to actually exist (so a rename can't silently break the select).
- **No token, no API** — the route file is re-run with the token blanked and asserted to register
  nothing.

Both PII layers were verified by deliberately introducing a leak and confirming the tests fail:
adding `ssn` to the allow-list fails the allow-list test, and emitting it in the row shape fails
the response test.

`tests/Feature/JarvisMatchesDashboardTest.php` (9 tests) is the drift alarm:

- `sum(outstanding_invoice_total)` across `/business-owners` must equal the panel's Total
  Outstanding exactly — the check that would have caught the $20.
- `summary`'s new / incomplete / overdue / owners must equal the panel's headline counts.
- `/needs-attention` must mirror the panel **row for row**, in the same order, with the same
  priority label.
- A test pins the original bug: a partially-selected `Client` model returns a different
  `paymentTotals()` than a full one. If that ever stops being true, the trap is gone.
- A test proves a dormant super admin can never become the owner the API reports on.
