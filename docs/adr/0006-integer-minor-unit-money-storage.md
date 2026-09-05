# ADR 0006: Store money as signed integer minor units

- Status: Accepted
- Date: 2026-09-04

## Context

Debt records can contain balances, movements, fees, schedules, budgets, imports, pledged-asset valuations, and repayment projections. These values must remain exact when they are added, subtracted, compared, exported, or exposed through the API. Formatted values and floating-point decimals make it easy to lose a cent or confuse a display value with a stored value.

The application uses `aiarmada/commerce-support` with Akaunting Money for currency-aware formatting and precision metadata.

## Decision

All monetary database columns use signed integer minor units. A MYR or USD value of `4280.00` is stored as `428000`; a zero-decimal JPY value of `1500` is stored as `1500`. Balance arithmetic, ledger snapshots, projections, imports, plans, schedules, and notification metadata use the same integer representation.

Forms and readable API/export fields accept or return major-unit decimal strings. Explicit `*_minor` fields are available for machine integrations and make the storage representation unambiguous. Currency codes are selected from the supported currency list, and currency precision is enforced at the input boundary.

Different currencies remain separate native exposures. The application never silently converts or adds them; a dated explicit conversion is a comparison only and does not rewrite native values.

Because this application is still in development, the fresh schema is the source of truth and there is no compatibility layer for the previous decimal representation.

## Consequences

- Exact integer arithmetic is used for every balance and monetary aggregate.
- Display and input formatting is centralized through Commerce Support / Akaunting Money.
- Rates and measurable quantities remain decimal because they are not money amounts.
- Existing development data must be recreated or intentionally migrated before using the new schema.
