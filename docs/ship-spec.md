# Promise Tracker — Complete Ship Specification

Status: orchestrator plan for Luna implementation. This is the ship version.
No backward compatibility, no legacy shims, no "future implementation" deferrals
for anything listed here. Schema changes are made by editing existing migration
files to their final shape (the app has not shipped), not by piling new
migrations on top, except where a genuinely new table needs its own migration.

## Ground truth (verified by orchestrator, re-verify before trusting)

- Baseline: `DB_USERNAME=postgres php artisan test tests/Feature/Promises/PromiseTrackerTest.php`
  → 15 passed. The suite only fails out of the box because `postgress` (misspelled)
  is the configured role; the working role is `postgres`, server on 127.0.0.1:5432.
- Databases `debt` and `debt_test` have been created locally. `psql` CLI is absent; use PHP/PDO.
- Stack stays: Laravel 13, Livewire 4, Fortify, Postgres only. Migrations use PG
  features (jsonb, partial indexes, CASCADE drops) — keep PG-only, do not chase SQLite.
- Project rule (`.ai/rules/views.md`): no backward-compatibility shims. Remove obsolete surfaces.

## Queue sort

### Mechanical (do without design debate)
- Fix `postgress` → `postgres` in `config/database.php`, `.env.example`, `phpunit.xml`.
- `PartySearch`: match contains, not prefix-only; keep limit and active filter.
- Expand `Currency` list: BDT, PKR, LKR, MMK, KHR, LAK, NGN, KES, EGP, TRY, ZAR, BRL, MXN.
- Remove or wire dead concepts found by grep (e.g. PartyRole Guarantor/Witness) — zero dead enums at ship.
- Delete orphan `database/database.sqlite` from tracking if tracked (verify with `git ls-files`).

### Judgement (specified below; dispute only with quoted code evidence)
- Lifecycle, charges engine, instalments, share/acknowledge, split/netting, plans v2, i18n, visibility.

### Human (parked, visible, does not block panels)
- Production mail/domain credentials; legal review of help/template wording; Shariah review of labels.
- WhatsApp Business API account (ship uses `wa.me` deep links, needs no account).
- Real payment-gateway merchant account (ship shows verified destinations + DuitNow details text; no gateway).

## Product definition

One sentence: two people remember the same promise the same way, settle it with
dignity, and money stops damaging the relationship.

Personas in scope at ship: solo debtor, solo creditor, both-at-once household
member, small shop/group keeper, shared-profile editor/viewer, counterparty with
no account (share link), interest-free (qard hasan/family) user, formal-debt
user (card/BNPL/financing). Languages at ship: English + Bahasa Melayu.

## Design decisions (settled)

### 1. Lifecycle — human endings
- `ObligationStatus`: `open`, `paused`, `disputed`, `settled`. Disputed/paused stop reminders-as-overdue and stop auto charges.
- New nullable `closure_reason` on obligations: `paid`, `forgiven`, `written_off`, `offset`, `completed`.
- `MoneyEntry` additions with signed effects:
  - `interest_charge`, `profit_charge`, `fee`, `penalty` → +1 (increase amount owed).
  - `forgiveness`, `write_off` → settlement-like: reduce balance toward zero for the obligation direction.
  - `offset` → settlement-like, always created in linked pairs (store `linked_movement_id`).
- Actions: PauseObligation, ResumeObligation, DisputeObligation, ResolveDispute,
  ForgiveObligation (full or partial amount), WriteOffObligation, OffsetObligations.
  Each writes movements + activity; status machine sets `settled` + reason when balance hits zero
  via those entries. Settled-by-payment keeps reason `paid`; commitments `completed`.
- Overdue definition excludes paused/disputed. UI badges for paused/disputed; settled shows reason label.

### 2. Charges — terms, automation gated by completeness
- New table `obligation_charge_terms` (1:1 with money obligations), final shape in migration:
  mode (`none|manual|auto_propose|auto_apply`), kind (`none|interest|profit`),
  rate (`decimal 12,8`, meaning per-period rate e.g. 0.015 = 1.5%), basis (`flat|reducing_simple|fixed_total`),
  period (`daily|monthly|yearly`), grace_days, cap_minor (nullable), total_payable_minor (nullable, for fixed_total/murabahah),
  penalty_kind (`none|fixed|percent_of_overdue`), penalty_value, allocation_order (`charges_first|principal_first`),
  next_charge_on, last_charge_on, acknowledged_at, acknowledged_name.
