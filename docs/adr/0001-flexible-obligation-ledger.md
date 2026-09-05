# Use one transaction stream for snapshot and detailed obligation tracking

The application keeps new obligations as current-balance snapshots by default, while allowing a record to use a detailed ledger of dated balance movements. A snapshot is an opening point, not a permanent restriction: recording the first movement promotes the record to detailed ledger tracking so later balance edits cannot silently diverge from the movement history. Detailed movements extend the existing financial transaction stream with an entry type, an explicit increase/decrease effect, and the balance after confirmation, instead of introducing a second ledger table; this preserves compatibility with payment providers and bank imports while keeping one auditable timeline. A signed current balance may cross zero: the original direction remains historical context, while the current position is derived as payable, receivable, or settled. General evidence remains linked to the obligation, and transaction evidence is linked to the specific movement.

## Considered Options

- Create a separate ledger table and duplicate or project payment transactions into it.
- Replace the existing transaction model with a new event-sourced model.

The alternatives would provide a cleaner long-term event model but add migration complexity and create avoidable risks of two balance histories diverging during the current product stage.
