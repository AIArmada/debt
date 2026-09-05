# Debt management context

This context describes the language used to record, understand, and evidence real-world arrangements when the available history may be incomplete. A record may contain one or more financial or non-financial obligations.

## Records, obligations, and balances

**Record**:
A case or arrangement involving a profile and one or more parties. It is the container for related obligations, shared context, general evidence, and activity. A person or organisation may have several separate records when the arrangements are unrelated.
_Avoid_: Person or account when referring to the arrangement itself.

**Party**:
A real-world person, organisation, household, team, estate, institution, or other entity involved in an arrangement. A party is not an application user: it may be unverified, may not have an account, and may appear in many records within the same profile.
_Avoid_: Using one generic role label when the party may be a beneficiary, guarantor, witness, representative, payer, payee, custodian, or another specific role.

**Record participant**:
A party's role in a record or in one of its obligations, such as debtor, creditor, beneficiary, guarantor, witness, representative, contact person, payer, payee, custodian, or service recipient. Participation is contextual: the same party may have different roles in different records, and a record may include multiple parties.
_Avoid_: Treating a party's name as proof of what they are responsible for.

**Party relationship**:
A relationship between two parties, such as an organisation and its contact person, a principal and personal assistant, a team and its members, or a beneficiary and their representative. It is separate from a party's role in a particular obligation.
_Avoid_: Storing an assistant, team member, or representative as if they were the beneficiary or creditor.

**Contact point**:
A purpose-specific way to contact a party, such as a phone number, email address, messaging handle, website, or postal address. A party may have multiple contact points, each with a label, preferred status, verification state, and validity period.
_Avoid_: Assuming one email or phone field is the party's complete contact identity.

**Payment destination**:
A versioned, purpose-specific instruction for sending money to a beneficiary or payee, such as a bank account, wallet, cash handover arrangement, or provider reference. It is separate from a party's general contact points and must be masked, access-controlled, and independently verified before use.
_Avoid_: Treating a phone number, party profile, or unverified account number as a payment instruction.

**Contact route**:
A deliberate way to reach a party, either directly through one of their contact points or indirectly through another party such as a personal assistant, relative, representative, or trusted contact. It records the purpose, priority, permission, and validity of the route without pretending that the intermediary is the party being contacted.
_Avoid_: Copying an assistant's phone number onto the principal party or treating an intermediary as the beneficiary, debtor, or creditor.

**Delivery destination**:
A place or handover arrangement for returning or delivering an asset, document, item, or other physical subject. It may be a party address, a collection point, a named person, or a free-form handover instruction, and it is separate from a money payment destination.
_Avoid_: Calling an address a bank account or assuming the party's primary address is always the correct handover location.

**Fulfilment instruction**:
The selected, dated instruction for resolving one obligation, such as which payment destination to use, which contact route to follow, or where an item should be delivered. It keeps a snapshot of what the user was shown so later changes to a party profile do not rewrite history.
_Avoid_: Treating the party directory alone as proof of how an obligation was settled.

**Obligation kind**:
The form of subject that is outstanding within one obligation: money, an asset/item, a service/time commitment, or an action/commitment.
_Avoid_: Debt type when the record may be something other than money.

**Obligation category**:
A kind-specific context for grouping and understanding an obligation. Money categories describe the financial source or purpose, asset categories describe the kind of thing involved, service categories describe the work or time owed, and action categories describe the promise or responsibility to be completed.
_Avoid_: One universal category list shared by money, assets, services, and actions, because “bank financing,” “borrowed item,” “repair,” and “confidentiality” answer different questions.

**Obligation**:
A single payable or receivable commitment inside a record. It has one obligation kind, its own terms, current position, movements or fulfillment events, and supporting evidence.
_Avoid_: Account when referring to the person or profile that owns the record.

**Mixed-obligation record**:
A record containing two or more obligations, potentially with different kinds or directions. Its summary reports each obligation separately and does not invent one combined balance.
_Avoid_: Net debt when the record contains different subjects or opposing positions.

**Record-level evidence**:
Evidence that supports the arrangement as a whole, such as a shared agreement, identity document, or general statement. Evidence about one obligation or event belongs with that narrower subject.
_Avoid_: Duplicating one document across every obligation it supports.

**Snapshot tracking**:
A way to start with the balance currently known without requiring the user to reconstruct the obligation's past. A snapshot is a valid opening point for future movements; recording the first movement promotes the record to detailed ledger tracking.
_Avoid_: Simple debt when describing the financial commitment itself.