- `fixed_total` (murabahah-style): profit = total_payable − principal is posted once as `profit_charge`
  when terms are saved in auto_apply, or proposed in auto_propose. It never accrues further.
- `ChargeCalculator` (bcmath, unit-tested): flat uses original principal (sum opening/advance in currency);
  reducing_simple uses current outstanding principal portion; pro-rate by days for daily, whole periods otherwise;
  round HalfUp to minor units; apply cap; return breakdown (basis amount, days/periods, rate) for audit snapshot.
- `MovementStatus` gains `proposed`. Balances count `confirmed` only (already true — keep).
  `money_movements` gains nullable `period_key` (unique where not null) + nullable `calculation` jsonb.
  period_key = `{obligation_id}:{entry}:{period_start}`-style deterministic key → idempotent generation.
- Command `charges:generate-due` mirrors SendDueReminders: per profile timezone, lock per obligation,
  skip paused/disputed/settled, generate proposed or confirmed per mode; penalties are ALWAYS proposed.
  Auto-apply requires `acknowledged_at` not null OR single-sided formal debt flag `is_formal` on terms
  (bank/card where counterparty acknowledgement is impossible) — UI must force explicit choice.
- Actions: SaveChargeTerms (validates completeness per mode; auto modes reject incomplete terms),
  ConfirmProposedMovement, DismissProposedMovement, GenerateCharges (service used by command + "generate now" UI),
  ChargeSchedulePreview query (next 6 periods assuming no payments).
- Interest-free guard: `financial_profiles.interest_free_mode boolean default true` (new profiles start
  interest-free; user opts into charges per promise and may turn the profile guard off in profile settings).
  When on, kind=interest and percent penalties are hidden in UI and rejected in actions; fee + fixed_total profit remain.
- Balance breakdown query: principal vs unpaid charges per obligation/currency honouring allocation_order.
  Display everywhere a balance shows: "Borrowed / Charges / Paid" lines.
- Schedule the command daily in `routes/console.php` alongside reminders if reminders are scheduled there; verify first.

### 3. Instalments
- New table `instalments`: obligation_id, sequence, due_on, amount_minor, currency, paid_minor default 0.
  Status derived: settled if paid>=amount, overdue if due_on<today and not settled, else pending.
- Actions: GenerateInstalmentSchedule (count + frequency weekly/monthly + first due date or pay-day-of-month;
  splits total payable incl. fixed_total profit; last instalment absorbs rounding), RegenerateInstalments
  (only when no paid_minor>0 exists), and allocation inside RecordMoneyMovement/SettleObligation:
  settlement money fills charges portion first or principal first per allocation_order, then oldest instalments.
  Voiding a movement reverses its instalment allocations — store `instalment_allocations`
  (movement_id, instalment_id, amount_minor) to make reversal exact. Forgiveness/write-off allocate the same way.
- UI: instalment list with progress, per-instalment due/paid, schedule builder on promise show + optional at create.
- Reminders/DueSoon consider next unpaid instalment due date when instalments exist.

### 4. Purpose-first promises + quick capture
- Create flow gains Title/Purpose ("What is this for?", optional → defaults to party name).
  `records.title` = purpose; obligation title = purpose. Remove forced party-name title.
- Create form stays one screen; schedule + charge terms are optional expandable sections on create, fully editable on show.
- Create and repeat: after save, "Add another like this" reuses party/direction/subject.

### 5. Counterparty share / acknowledge / dispute (no account)
- New table `record_shares`: record_id, token_hash (unique), created_by, expires_at nullable, revoked_at nullable,
  acknowledged_at/name, disputed_at/name/note, witness_name nullable (wires the Witness concept honestly or drop field).
- Actions: CreateRecordShare (returns plain token once), RevokeRecordShare, AcknowledgeShare, DisputeShare.
- Public routes (throttled, no auth): `GET /s/{token}` read-only promise view (confirmed movements, terms summary,
  instalments, balance breakdown, evidence file NAMES only), `POST /s/{token}/acknowledge`, `POST /s/{token}/dispute`.
  Dispute via share sets obligation(s) `disputed` + notifies profile members (database notification, new type `share_disputed`;
  acknowledgement type `share_acknowledged`). Acknowledge stamps charge terms acknowledged_* when terms exist.
