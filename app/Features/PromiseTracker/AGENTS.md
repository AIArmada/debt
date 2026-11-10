# PromiseTracker

This application currently has one owning capability: the promise ledger, including
party records, profiles, evidence, reminders, imports, support cases and staff actions.
They share the same profile/record aggregate, so they stay one feature until a real
public boundary is worth introducing.

## Where to work

| Change | Location |
| --- | --- |
| Records, guarantees, visibility, notes | `Actions/Records` |
| Obligations, statuses, charge terms, shares | `Actions/Obligations` |
| Money movements, instalments, offsets, voids | `Actions/Money` |
| Quantity returns and corrections | `Actions/Quantities` |
| Budget periods and repayment plans | `Actions/Plans` |
| CSV and profile-export imports | `Actions/Imports` |
| Record shares and disputes | `Actions/Shares` |
| Evidence attachments | `Actions/Evidence` |
| Parties, contacts, payment destinations | `Actions/Parties` |
| Profiles, members, invites, tokens, exchange rates | `Actions/Profiles` |
| Reminder scheduling and delivery | `Actions/Reminders` |
| Staff/support workflows | `Actions/Staff`, `Actions/Support` |
| Notification read state | `Actions/Notifications` |
| Authentication actions, logout | `Actions/Auth` |
| Shared action input DTOs | `Actions/Data` |
| Cross-cutting write helpers | `Persistence` |
| Reads, value objects, enums, queries | `Domain`, `Services` |
| HTTP, Livewire, Filament, commands, jobs | `EntryPoints`, `Commands`, `Jobs` |

Entrypoints adapt transport; concrete Actions implement local use cases.
Add Api contracts/values only when another feature needs a public boundary.
Read feature.json for durable resource ownership before changing writes.
Keep Models internal. Named Actions/Persistence classes listed in feature.json may write owned state.
If you add a write path, add its class to the matching resource in feature.json.
If overriding FeatureServiceProvider::boot(), call parent::boot().

Framework-managed tables (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`, `password_reset_tokens`, `passkeys`) are intentionally not owned here.
Spatie/Authz package tables are vendor-managed except `model_has_roles`, which the
staff-role actions own and the write rule tracks. `model_has_permissions` is tracked
by the rule but has no application writer yet.

## Verification

Tests need the phpunit drivers from `phpunit.xml`; only export the DB/Redis values
from `.env`, then override the test database and schema:

```sh
set -a; eval "$(grep -E '^(DB_|REDIS_)' .env)"; set +a
DB_DATABASE=promise_staging DB_SEARCH_PATH=promise_test php artisan test --compact
composer architecture:test
composer types:check
vendor/bin/pint --test --format agent
```
