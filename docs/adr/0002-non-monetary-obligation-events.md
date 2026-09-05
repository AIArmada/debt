# Keep non-money fulfillment events separate from financial transactions

An obligation can represent money, an asset/item, a service/time commitment, or an action/commitment. Money has balance, currency, payment-provider, and reconciliation semantics, while non-money obligations need quantity/progress/fulfillment history, so non-money obligations use `obligation_events` and keep `financial_transactions` strictly monetary. Asset and service quantities share one subject model; quantity-changing events record an explicit increase or decrease. This keeps one obligation record and one evidence model while avoiding fake currencies, RM0 balances, and misleading payment records.

## Considered Options

- Store every update in `financial_transactions`: rejected because item returns and action progress are not financial movements and would corrupt payment-provider/reporting assumptions.
- Create separate top-level models for items and actions: rejected because access control, evidence, profiles, sharing, and dashboard navigation are shared obligation concerns.
- Use one generic event stream for money and non-money: rejected for now because monetary entries already have balance-effect and settlement invariants that should remain explicit.
