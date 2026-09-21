# JARVIS write manifest — v1

**Status: slice 1 SHIPPED (buckets and holds). Everything else still proposal.** This document is written first, on purpose:
JARVIS classifies each mutation's permission level from this file, and the approval card it shows
Umair is built from these descriptions. A mutation that cannot be described precisely here is one
that should not be built.

Scope set by Umair: *"general tasks all which I can do other than editing the client profile"*,
and *"I wont allow you to touch SSN or anything like this"*.

---

## Permanently out of scope

Not pending, not negotiable, not revisited without Umair saying so himself:

- **`business_owner_credentials`** — never read, never written, never joined.
- **Consumer identity fields** — name, DOB, SSN, address, phone, email, monitoring and CFPB
  credentials, ID document contents or paths, intake IP. Neither readable nor writable.
- **Client notes** — writing one is allowed (it exposes nothing); reading stays off.

## Excluded from v1, with reasons and the path back

| Excluded | Why | Path back |
|---|---|---|
| **Edit client profile** (`PUT end-users/{id}`) | It *is* the personal record. Umair excluded it explicitly. | None wanted. |
| **Create a client** (`POST end-users`) | Creating a client *is* entering their personal details. | None — clients arrive via the intake link. |
| **Graduate / cancel / pause** (`status`) | No dedicated action exists. `status` is validated in the same rules array as `ssn`, `date_of_birth` and the monitoring passwords, on the profile form handler. | Build a purpose-built status action on the dashboard first, the way `hold` and `to-done` already are. Then it becomes a clean proxy. |
| **Undo a round advance** | Does not exist anywhere in the app. Unwinding one means the rounds strip *and* `round_dates`, through two editors; a stale `round_dates` entry is what caused the historical phantom-trailing-round bug. | Build a real dashboard undo, used by humans, before exposing it. Umair chose to ship without it (2026-09-22). |
| **Delete identity documents** (`DELETE end-users/{endUser}/identity/{type}`) | Destroys the consumer's own documents. | None wanted. |
| **Delete a client** (`DELETE end-users/{id}`) | Recycle Bin. Out of scope for an assistant. | None wanted. |

---

## Two classes of write, and why the distinction matters

**PROXY** — the endpoint calls a single-purpose controller action that exists on a screen. That
action *cannot* write a personal field no matter what it is sent. Safe by construction.

**NARROW WRITE** — the field's only write path on the dashboard is
`EndUserController::update()`, the general profile form handler, whose validation array also
covers `ssn`, `date_of_birth` and the monitoring credentials. Proxying it would hand JARVIS a
method that can write an SSN. So the endpoint writes **that one field, with its own validation,
and never delegates**. Same effect as the screen; not the same method.

JARVIS classifies NARROW WRITE more strictly than PROXY *despite the smaller blast radius*,
because a regression upstream in that shared handler fails silently and in the worst direction.

---

## Reversibility states

Stated per endpoint. JARVIS says one of these sentences to Umair, so it must be the true one.

| State | What JARVIS says |
|---|---|
| `undo-endpoint` | "I can undo this." |
| `dashboard-one-action` | "You can undo this on the dashboard." |
| `dashboard-multi-screen` | "This cannot be undone from here. Correcting it means two screens." |
| `irreversible` | "This cannot be undone." |

---

## ⚠ Open decision before round advance can be built

**A round advance credits a VA, and an API call credits nobody.**

Advancing a round is not a dedicated action — it is a *side effect*. `EndUser::booted()` watches
the `rounds` array, and when it grows it writes a `round_selections` row stamped with
`Auth::guard('admin')->id()`. That row is the only signal the **Daily Task** and **Tasks View**
reports trust, and it is how a VA's work is credited.

The JARVIS API has no authenticated admin. A round advanced through it would record
`admin_id = null` — an advance credited to nobody, which quietly under-reports whoever should
have got it.

Three options, Umair's call:

1. **Credit Umair.** Honest (he approved it out loud) but inflates his own numbers.
2. **Credit a dedicated "JARVIS" admin account.** Cleanest for the report — advances made by the
   assistant are visibly separate and can be excluded or counted deliberately. Requires creating
   that account.
3. **Credit nobody (`null`).** Matches the current behaviour of the "incomplete" button, which
   also credits no one. Simplest, and silently wrong if he ever reconciles.