**Detailed ledger tracking**:
A way to start from a known opening balance and record dated balance movements as they happen. A record also enters this mode automatically when a movement is added to a snapshot, so the current balance cannot silently diverge from its movement history.
_Avoid_: Full history, because the ledger may begin after the obligation itself began.

**Current balance**:
The signed net amount at the latest confirmed point in time, measured relative to the original direction. A positive balance follows the original direction, zero is settled, and a negative balance means the current position has reversed.
_Avoid_: Original amount, which is optional historical context and may be unknown.

**Current position**:
The present direction and absolute amount derived from a money obligation's signed current balance. It may be “You owe them,” “They owe you,” or “Settled,” regardless of the original direction.
_Avoid_: Direction when describing the live position after movements.

**Original direction**:
The direction of the obligation when it was created: payable when the user was expected to pay, return, or perform; receivable when the other party was expected to pay, return, or perform. It remains historical context even if the current position reverses.
_Avoid_: Current position when describing the original arrangement.

**Opening balance**:
The amount outstanding when tracking begins, whether or not it was the amount originally borrowed or advanced.
_Avoid_: Original amount when historical provenance is uncertain.

**Outstanding quantity**:
The amount of an asset or service that still needs to be returned or delivered, measured in its stated unit. The unit is either countable as whole units or measurable and allowed to use a fractional amount.
_Avoid_: Balance when the subject is not money.

**Countable quantity**:
A quantity made up of whole units that cannot be meaningfully split for the obligation, such as one camera, two vehicles, or three documents. It must be recorded as an integer.
_Avoid_: Treating a partial number as a partial item unless the obligation explicitly concerns a measurable substance or dimension.

**Measurable quantity**:
A quantity measured on a scale or continuous unit, such as grams, kilograms, litres, metres, hours, or days. It may be recorded with a fractional amount when the unit supports it.
_Avoid_: Assuming every asset or service can be divided into fractional units.

**Estimated value**:
Optional monetary context for an asset; it does not turn an asset obligation into a money obligation.
_Avoid_: Current balance for an item's reference value.

**Native currency exposure**:
The outstanding signed balance for one currency within a money obligation. A record may have a primary currency and additional movement currencies; each exposure remains separate and is never added to another currency without an explicit conversion request.
_Avoid_: Total balance when the record contains more than one currency.

**Currency total**:
The sum of current positions that share the same currency. Currency totals are safe to add within that currency only.
_Avoid_: Base-currency total when no dated conversion has been explicitly selected.

**Explicit conversion**:
A separately requested comparison using a dated, saved exchange rate. It is an estimate for comparison and does not rewrite native amounts, movement history, or currency totals.
_Avoid_: Automatic conversion, especially when describing the authoritative balance.

**Precision boundary**:
Money is stored as integer minor units (for example, sen), while quantities, rates, and percentages use exact fixed-scale decimal database columns and decimal-string calculations. No money or domain measurement is stored as a floating-point value. Formatting and removal of insignificant zeroes happen only at the presentation boundary.
_Avoid_: Using floating-point arithmetic for a value that will be persisted or compared.

## Ledger movements and evidence

**Ledger entry**:
A dated financial movement recorded against one obligation, with a positive amount and an explicit increase or decrease effect.
_Avoid_: Transaction when discussing a non-financial action or a planned payment that has not changed the balance.

**Advance**:
An additional amount that increases the outstanding balance, usually because more principal was lent, borrowed, or supplied.
_Avoid_: Top-up when the event needs to be understood in a legal or financial record.

**Charge**:
An amount added to the balance for interest, profit, late fees, storage, or another agreed cost.
_Avoid_: Fee when the amount is specifically interest or profit.

**Payment / collection**:
A confirmed decrease of a payable / receivable balance respectively.
_Avoid_: Repayment for a generic decrease, because receivables are collected rather than repaid.

**Collection account**:
A profile-owned receiving destination used as an optional reference for money the user expects to collect. Its identifier is encrypted at rest and only the masked form is shown in schedules, exports, and ordinary lists.
_Avoid_: Treating a collection account as proof that money was received; only a confirmed collection movement changes the balance.

**Collection schedule**:
An expectation for money to be collected on a cadence, such as RM5 every day. It is a follow-up or reconciliation aid linked to a receivable, not an automatic payment, bank connection, or balance movement.
_Avoid_: Counting every scheduled occurrence as received before the user confirms a collection or reconciles a bank import.

