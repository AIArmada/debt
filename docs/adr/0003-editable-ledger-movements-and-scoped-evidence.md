# Allow audited movement corrections and scoped evidence forms

Ledger movements are editable during development, but every correction records before-and-after values and recalculates later confirmed balances so the visible ledger remains internally consistent. Evidence is a scoped record that may belong to the whole obligation, one movement, or one fulfillment event, and may be captured as a file, external link, or written note; this keeps supporting context close to the fact it explains without pretending every form of evidence is a document.

## Considered Options

- Make movements immutable and require a reversing entry for every correction: rejected for the current product stage because ordinary data cleanup would be unnecessarily cumbersome, while the audit log already preserves the correction history.
- Keep evidence as uploaded files only: rejected because messages, links, witness statements, and contemporaneous notes are valid supporting records even when no file exists.