**Recommendation: option 2.** The audit requirement already says a JARVIS write must be
distinguishable from a human click; this is the same principle reaching the credit report.

Round advance is **not built until this is answered.**

---

## The mutations

Every endpoint is `POST /api/jarvis/...`, takes an `Idempotency-Key` header, and supports
`?preview=true` (runs the real code path and validation inside a transaction, rolls it back, and
returns the before/after diff without committing).

### Rounds

| Endpoint | Class | Changes | Reversibility | Mirrors |
|---|---|---|---|---|
| `rounds/advance` | **NARROW WRITE** | Appends the next label to `end_users.rounds`; the model then writes a `round_selections` row. | `dashboard-multi-screen` | The rounds strip (3 editors) → `EndUserController::update()` |
| `rounds/date` | **NARROW WRITE** | `round_started` / `next_round_override` on one client. | `dashboard-one-action` | Inline date edit on the Clients list → `update()` |

⚠ These two read alike and are not alike. Advancing changes which round a client is on and
credits work; correcting a date changes only a date. The approval cards must not sound the same.

### Buckets and holds — all clean proxies — ✅ SHIPPED

`POST /api/jarvis/clients/{id}/...` — all live behind `JARVIS_WRITES_ENABLED` (default off).

Two things learned building them, both now handled:

- **These actions scope through the session**, not the client id. `EndUserController`
  resolves them with `EndUser::forClient(session('selected_client_id'))`, and that helper checks
  **only the session value — not whose owner it is**. On the dashboard that's safe because the
  selector never offers an owner outside your org. The API has no selector, so it sets the
  session from the target client *and verifies the owner belongs to the assistant's org first*.
  Without that check, an arbitrary id would reach into another organisation. Covered by
  `test_a_write_cannot_reach_another_organisations_client`.
- **`approve-round` does not advance.** Verified in code: it sets `round_approval_status` and a
  timestamp, nothing else. The `rounds` array is untouched, so no `round_selections` row is
  written and nobody is credited. `test_approving_a_round_does_not_advance_it` is the alarm if
  that ever changes.


| Endpoint | Class | Changes | Reversibility | Mirrors |
|---|---|---|---|---|
| `clients/{id}/to-done` | PROXY | `intake_status = done`, stamps `listed_at` | `dashboard-one-action` | `EndUserController::moveToDone` |
| `clients/{id}/to-errors` | PROXY | `intake_status = error` | `dashboard-one-action` | `moveToErrors` |
| `clients/{id}/to-round-error` | PROXY | `intake_status = round_error` | `dashboard-one-action` | `moveToRoundError` |
| `clients/{id}/resolve-round-error` | PROXY | Clears the round error | `dashboard-one-action` | `resolveRoundError` |
| `clients/{id}/to-new-clients` | PROXY | `intake_status = pending_review` | `dashboard-one-action` | `moveToNewClients` |
| `clients/{id}/hold` | PROXY | Sets `held_at` | `undo-endpoint` (`resume`) | `hold` |
| `clients/{id}/resume` | PROXY | Clears `held_at` | `undo-endpoint` (`hold`) | `resume` |
| `clients/{id}/request-approval` | PROXY | `round_approval_status = awaiting` | `undo-endpoint` (`clear-approval`) | `requestRoundApproval` |
| `clients/{id}/approve-round` | PROXY | Sets `round_approval_status = approved`. **Does not advance.** | `undo-endpoint` (`clear-approval`) | `approveRound` |
| `clients/{id}/clear-approval` | PROXY | Clears the approval park | `dashboard-one-action` | `clearRoundApproval` |

Hold/resume, request-approval and approve-round are all undone by an endpoint —
`clear-approval` unwinds **either** the awaiting or the approved state, so it is the undo for
both. Three genuine `undo-endpoint` states in v1.

⚠ **The three approval actions are Clinecea-only.** They go through `resultsScopedEndUser()`,
which requires `clients.results_tracking`. For any other owner the API returns **409 with a plain
explanation** rather than a bare 404. `/business-owners` rows now carry `results_tracking` so the
caller can avoid offering an action that cannot succeed.

### Process steps, scores