**Collection context**:
A dated note about why an expected collection was missed, postponed, promised, or otherwise needs attention. It may have its own evidence and does not alter the ledger balance.
_Avoid_: Using a context note to silently reduce, increase, or settle the receivable.

**Adjustment**:
A deliberate correction to the balance whose increase or decrease effect is stated explicitly.
_Avoid_: Edit, because changing a balance without recording why loses the audit trail.

**Evidence**:
A private record that supports an obligation generally or one particular ledger entry or fulfillment event. Evidence may be an uploaded file, an external reference, or a written note.
_Avoid_: Attachment when the item is intended to be relied on as a record.

**Evidence form**:
The medium in which evidence is captured: uploaded file, external link, or written note.
_Avoid_: Evidence type when describing the medium rather than what the evidence is about.

**Evidence category**:
The subject of evidence, such as an agreement, receipt, statement, message, condition photo, audio or video record, pawn ticket, valuation, identity record, or witness statement.
_Avoid_: File type, because a PDF, image, audio file, or link can support different kinds of evidence.

**General evidence**:
Evidence that supports the obligation as a whole, such as an agreement, statement, or identity document.

**Transaction evidence**:
Evidence that supports one ledger entry, such as a receipt, bank confirmation, or message about that specific movement.

**Movement correction**:
An edit to a recorded ledger entry that keeps the movement's audit trail while recalculating its balance effect and the balances of later confirmed entries.
_Avoid_: Silent balance edit, because a correction must preserve why the ledger changed.

**Fulfillment event**:
A dated update about adding scope, returning, replacing, progressing, missing, waiving, or completing a non-money obligation.
_Avoid_: Financial transaction when no money changed hands.

**Asset obligation**:
An obligation whose subject is a physical item, document, digital item, data, access credential, or other asset that must be returned, replaced, or otherwise resolved.

**Service obligation**:
An obligation whose subject is work, care, transport, professional help, or time owed. It may have a measurable quantity and can be fulfilled in parts.

**Action obligation**:
An obligation whose subject is a promised action, deliverable, or outcome rather than an amount of money or a physical thing.

**Conditional obligation**:
An obligation that only applies after a described condition or event, with an optional date recording when that condition was triggered.

**Settled**:
The state in which the outstanding subject has been resolved: the money is paid/collected, the asset is returned/replaced, the service is delivered, or the action is fulfilled.
_Avoid_: Paid as a universal status.

## Money representation

**Minor unit**:
The smallest accounting unit for a currency, such as sen for MYR or cents for USD. All monetary database columns and in-memory balance arithmetic use signed integers in minor units; for example, RM4,280.00 is stored as 428000.
_Avoid_: Storing formatted currency strings or floating-point balances.

**Major-unit input**:
A human-entered amount such as `4280.00`. Forms and the readable API fields accept major units, then convert at the application boundary before persistence. Machine integrations may use explicit `amount_minor` or other `*_minor` fields.
_Avoid_: Treating an unlabelled integer as both RM100 and 100 sen.

**Currency precision**:
The number of decimal places defined by the selected currency. Money input is validated against the currency precision, so zero-decimal currencies such as JPY cannot receive fractional values.
_Avoid_: Applying a universal two-decimal rule to every currency.

**Money formatter**:
The shared AIArmada Commerce Support formatter backed by Akaunting Money, used for currency-aware display, input conversion support, and currency precision metadata.
_Avoid_: Calling `number_format` for monetary values.

## Planning and repayment continuity

**Budget period**:
A time-bounded view of the money available in one profile and one currency after recorded income, expenses, and a protected reserve. A budget period describes capacity; it does not itself promise that money will be paid to any obligation.
_Avoid_: Treating the budget as a repayment record.

**Repayment capacity**:
The amount that can reasonably be allocated to payable money obligations for a budget period. It is shown with its inputs—income, essential expenses, flexible expenses, and reserve—so the user can understand how the amount was reached.
_Avoid_: Calling the amount available a payment when no payment has happened.

**Repayment plan**:
A dated, currency-specific proposal for distributing repayment capacity across selected payable money obligations in a profile. A plan is an intention and never changes an obligation balance by itself.
_Avoid_: Treating a generated plan as proof of payment.

**Plan allocation**:
One plan's proposed amount for one obligation and one currency exposure, grouped under its record. It includes priority, minimum target, additional target, and the amount actually realised so progress can be compared without changing the ledger.
_Avoid_: Using an allocation as a second balance for the obligation.

