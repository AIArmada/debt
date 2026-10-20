# Promise Tracker — Complete Handoff (read this first)

> **STATUS: SHIP BUILD COMPLETED Mon 19 Oct 2026.** Luna full-diff audit fixes
> landed; orchestrator final gate: **222 tests passed (1282 assertions)**,
> PHPStan `[OK] No errors`, Pint `{"tool":"pint","result":"passed"}`,
> migrate:fresh+seed OK on `debt` and `debt_test`. Nothing committed.
> **LIVE CHROME GATE CLOSED Mon 19 Oct 2026:** Luna Chrome DevTools MCP run
> found a plans-route 500, raw translation keys, mobile filter overflow, and
> Alpine CSP console errors; Luna fixed them (227 tests green); retest passed
> all flows including charge proposal generation/confirmation after backdating
> the dev opening balance. Evidence: `/tmp/luna-chrome-full-out.md`,
> `/tmp/luna-chrome-retest-out.md`, `/tmp/luna-chrome-r3-out.md`, screenshots in
> `/tmp/chrome-shots/`. Next: FilamentPHP 5 backend (same Luna orchestration).
> **FILAMENT STAFF BACKEND COMPLETED Mon 19 Oct 2026:** staff /admin per
> docs/filament-admin-spec.md (AIArmada dev-main per owner override:
> commerce-support 768dc55, authz ccb31cc, filament-authz da39860, Filament
> v5.10.2, Spatie Permission 8.3.0). Final gate: **266 tests passed (1471
> assertions)**, PHPStan 0, Pint passed, Vite built. Admin Chrome gate passed
> (guest redirect, customer denied, MFA enroll + fresh challenge, case->grant->
> workspace->revoke, audit; Alpine CSP split: customer CSP build, admin standard
> build via UseStandardAlpineForAdmin + published assets). Nothing committed.
> Sections below are retained as the historical record of how it was built.

Written: Sat 17 Oct 2026, ~14:22 +08, workspace `/Users/saiffil/Herd/debt`.
Purpose: a fresh agent/session with ZERO prior context can finish the ship
version from this file alone. Do not re-run the audit or first-pass review;
they are done and summarised here. Token-saving order: read THIS file fully,
then `docs/ship-spec.md` (262 lines, incl. binding Amendments A1+A2), then act.

## 1. Roles and hard rules (user-mandated, do not change)

- **Orchestrator (you, Muse session):** plan, review, audit, verify. You do
  NOT write product code yourself except trivial local infra (e.g. `.env` fix,
  already done) and docs like this file.
- **Writer: `gpt-6-luna` via Codex CLI, ALWAYS max reasoning effort.**
  Invocation pattern (proven working, codex-cli 0.159.0 at
  `/Users/saiffil/.local/bin/codex`):
  `codex exec -m gpt-6-luna -c model_reasoning_effort="max" -s workspace-write -C /Users/saiffil/Herd/debt -o /tmp/<out>.txt - < /tmp/<brief>.md > /tmp/<log>.log 2>&1`
  Read-only audits use `-s read-only`. Never change waves/models; never use the
  native numbered use-case aliases. Standing user rule (personal memory `panel-models.md`):
  Luna dispatches are ALWAYS max, never low/medium. Panel skill reference:
  `~/.agents/skills/panel/SKILL.md` + `PROTOCOL.md` (read before dispatching).
  Note: trivial probe must use effort `none` (this model rejects `minimal`).
- **No backward compatibility, no legacy shims** (`.ai/rules/views.md` + user).
  Schema changes EDIT existing migrations to final shape; genuinely new tables
  got new migrations. This is the complete ship version — no "v2 / future
  implementation" deferrals inside spec scope.
- Luna must self-review/audit/fix its own diff before handover; orchestrator
  then does final review/audit. Both steps are REQUIRED, neither is optional.
- **Do not commit, push, tag or rewrite history.** User never asked. HEAD is
  still pre-existing `5f3222c wip`; ALL ship work is uncommitted (see §7).
- Loop only OPEN deltas back to Luna (one issue cluster per brief). Never ask
  Luna to reconfirm settled agreements.

