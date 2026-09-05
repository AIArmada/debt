# Keep mixed-currency movements native and separate

Money obligations keep a primary currency for their opening balance, but each confirmed movement may use any supported currency. The application stores a signed balance for every currency exposure and shows totals grouped by currency. It never adds MYR, USD, or another currency together and never silently applies inflation-sensitive or market-sensitive exchange assumptions.

An exchange rate remains available only as an explicit comparison tool. A dated saved rate can produce an estimate when a user asks for one, but it does not change the native movement amount, the native balance, the audit trail, or the dashboard's authoritative currency totals.

## Considered options

- Force every movement to the record currency: rejected because real obligations may be settled through different currencies and bank imports must preserve their source currency.
- Automatically convert every movement to the profile base currency: rejected because it creates false precision and hides the amount actually agreed or transferred.
- Add all currency amounts into one numeric total: rejected because the arithmetic is meaningless without a chosen rate and rate date.