| Endpoint | Class | Changes | Reversibility | Mirrors |
|---|---|---|---|---|
| `process-steps` | PROXY | Creates a step (per bureau) | `undo-endpoint` (delete) | `ProcessStepController::store` |
| `process-steps/{id}` | PROXY | Corrects a step | `dashboard-one-action` | `update` |
| `process-steps/{id}/delete` | PROXY | Removes a step | `irreversible` | `destroy` |
| `scores` | PROXY | Records a score entry | `dashboard-one-action` | `ScoreController::store` |

### Money

| Endpoint | Class | Changes | Reversibility | Mirrors |
|---|---|---|---|---|
| `payments` | PROXY | Records a payment against a round | `undo-endpoint` (delete) | `PaymentController::storePayment` |
| `payments/{id}/delete` | PROXY | Removes a payment | `irreversible` | `destroyPayment` |
| `payments/time` | PROXY | Logs hours (hourly owners) | `undo-endpoint` | `storeTime` |
| `payments/payout` | PROXY | Records a payout | `undo-endpoint` | `storePayout` |

**There is no "adjust a balance".** A balance is derived — `Client::paymentTotals()` computes
rounds-reached minus payments-recorded. The mutation is always "record a payment".

### 🔴 Bulk actions — flagged loudly, as requested

These exist on screens and therefore qualify, but each affects **many records in one call**:

| Endpoint | Blast radius | Mirrors |
|---|---|---|
| `payments/bulk` | Many payments at once | `bulkStorePayment` |
| `payments/pay-all-unpaid` | **Every unpaid round for an owner** | `payAllUnpaid` |
| `documents/bulk` | Many documents | `bulkStore` |

**Recommendation: leave all three out of v1.** They are the highest-consequence calls in the
list, they are rare, and a misheard instruction at 7am is unrecoverable for the first two. If
Umair wants them later they should be their own conversation.

### Owners, documents, notes

| Endpoint | Class | Changes | Reversibility | Mirrors |
|---|---|---|---|---|
| `owners` | PROXY | Creates a business owner | `dashboard-one-action` | `ClientController::store` |
| `owners/{id}` | PROXY | Updates operational fields (not credentials) | `dashboard-one-action` | `ClientController::update` |
| `owners/{id}/regenerate-intake` | PROXY | New intake link — **breaks the old one** | `irreversible` | `regenerateIntake` (super only) |
| `documents` | PROXY | Attaches a document (metadata) | `undo-endpoint` (delete) | `DocumentController::store` |
| `notes` | PROXY | Adds a note | `dashboard-one-action` | note create |

⚠ `regenerate-intake` invalidates the link an owner may already have given to clients. Reversible
only in the sense that a new link can be issued — the old one is gone for good.

---

## Guarantees around every mutation

- **Kill switch** — `JARVIS_WRITES_ENABLED=false` disables every write while reads keep working.
  Default **off**; writes are opt-in.
- **Idempotency** — `Idempotency-Key` required. A repeated key returns the first result without
  re-applying. A retried "advance round" cannot advance twice.
- **Audit** — every write logs actor `jarvis`, the idempotency key, a request id and the
  parameters, distinct from Umair's own dashboard actions. His activity log stays evidence of
  what *he* did.
- **Rate limit** — separate from reads and much lower.
- **Soft delete** — wherever the model supports it. No hard deletes.
- **Preview** — `?preview=true` on everything, running the real validation and rolling back.

---

## The assistant's account

Umair chose a dedicated account (2026-09-22) so assistant work is visibly separate from human
work. Created by migration, and three things about it are deliberate:

- **`role = 'system'`, not `super`.** The column *defaults* to `'super'`, and this row is created
  by code rather than a human filling a form — exactly the case that inherits the default
  silently, which is the bug that made the read API return `0/0/0/0` with a healthy 200.
- **Unusable password.** `admins` has no enabled flag, so the only way to make it
  unauthenticatable is a stored value that is not a hash of anything.
- **Excluded from Team Chat.** With `parent_admin_id` set to the owner it would otherwise appear
  in every VA's sidebar as a person to message. `teammates()` now excludes `system`.

Tested: cannot be logged into, is not `super`, never appears as a teammate, and can never be
resolved as the owner the read API reports on.

## Build order

1. ✅ Infrastructure: kill switch, idempotency, audit actor, preview.
2. ✅ Buckets and holds — all clean proxies, all reversible. The safest possible first slice.
3. Process steps and scores.
4. Money (single payments only).
5. Owners, documents, notes.
6. Rounds — **last**, and only once the credit-attribution decision above is answered.