## 2. What this product is (audit conclusion, settled)

Folder `debt`, product **Promise Tracker** (`APP_NAME` in `.env.example`).
Laravel 13.30, Livewire 4.4, Fortify, Postgres-only, PHP 8.5, Pest 5.

One-sentence outcome adopted for ship: *two people remember the same promise
the same way, settle it with dignity, and money stops damaging the relationship.*

Original audit verdict (orchestrator, before build): the app was an excellent
private ledger for REMEMBERING debt, but real debt misery is mostly inability
to pay, disagreement, shame/avoidance and power imbalance — not forgetting.
It had: one-sided truth (no counterparty acknowledgement), only open/settled
endings (no forgive/pause/dispute), no interest/profit/fee/penalty model, no
instalments/recurring, reminders only to the recorder via database, English
only, no splits/groups, thin repayment plan, no safety signposting.

The ship build (this handoff's work) closes those gaps per `docs/ship-spec.md`.

### Interest / profit / fee / penalty — settled design (do not redesign)

- Add them, but gated by completeness. Modes per money obligation:
  `none | manual | auto_propose | auto_apply`.
- Charge kinds are legally/culturally distinct: interest (cost over time),
  profit (agreed markup / fixed total), fee (fixed service cost), penalty
  (late/default). Never conflate them in UI or maths.
- `auto_apply` requires versioned counterparty acceptance ("I accept these
  terms", hash-bound) OR explicit `is_formal` flag (bank/card). Penalties are
  ALWAYS proposed, never auto-applied. Charges never compound. Fixed-total
  profit posts exactly once. Interest-free profile guard (default ON) blocks
  interest + percentage penalties + non-fixed profit; fixed-total profit and
  fees allowed with fresh upfront-agreement confirmation (Amendment A2).
- Worked maths anchors (unit-tested): flat RM1,000 × 1.5% monthly × 1 period
  = **RM15.00**; reducing-simple RM600 × 1.5%/day × 10 days = **RM90.00**;
  `cap_minor` is a LIFETIME cap; annual comparison rate = daily×365,
  monthly×12, yearly×1 (avalanche/projection).

## 3. Authoritative documents (in-repo, survive session loss)

- `docs/ship-spec.md` — THE spec. Sections §1 lifecycle, §2 charges, §3
  instalments, §4 purpose, §5 share/consent, §6 communication, §7
  splits/netting, §8 plans v2, §9 i18n/Hijri, §10 privacy/safety/portability,
  batch plan, plus **Amendments A1** (18 Luna first-pass resolutions: versioned
  acceptance, rate normalisation, period maths, status propagation, reminder
  skipping, notifications, due-date precedence, ZIP restore, privacy
  everywhere, splits sum=10000bps largest-remainder, pair-aware offset void,
  amount bounds, role deletions, Luna's 7-batch order) and **A2** (R1
  labelling decision A + exact copy keys). Amendments OVERRIDE body text.
- This `HANDOFF.md` — state + resume instructions (includes the pending fix
  brief verbatim in Appendix A so `/tmp` loss does not matter).
- Session-only artifacts in `/tmp` (MAY vanish; copies/essence are in this
  file): `/tmp/wave3-brief.md`, `/tmp/wave4-brief.md`, `/tmp/wave4-fix-brief.md`,
  `/tmp/panel-briefs/*`, `/tmp/panel-out/*`, `/tmp/gate-wave*.log`.
- Luna first-pass verdict was **NOT-READY: FATAL 1, GAP 7, RISK 7, ORDER 3**
  (full text was `/tmp/panel-out/luna-first-pass.txt`); ALL items were
  resolved in Amendments A1/A2 and verified against code by the orchestrator.
  Do not reopen them.

## 4. Environment ground truth (all orchestrator-verified by command)

- Postgres server IS running at `127.0.0.1:5432` (no `psql` CLI; use PHP/PDO).
  Working role: **`postgres`** (no password). The historic typo role
  `postgress` does NOT exist. Databases `debt` and `debt_test` were CREATED
  by the orchestrator via PDO and exist. Extensions: only `plpgsql`.
- Local `.env` was fixed by orchestrator: `DB_USERNAME=postgres` (was the
  typo). `config/database.php`, `.env.example`, `phpunit.xml` were fixed by
  Luna in Wave 1 (grep `postgress` = 0 in app/config; spec doc still mentions
  the word historically — expected).
- `config/database.php` defines ONLY pgsql. Migrations use PG features
  (jsonb, partial unique indexes, CASCADE) — SQLite testing will FAIL by
  design; never switch tests to SQLite. Orphan `database/database.sqlite`
  is untracked user property; leave it alone.
- PHP: 8.5.0, drivers mysql/pgsql/sqlite, `bcmath` + `intl` + `zip` present
  (`ZipArchive` exists — ZIP restore is viable).
- **Luna sandbox gotcha:** Codex sandboxes block `127.0.0.1:5432`
  ("Operation not permitted"), so Luna CANNOT run DB tests or
  migrate:fresh. Luna runs pint + phpstan (phpstan parallel hits TCP EPERM;
  working trick: `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpstan analyse --debug ...`).
  ALL real test/migration runs are the ORCHESTRATOR's job. This split worked
  for every wave; keep it.
- Reminder scheduling lives in `bootstrap/app.php:19`
  (`reminders:send-due` daily 08:00) — NOT `routes/console.php`.
  `charges:generate-due` must sit BEFORE it (Wave 2 did this; verify order).
- Baseline before build: `DB_USERNAME=postgres php artisan test tests/Feature/Promises/PromiseTrackerTest.php`
  → 15 passed. The suite only ever failed out-of-box due to the typo role.

## 5. Build history — waves, scope, verified gates

Execution order (Luna's 7 batches, grouped into waves): 1 Foundation →
2 Share/consent → 3 Charges → 4 Instalments/plans → 5 Purpose/communication →
6 Groups/netting → 7 i18n/Hijri/help/ZIP-restore/CSV.

### Wave 1 = Batches 1+2 (COMPLETE, green)
Scope: typo fixes; lifecycle (`ObligationStatus` open/paused/disputed/settled
+ `closure_reason` paid/forgiven/written_off/offset/completed; new
`MoneyEntry` interest_charge/profit_charge/fee/penalty/forgiveness/write_off/
offset; Pause/Resume/Dispute/Resolve/Forgive/WriteOff actions; pair-aware
void; status-machine preservation); records `visibility`+`created_by` with
privacy scoping in lists/totals/exposure/plans/exports/notifications;
per-profile exchange rates; `PartyRole::Witness/Contact` DELETED;
charge-terms table+model foundation (`obligation_charge_terms`, terms hash,
proposed status, `period_key`, `calculation` jsonb); `interest_free_mode`
default true; share/consent (`record_shares`, public `/s/{token}` routes,
versioned acceptance, share notifications); currency expansion (BDT PKR LKR
MMK KHR LAK NGN KES EGP TRY ZAR BRL MXN); PartySearch contains-match.
Fix rounds needed: self-ref FK must be added AFTER create (fixed);
`ProfileExport::attachmentQuery` return type; `Collection::load` misuse;
HasFactory wrong namespace in ObligationChargeTerm; due-date
`after_or_equal:today` wrongly rejecting overdue promises (removed);
27 phpstan errors incl. visibility enum casts; queued-export owner 404;
attachment 403→404; test seam parsing export id from URL PATH not query.
**Gate: 156 passed (887 assertions), phpstan 0, pint passed, migrate:fresh ok.**

### Wave 2 = Batch 3 Charges engine (COMPLETE, green)
Scope: `App\Domain\Charges\ChargeCalculator` (bcmath, flat/reducing_simple,
lifetime cap, grace, calendar-anniversary monthly clamp, audit breakdown);
BalanceBreakdown (principal vs charges, both allocation orders);
SaveChargeTerms (hash/downgrade/guard); `App\Services\GenerateCharges` +
`charges:generate-due` command (per-timezone, locks, idempotent period_key,
penalties always proposed, fixed-total exactly-once via
`fixed_total_posted_at`); Confirm/Dismiss proposed movements;
ChargeSchedulePreview (6 periods); manual charge UI; terms UI on promise show.
Fix rounds: PG `sum()` string cast in test; rate input rendered for kind=none
leaking placeholder (now kind-gated).
**Gate: 173 passed (990 assertions), phpstan 0, pint passed.**

### Wave 3 = Batches 4+5 (COMPLETE, green)
Scope: instalments + `instalment_allocations` (generate/regenerate guarded,
oldest-first allocation, exact void reversal, allocation on confirm-proposed,
due-date precedence to next unpaid instalment); Plans v2 (major-unit inputs
via `Money::parseMajor`, `strategy` due_date/snowball/avalanche with annual
normalisation, instalment-aware caps, DebtFreeProjection capped 600 months,
collect-first receivables); purpose titles (`records.title` = purpose else
party name, restart prefill keeps real party, "Add another like this");
message templates en+ms (`lang/{en,ms}/templates.php`, 6 keys), copy +
wa.me (MY leading-0→60 only when base currency MYR), mail reminder channel,
hardship preselect, title/due-date editable after movements (activity-logged).
Fix rounds: share URL appended when template lacks placeholder; PG sum casts;
Message section wrapped in `@can('update')` + authorize in
`requestPauseMessage()`; Plans\Show enum-string crash fixed
(enum|string|null handling — model casts return enums, NEVER `(string)` an enum).
**Gate: 187 passed (1067 assertions), phpstan 0, pint passed, migrate:fresh ok,
both artisan commands run clean (0 generated on seed).**

### Wave 4 = Batches 6+7 (WRITTEN, NOT YET GREEN — current frontier)
Scope dispatched in `/tmp/wave4-brief.md` items 1–15: obligation_shares +
SetObligationShares + share-scaled PartyExposure; OffsetObligations action;
NettingSuggestion + People UI; guarantor add/remove + share-page display;
full i18n (`lang/en` + `lang/ms`: app, promises, plans, charges, share,
legal, help, templates); `users.locale` + `SetLocale` middleware + switcher
+ `/locale/{locale}`; Hijri (`show_hijri` + `HijriDatePresenter` via
IntlCalendar islamic, guarded); help page `/help/debt-support` (AKPK,
Talian Kasih 15999, Befrienders KL); export v2 + ZIP download + restore into
NEW profile (`ImportProfileExport`); CSV `amount` major OR `amount_minor` +
dates Y-m-d / d/m/Y / d M Y.
**Interruption:** Luna's Wave 4 run hit the Codex usage limit (EXIT 1,
1,142,545 tokens, "try again at 4:49 PM") BEFORE writing its handover file
(`/tmp/wave4-out.md` never created). Files WERE written to disk.
**Orchestrator gate on partial Wave 4: migrate:fresh OK; 190 passed,
11 FAILED (1106 assertions); phpstan 0; pint passed.**

Pre-audit (orchestrator, read-only) confirms Wave 4 structure exists:
guarantor wired (`app/Livewire/Promises/Show.php` addGuarantor ~:309),
`app/Domain/Presenters/HijriDatePresenter.php` exists, export `'version' => 2`
(`ProfileExport.php:80`), `app/Actions/Promises/ImportProfileExport.php` exists,
help route (`routes/web.php:52`), `app/Http/Middleware/SetLocale.php` registered
(`bootstrap/app.php:26`), lang files en+ms (app/charges/help/legal/plans/
promises/share/templates), tests created: SplitsNettingTest,
OffsetObligationsTest, GuarantorTest, I18nTest, RestoreZipTest,
CsvImportFormatsTest, HelpPageTest. Greps: `murabahah` 0, `postgress` 0 in
app/config, added TODO/FIXME 0.

#### The 11 failures (from `/tmp/gate-wave4-partial.log`, diagnosed)
1. `ChargesEngineTest` schedule-preview — page missing exact
   `Avoid interest (riba) and percentage penalties` (A2 `charges.guard.label`).
2. `WaveOneFoundationTest` exchange-rates — same missing guard string.
3. `PromiseTrackerTest` save-payable — expects exact `You owe them`.
4. `PromiseTrackerTest` overpayment — expects exact `They owe you`.
5. `PartyRichnessTest` relationships — expects exact
   `Ali — assistant of → Ahmad` (em dash + arrow format).
6. `ShareConsentTest` share-page — expects exact
   `fixed total, agreed upfront` (A2 casing/wording).
7–9. `ShareConsentTest` ×3 (versioned terms / plain ack / private share) —
   redirect gains `?lang=en`; tests assert exact clean `shares.show` URL.
   App fix: preserve lang only when explicitly requested / non-en.
10. `ProfileSettingsTest` — `ValidationException: The show hijri field is
    required` (must be sometimes|boolean default false).
11. `CsvImportFormatsTest` major-amount — raw Carbon
    `InvalidFormatException: Unexpected data found. Trailing data` escapes;
    must catch per-format and throw ValidationException instead.
Clusters 1–6 are en-lang exact-string / default-locale issues: English lang
values MUST reproduce historic strings byte-for-byte; default factory-user
locale must render `en`. The ready-to-send fix brief is Appendix A below
(also at `/tmp/wave4-fix-brief.md` while /tmp survives).

## 6. Blocker and scheduled resume (time-sensitive)

- **Blocker:** Codex usage limit for `gpt-6-luna` until **16:49 today**
  (Sat 17 Oct 2026). Verified by probe at 14:08 (`effort=none` → usage-limit
  error). Do NOT retry Luna before 16:49; probes waste nothing but prove
  nothing new until then. If the user upgrades credits, resume immediately.
- **Scheduled resume:** one-shot cron job **`bfe895e0`**, `55 16 17 10 *` (prompt: read this HANDOFF.md first, then execute §8)
  (16:55 today, non-recurring), whose prompt contains the full continuation
  sequence in §8. If you are a NEW session after 16:55 and that job already
  fired in the old session, ignore it and run §8 yourself. Check with the
  cron-list tool; delete the job if you take over manually first.
- If BOTH the cron session and you would dispatch Luna, coordinate: check
  `/tmp/wave4-fix-out.md` existence + `ps aux | grep codex` before dispatching
  a duplicate. Never run two Luna writers concurrently in this workspace.

## 7. Git state (handle with care)

- HEAD: `5f3222c wip` (pre-existing; history untouched by this work).
- Working tree at handoff: **208 porcelain entries**; tracked diff
  **114 files changed, 3388 insertions, 670 deletions** plus many untracked
  new files (actions, models, lang/, tests, migrations
  `2026_10_17_000001` charge terms, `000002` record shares, plus Wave 3/4
  migrations). NOTHING from Waves 1–4 is committed. Do not commit/push/tag
  unless the user explicitly asks in that session.
- Untracked files you did not create are user property (e.g.
  `database/database.sqlite`). Never `git clean`. Commit (if ever asked) by
  naming files explicitly.
- `.env` is local-only; its `DB_USERNAME=postgres` fix must not be committed.
- Useful key paths added across waves (non-exhaustive): `app/Domain/Charges/`,
  `app/Services/GenerateCharges.php`, `app/Console/Commands/GenerateDueCharges.php`
  (verify exact command class name), `app/Domain/Queries/{ChargeTermsHash,
  RecordShareView,RecordVisibilityQuery,BalanceBreakdown,ChargeSchedulePreview,
  NettingSuggestion}.php`, `app/Http/Controllers/RecordShareController.php`,
  `app/Models/{ObligationChargeTerm,RecordShare,Instalment,
  InstalmentAllocation,ObligationShare}.php`,
  `app/Domain/Communication/MessageTemplates.php`,
  `app/Domain/Presenters/HijriDatePresenter.php`, `lang/{en,ms}/`,
  `resources/views/shares/show.blade.php`, `resources/views/help/`.
  Always re-verify with `find`/`grep` — names above are memory, files are truth.

## 8. EXACT resume sequence (run in order, after 16:49 or credit upgrade)

Step 0 — Availability probe (cheap, once):
`codex exec -m gpt-6-luna -c model_reasoning_effort="none" -s read-only -C /Users/saiffil/Herd/debt "reply with the single word: ok"`
Expect `ok`. If still usage-limit, STOP and tell the user the new reset time
from the error; reschedule, do not burn retries.

Step 1 — Dispatch the fix brief (Appendix A; recreate `/tmp/wave4-fix-brief.md`
from Appendix A verbatim if /tmp was cleared):
`codex exec -m gpt-6-luna -c model_reasoning_effort="max" -s workspace-write -C /Users/saiffil/Herd/debt -o /tmp/wave4-fix-out.md - < /tmp/wave4-fix-brief.md > /tmp/wave4-fix.log 2>&1`
Monitor to terminal (`/tmp/wave4-fix-out.md` appears; process exits). Runs in
prior waves took ~10–50 min; logs can reach tens of MB — that is normal.

Step 2 — Orchestrator gate (ALL must pass; quote numbers, never paraphrase):
```
php artisan migrate:fresh --seed --force
php artisan test --compact
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/pint --test --format agent
php artisan charges:generate-due
php artisan reminders:send-due
php artisan route:list --except-vendor
```
Any failure → write a NEW small brief with ONLY those failures (file:line +
quoted message + root-cause hypothesis you verified by reading code) and loop
Step 1. Never edit product code yourself; never weaken/skip tests to pass.

Step 3 — Luna full-diff self-review (REQUIRED before handover): dispatch
read-only max audit (brief template in Appendix B), then a workspace-write max
fix dispatch for its findings, then re-run Step 2 gate. Luna's self-audit must
trace `docs/ship-spec.md` §1–§10 + A1/A2 line by line against `git diff`
(including untracked files via `git status`), list anything unimplemented
and FIX it (no deferrals), and quote grep proofs + command results.

Step 4 — Orchestrator final audit (independent, read + run):
- Spec trace §1–§10 + A1/A2 against code (open cited files; search output
  alone is not evidence).
- Hand-check charge anchors via unit tests + one manual recompute
  (RM15.00 flat, RM90.00 reducing — see §2).
- Share leakage: share page shows confirmed movements only, evidence NAMES
  only (no download), expired/revoked token 404, other records invisible.
- ZIP restore round-trip: RestoreZipTest green + spot-check balances/attachment
  bytes/instalment paid/shares preserved; bad version rejected.
- Privacy: private record invisible to viewer/editor (lists, totals, exposure,
  plans, exports, notifications, attachment 404); creator+owner can view;
  private share only by creator/owner, activity-logged.
- Greps (quote output): `murabahah` 0 in app/resources/lang; `postgress` 0 in
  app/config/phpunit/.env.example; added TODO/FIXME 0 (`git diff -U0 | grep`);
  every `PartyRole` case (Counterparty, Guarantor) has a producer in app code;
  every `MoneyEntry`/`ObligationStatus` case reachable or deleted.
- Fresh migrate from empty on `debt_test` too:
  `DB_DATABASE=debt_test php artisan migrate:fresh --seed --force`.
- Landing smoke: `php artisan route:list` contains shares/help/locale/plans/
  imports/exports.zip routes; `php artisan charges:generate-due` scheduled
  BEFORE reminders in `bootstrap/app.php` (read the file).
- Update todos to completed (orchestrator session tool, if available).

Step 5 — Final report to user (see §10 outline). Do not commit.

## 9. Traps that already bit this build (avoid re-creating them)

- Postgres `sum()`/`count()` aggregates return STRINGS — cast `(int)` in tests.
- Eloquent enum casts return ENUM objects — never `(string) $enum`; handle
  `enum|string|null` (Plans\Show crash). `->value('col')` DOES apply casts.
- Self-referencing FK inside `Schema::create` fails on PG — add FK after create.
- Export download test: export id is in URL PATH basename, not query string.
- Blade `@can` gaps leak action WORDS to viewers (assertDontSee catches it) —
  wrap whole action sections, and `Gate::authorize` inside Livewire methods.
- i18n: en values must be byte-identical to historic strings or dozens of
  assertSee tests break. Default locale must be `en` for factory users.
- Luna sandbox: DB blocked, phpstan parallel EPERM — see §4 workarounds.
- Codex log tail can look frozen for 10+ min while file grows (self-audit
  diff dumps). Check process + log mtime/size before declaring a stall.
- Another unrelated Codex process (`/tmp/panel-affiliates/...`) may be running
  for a different project — never kill/touch processes you did not start.

## 10. Final report outline (when §8 completes — one self-contained message)

1. Top line: shipped outcome in plain words (what a user can now do).
2. What was built per wave (Waves 1–4), incl. charges automation design
   (manual / auto-propose / auto-apply + versioned acceptance + guard).
3. Audit→product trace: how each original misery (ambiguity, inability,
   unwillingness/dispute, power, shame) is now addressed — honestly, incl.
   limits (app cannot create money or force payment).
4. Verification numbers quoted from tools (tests passed/assertions, phpstan,
   pint, migrate:fresh, commands) — exact terminal lines, not paraphrase.
5. Files/areas changed (grouped paths as links), migration approach, no-BC note.
6. Parked HUMAN items (need user, code cannot finish them): production
   mail/domain credentials; legal review of help/template wording; Shariah
   review of labels (UI already says "Labels here are not Shariah
   certification."); payment-gateway merchant account (ship uses verified
   destinations + wa.me deep links, no gateway); WhatsApp Business API (same).
7. Interruption note: Codex usage limit hit during Wave 4, recovered via
   scheduled resume; final state re-verified green after recovery.
8. How to run locally: Postgres role `postgres`, DBs `debt`/`debt_test`,
   `php artisan migrate --seed`, `composer run dev` / Herd site `debt.test`,
   seed login `test@example.com` / `password` (DatabaseSeeder).
9. Explicit statement: nothing committed/pushed; working tree left for review.

## Appendix A — Wave 4 fix brief (verbatim; save as `/tmp/wave4-fix-brief.md`)

```
Fix Wave 4 leftovers in /Users/saiffil/Herd/debt. Orchestrator Postgres gate: 190 passed, 11 failed; phpstan 0; pint pass. Your previous run hit usage limit before handover/self-audit — after these fixes, ALSO do the Wave 4 self-audit vs /tmp/wave4-brief.md items 1-15 and produce the handover output that file required (files by area, commands+results, self-audit, grep proofs, assumptions).
NOTE from handoff: /tmp/wave4-brief.md may be gone; the authoritative scope is docs/ship-spec.md Batch 6+7 items (§7, §9, §10) + this HANDOFF §5 Wave 4 list. Self-audit against THOSE.

Verified failures to fix (fix APP by default; touch tests only where wording intentionally changed per spec A2, and list each):
1. ChargesEngineTest schedule-preview + WaveOneFoundation exchange-rates: page no longer contains exact 'Avoid interest (riba) and percentage penalties'. A2 mandates that exact English string via charges.guard.label. Ensure en value exact and default-locale rendering shows en for factory users (check SetLocale fallback + users.locale default/factory).
2. PromiseTrackerTest expects 'You owe them' / 'They owe you' on index/show — i18n Direction labels in en MUST keep exactly those historic strings (check lang/en/promises directions + blade usage; default locale must be en).
3. PartyRichnessTest expects 'Ali — assistant of → Ahmad' — relationship label rendering changed by i18n; en label must reproduce that format (em dash, 'assistant of', arrow) or adjust blade to use enum label() as before in en.
4. ShareConsentTest (3): POST acknowledge/dispute redirect now appends '?lang=en', tests assert exact shares.show URL. Fix APP: only preserve lang query when request explicitly had lang param (or non-en locale); default en redirect stays clean. Also share page expects 'fixed total, agreed upfront' exact A2 wording — align share.php en value/casing.
5. ProfileSettingsTest ValidationException 'The show hijri field is required' — UpdateFinancialProfileData must treat show_hijri as sometimes|boolean default false (existing callers omit it). Same tolerance for interest_free_mode if similarly required and omitted by old callers.
6. CsvImportFormatsTest major-amount: Carbon InvalidFormatException escapes ('Unexpected data found. Trailing data') — date parsing must try each format safely (catch Throwable / check errors) and finally throw ValidationException for unparseable dates, never raw Carbon exception. Ensure d/m/Y and 'd M Y' rows parse.

Then: pint --dirty, phpstan app (sqlite --debug trick), php -l touched. Do not run DB tests. Self-audit Wave 4 scope 1-15 with grep proofs (PartyRole producers, postgress 0 in app/config, murabahah 0, added TODO 0). Quote results.
```

## Appendix B — Luna full-diff self-review brief template (Step 3)

```
Read-only audit. Repo /Users/saiffil/Herd/debt. Read docs/ship-spec.md (A1+A2 binding) and HANDOFF.md §5. Review the FULL uncommitted diff (git diff + git status untracked) against the spec section by section (§1-§10, A1, A2). Start from zero: distrust handover notes including your own prior ones; every claim needs a quoted command (grep/cat) with file:line. Categories: FATAL (spec contradicted / money wrong / privacy leak), GAP (spec line unimplemented — name user harm), RISK (security, double-charge, data loss, labelling), DEAD (enum/action/route with zero producers — grep app/resources/config/routes/database/tests). Hand-recompute the two charge anchors (RM15 flat monthly 1.5% on RM1000; RM90 reducing daily 1.5% x10d on RM600) from calculator code and report inputs/outputs. Output: verdict SHIP-READY or NOT-READY + counts; findings grouped with evidence; anchors; unverified list. Do NOT modify files in this pass.
```
Then dispatch a workspace-write fix brief containing ONLY those findings
(same formatting discipline as Appendix A), re-run §8 Step 2 gate, and loop
only if new failures appear.

## Appendix C — One-line status for the user at any moment

"Waves 1–3 green (187 tests). Wave 4 written, 190 passed / 11 diagnosed
failures, static gates clean. Blocked only on Codex Luna usage limit until
16:49 Sat 17 Oct 2026; resume scheduled 16:55 (job bfe895e0) to fix, self-review,
final-audit and report. Nothing committed."

## 11. Filament self-audit remediation — 19 Oct 2026

The follow-up to `/tmp/luna-filament-self-audit-out.md` was applied per the
current user request. The relevant `HANDOFF.md` traps still apply: keep
Postgres as the test database, cast aggregate strings in assertions, and do
not add backward-compatibility shims.

### Accepted with no code change (explicitly requested)

- `MustVerifyEmail` remains on `User`: customer routes already declare their
  `auth,verified` intent, and verified email is a staff-panel requirement.
- The permission-table migration remains fresh-install-only. This app has not
  shipped; no deployed database has run the vendor migration that could
  collide with the app-owned final migration.
- The money and scheduler source differences are accepted as pre-existing
  ship-wave changes covered by the user's stated 249-test gate. They are out
  of scope for this correction.

### Changes in this correction

- The staff MFA middleware is persistent on Livewire requests, rejects
  markers older than 900 seconds, and clears invalid markers. The app TOTP
  provider is configured with a zero-step window.
- Audit rows reject model creation outside `SupportAuditLogger`; logger
  metadata is scalar-only; the final-shape migration blocks PostgreSQL UPDATE
  and DELETE statements with a trigger.
- The case workspace includes grant-gated attachment metadata and quantity
  subject details, rechecks the same active grant after data reads, and logs
  profile-data views once per case/grant/session.
- The Authz session guard subclass is retained and receives `app.key` for
  remember-cookie HMACs. `config/filament-authz.php` keeps only the
  plugin-independent impersonation disable switch.

Verification: `vendor/bin/pint --dirty --format agent` passed;
`DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpstan analyse --debug
--memory-limit=1G --no-progress` reported `{"tool":"phpstan","result":"passed","errors":0}`;
all touched PHP files passed `php -l`; `php artisan view:cache` reported
`Blade templates cached successfully`; `npm run build` reported `✓ built in
2.79s`; and `git diff --check` exited 0. DB tests were not run per the user's
instruction. The 249-test baseline is the result supplied by the user, not a
post-change rerun.