- Hash tokens like API tokens/invites already do. Expired/revoked → 404. Tests for leakage: no other records, no downloads.

### 6. Communication kit
- `MessageTemplates` domain class: keys gentle_reminder, firm_reminder, receipt_thanks,
  hardship_request, forgiveness_notice, share_invite. Each in `en` and `ms`, with placeholders
  (name, purpose, amount formatted, due date, share url, payment destination label).
  Templates live in lang files, rendered server-side; UI offers Copy + WhatsApp (`wa.me/<e164>?text=`)
  using the party primary contact when it looks like a phone; never auto-sends.
- `ReminderChannel` gains `mail`. ReminderDue notification honours channel (database, mail, or both via two rows —
  keep single channel per reminder, UI select). Mail uses Laravel mail config; array driver in tests.
- Hardship flow: from a payable promise, "Request pause" composes hardship_request message + optionally
  creates a proposed new due date the recorder can apply (existing UpdatePromiseDetails lock is relaxed:
  due date and title become editable with activity log entry even after movements; amounts never edit — void only).

### 7. Split, groups and netting
- New table `obligation_shares`: obligation_id, party_id, share_bps (int, 10000=100%) unique(obligation_id,party_id).
  Optional at create/add-part: split counterparty side among 2+ parties; balances per party scale by share for display
  in PartyExposure (money movements stay on the obligation; exposure multiplies by share, rounding to minor, documented).
- Guarantor role wired: RecordParty may add guarantor parties from promise show UI (name + optional contact link);
  shown on share page. If any PartyRole still has zero UI after this, delete it.
- `NettingSuggestion` query: per profile + currency, net each party (to_receive − to_pay incl. shares),
  greedy debtor→creditor settlement list. UI page section on People index or Plans: suggestions are advisory;
  "Apply offset" between two obligations of same party+currency uses OffsetObligations (§1).

### 8. Plans v2
- Budget inputs become major-unit strings parsed via `Money::parseMajor` (rename Livewire props, no minor in UI).
- `GeneratePlan` gains strategy input: `due_date` (current), `snowball` (smallest balance first),
  `avalanche` (highest effective rate first; rate from charge terms, 0 when none — ties by due date).
  Store strategy on repayment_plans (edit migration). Plan may include a collect-first note listing largest receivables.
- `DebtFreeProjection` query: simulate monthly with current capacity + strategy, applying estimated reducing charges
  when terms exist; output months-to-free + total charges estimate + "pay X extra → N months earlier" for X=10% capacity.
  Show on Plans show. Estimates labelled as estimates.
- Instalment-aware cap: when instalments exist, a period allocation is capped at arrears + instalments due
  within the budget period (not merely total outstanding), so plans do not front-load far-future instalments.

### 9. i18n (en + ms) and dates
- Create `lang/en/*.php` and `lang/ms/*.php` (app, promises, plans, charges, share, templates).
  Convert user-facing strings in: sidebar, promises index/create/show, plans index/show, people index/show headings+actions,
  share public page, notifications empty states, legal page titles. Enum labels via lang where shown.
- `users.locale` (default `en`) + SetLocale middleware (user locale → session → config). Locale switcher in settings profile page.
  Share page locale from record profile owner locale, toggleable via `?lang=`.
- Due dates show Hijri alongside Gregorian when profile `show_hijri` (new boolean, default false) using IntlCalendar islamic
  when intl extension present; silently omit when unavailable. Verify `php -m | grep intl` and test both paths with a fakeable presenter.