**Planned amount**:
The amount a repayment plan proposes to pay during its period. It is distinct from the obligation's current balance and from confirmed money movements.
_Avoid_: Showing a planned amount as paid.

**Actual payment**:
A confirmed ledger movement that decreases a payable money exposure. It changes the obligation's current position and may be linked to a plan allocation for reconciliation.
_Avoid_: Counting a draft, failed, or unrelated movement as paid toward a plan.

**Plan progress**:
The comparison between a plan allocation and linked confirmed payments in the same plan period: planned, on track, partly paid, paid, short, or needs review.
_Avoid_: Inferring progress from the current balance alone, because advances, charges, adjustments, and payments have different meanings.

**Planning currency**:
The currency in which a budget period and repayment plan are calculated. Only native exposures and cash-flow entries in that currency are included; other currencies are shown separately and are never silently converted.
_Avoid_: Adding MYR, USD, or other native amounts into one total without an explicit dated conversion.

**Profile repayment summary**:
A currency-grouped view across all money obligations in a profile, showing current outstanding, minimums, planned amounts, actual payments, and remaining amounts. Non-money obligations remain visible as separate counts and are not assigned monetary totals.
_Avoid_: Calling a mixed-currency or mixed-obligation summary a single net debt total.

**Actual repayment**:
A confirmed payment made during a budget period in its planning currency. It is shown separately from repayment capacity so the same money is not treated as both available and already spent.
_Avoid_: Reducing the budget's recorded income or expense entries when a repayment is recorded.

**Plan review state**:
A visible state indicating that a confirmed movement changed the assumptions behind a repayment plan. The plan remains available for reference until the user explicitly recalculates it.
_Avoid_: Silently changing a plan allocation after a charge, advance, adjustment, or unplanned payment.

**Recalculated plan**:
A new repayment plan generated from the current balances and remaining budget capacity after the user confirms a review. The prior plan remains as a superseded historical proposal.
_Avoid_: Overwriting the previous plan and losing the basis on which an earlier decision was made.

**Plan lifecycle**:
A repayment plan may be active, paused, needs review, completed, or superseded. Only one plan for the same budget period and currency may be active; paused plans remain available to resume, while a refreshed plan supersedes the stale version it replaces.
_Avoid_: Treating every generated plan as active or deleting an earlier planning decision.

**Plan refresh**:
An explicit confirmation that creates a new plan version from the current balances and budget capacity after a movement changed a saved plan's assumptions. The refresh carries confirmed payments already made in the budget period into the new version without relinking or changing the original movements.
_Avoid_: Automatically rewriting a saved plan when the ledger changes.

## Current application contract

The application accepts only the current domain names and shapes:

- Records link to a party_id; record creation uses the party object for creating a new party.
- Financial movements use entry_type as their single ledger classification. The old transaction type column is removed.
- Quantity-based obligations and pledged assets persist an explicit quantity_mode; the model does not infer it from a unit or from an older record shape.

_Avoid_: Reintroducing aliases or silent fallbacks for removed field names. Historical migration files remain only to reproduce the development schema; they are not application compatibility code.

## Semantic UI signals

The interface uses one shared color vocabulary across records, obligations, movements, plans, evidence, imports, schedules, and dashboards. The text label is always shown with the color, so color is a reinforcement rather than the only meaning.

| Signal | Meaning | Examples |
|---|---|---|
| Info / blue | Open, active, in progress, or planned | Open record, active plan, OCR processing |
| Success / green | Resolved, confirmed, recorded, or verified | Settled obligation, recorded import row, verified evidence |
| Warning / amber | Needs review, approaching a deadline, or awaiting confirmation | Plan review, due item, paused authorisation |
| Danger / rose | Urgent, overdue, failed, or reversed | Overdue pawn maturity, failed movement, reversed position |
| Neutral / slate | Inactive, historical, optional, or not applicable | Paused plan, read notification, missing maturity date |
| Incoming / teal | Value or subject is expected back to the user | Money to receive, service owed to the user |
| Outgoing / rose | The user must pay, return, or perform | Money to pay, asset to return, action to complete |
| Type colors | What kind of obligation is being tracked | Money blue, asset amber, service teal, promise/action violet |
| Priority colors | Relative order of attention | High rose, medium amber, normal teal |

These signals are implemented through the reusable `status-badge`, `direction-badge`, `obligation-badge`, `priority-badge`, and `risk-badge` Blade components plus the `app-status-*` design tokens in the application stylesheet.