### 10. Privacy, safety, portability
- `records.visibility` (`shared|private`) + `records.created_by`. Private = only creator + profile owner can view;
  editors/viewers cannot. Policies + all list/total/export queries must respect it for non-owners (totals exclude others' private).
- Help page `GET /help/debt-support` (auth): AKPK + generic guidance (talk early, get agreement in writing, pause before penalty),
  threats/coercion safety note, crisis signposting MY (Talian Kasih 15999, Befrienders KL) + "find local help" wording, no advice claims.
  Link from sidebar + overdue empty/alert areas. Content from lang files.
- Exchange rates become per-profile: add `profile_id` (required) by editing migration; scope ConvertedView + UI + SetExchangeRate.
- Export v2: include charge terms, instalments+allocations, shares (party), record shares ack state, lifecycle fields.
  Restore: `ImportProfileExport` action + UI on Imports page: JSON upload restores into a NEW profile owned by user
  (never merge), transactional, reuses export payload shape. Round-trip test required.
- CSV import accepts `amount` (major, parsed per currency precision) OR legacy `amount_minor` column (input tolerance, not BC shim —
  it is file-format tolerance; keep both, error if neither), and dates `Y-m-d`, `d/m/Y`, `d M Y`.

## Batch plan (each batch: implement → scoped tests → pint/phpstan → Luna self-audit note)

1. **Foundation:** typo fixes, lifecycle (§1), charge terms+calculator+proposed+command (§2), edited migrations final-shape,
   factories for new models, unit tests for calculator/machine, feature tests for forgive/pause/dispute/charges modes.
2. **Money flow:** instalments+allocations (§3), purpose title (§4), plans v2 + projection (§8), breakdown display.
3. **Two-sided:** share/ack/dispute (§5), communication kit + mail reminders + due-date edit relaxation (§6).
4. **Whole product:** split/netting/guarantor (§7), i18n+Hijri (§9), visibility/help/per-profile rates/export-restore/CSV (§10),
   currency expansion + PartySearch fix, dead-concept sweep.

Verification per batch (scoped, quote output):
`vendor/bin/pint --dirty --format agent` · `vendor/bin/phpstan analyse <touched paths> --memory-limit=1G` ·
`php artisan test --compact <touched test files>` · fresh migrate on scratch DB:
`DB_DATABASE=debt_test php artisan migrate:fresh --seed` must pass from empty at ship.

## Luna self-audit before handover (required output)

- Re-read full `git diff` against this spec section by section; list any spec line not implemented — fix, do not defer.
- Grep proof: `postgress` zero hits; `TODO|FIXME|future` zero new hits; every enum case has a caller or is deleted.
- Every zero/never claim in new prose carries the command that proved it.
- Fresh migrate+seed from empty, full test suite result quoted (counts), pint+phpstan results quoted.

## Orchestrator final audit

Diff read-through, spec trace, independent test re-run of touched suites, spot-check charge maths by hand-computed cases,
share-leakage tests, restore round-trip. Ship only when Luna self-audit + orchestrator audit both pass.

## Amendments A1 — binding resolutions (override any conflicting text above)

Source: Luna first-pass NOT-READY (F1, G1–G7, R1–R7, O1–O3), all code claims re-verified by orchestrator.
Correction: reminder scheduling lives in `bootstrap/app.php` (`reminders:send-due`, daily 08:00), not `routes/console.php`.

- **F1/R2/O1 Terms acceptance, versioned.** Charge terms carry `terms_hash` (sha256 of canonical terms fields +
  currency + principal snapshot) and `accepted_terms_hash`, `accepted_at`, `accepted_name`. Share CTA wording is
  "I accept these terms" (acceptance, not mere acknowledgement) and stamps the hash it displayed.
  Any SaveChargeTerms change recomputes hash, clears acceptance, and downgrades `auto_apply` → `auto_propose`.
  Flow: save terms (auto_propose) + `auto_apply_on_acceptance` flag → on matching-hash acceptance, promote to
  `auto_apply` and, for `fixed_total`, post the single profit_charge at that moment. Formal debts (`is_formal=true`,
  explicit UI choice) may auto-apply without acceptance. No other auto-apply path exists.
- **Rate normalisation (G1).** Annual simple rate = daily×365, monthly×12, yearly×1. Avalanche compares annual rate;
  monthly projection charge = outstanding × annual ÷ 12. `penalty_value`: fixed = minor units int; percent = bps int of overdue amount.
- **Period maths.** Charge window is `[period_start, period_end)`; days = date diff. Monthly periods are calendar
  anniversaries (clamp to month-end). First period starts at opening-movement date + grace_days. Penalties proposed
  only after due date + grace_days. `cap_minor` is a LIFETIME total cap per obligation+currency; calculator takes
  already-charged confirmed total and returns `min(computed, cap − already_charged)` floored at 0. Worked anchors:
  flat RM1,000 × 1.5% monthly × 1 period = RM15.00; reducing RM600 × 1.5%/day × 10 days = RM90.00.
- **Status propagation (G2/G3).** Status machine: balance zero → `settled` (+ reason from entry); nonzero preserves
  `paused`/`disputed`, else `open`. Reopening a settled obligation (new charge/advance or void leaving nonzero)
  clears `closure_reason` → `open`. RecordStatusQuery: all settled→settled, any disputed→disputed, any paused→paused, else open.
  RecordArchiveFilter gains `paused` and `disputed`; `open` filter = any non-settled obligation.
  PartyExposure returns open/paused/disputed/settled counts (open excludes paused/disputed).
- **Reminders (G4).** SendDueReminders skips settled/paused/disputed obligations and dismisses their pending/snoozed reminders.
  Scheduler: register `charges:generate-due` daily in `bootstrap/app.php` BEFORE `reminders:send-due` at the same tick.
- **Notifications (G5).** NotificationType gains `share_accepted`, `share_disputed`; Notifications inbox lists all types
  with per-type rendering and links; preferences apply per type. Share dispute does NOT auto-set disputed status silently —
  it sets status disputed AND notifies (visible, reversible via ResolveDispute).
- **Due-date precedence (G6).** With instalments, overdue/due-soon use next unpaid instalment `due_on` INSTEAD of
  obligation `due_on` (which becomes final-maturity display only).
- **Restore evidence (G7).** Canonical restore artifact = ZIP (`export.json` + `files/<attachment_id>/<original_name>`);
  ext-zip verified present. Add ZIP export download; restore accepts ZIP (full) — JSON/CSV stay data-only exports, not restore inputs.
- **Privacy everywhere (R3/O3).** Visibility scoping applies to lists, totals, PartyExposure, GeneratePlan, projections,
  netting, exports AND notification recipients/payloads (recipient must pass record view policy). A private record MAY be
  token-shared only by its creator or profile owner; that share is the explicit exception and is activity-logged.
- **Splits (R4).** Shares must sum to exactly 10000 bps, validated under obligation lock. Money split uses
  largest-remainder rounding in party-id order so parts sum to the whole exactly.
- **Offsets (R5).** VoidMovement is pair-aware: voiding an offset voids its linked movement in the same transaction.
- **Bounds (R6).** Forgiveness/write-off/offset amounts > abs(outstanding in currency) are REJECTED under lock;
  `isSettlement()` includes forgiveness, write_off, offset for clamping paths.
- **Roles.** Delete `PartyRole::Witness` and `PartyRole::Contact` and the `witness_name` share field (zero producers verified);
  keep Counterparty + Guarantor (wired in §7). Edit the enum-check migration accordingly.
- **Labelling (R1, pending convergence Q1).** Remove "murabahah-style" wording everywhere → "fixed total, agreed upfront".
  UI label for the guard: "Avoid interest (riba) and percentage penalties", with copy "Labels here are not Shariah certification."
- **Batch order (adopted from Luna).** 1 Foundation (+visibility, per-profile rates, status/query scoping) →
  2 Share & consent (versioned acceptance, notifications) → 3 Charges engine → 4 Instalments & plans v2 →
  5 Purpose & communication → 6 Groups/netting/guarantor → 7 i18n/Hijri/help/ZIP-restore/CSV + full verification.
  Sections above keep their numbers; only execution order changes.

## Amendments A2 — convergence decision (R1 closed, Luna decision A)

- Under `interest_free_mode`, `fixed_total` profit and fees ARE allowed. Guard blocks interest, percentage
  penalties, and non-fixed profit bases — enforced in SaveChargeTerms AND GenerateCharges/command (covers terms
  saved before the guard was turned on).
- Guarded fixed-total requires per-promise confirmation persisted as `fixed_total_agreed_upfront_at` +
  `fixed_total_agreed_upfront_by` on `obligation_charge_terms`; changing principal/currency/total clears it.
  Required even when `is_formal=true` (that flag only governs the auto-apply acceptance exception).
- Copy keys (en + ms): `charges.guard.label`, `charges.guard.disclaimer`, `charges.fixed_total.label`,
  `charges.fixed_total.total_payable`, `charges.fixed_total.agreed_upfront`,
  `charges.fixed_total.agreed_upfront_required`, `charges.guard.interest_blocked`,
  `charges.guard.percentage_penalty_blocked`, `charges.guard.non_fixed_profit_blocked` — English values per Luna Q1 answer.


