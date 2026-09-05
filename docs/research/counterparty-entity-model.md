# Party entity model research

**Status:** Research note

**Date:** 2026-09-05

**Scope:** Whether debt records should reference real parties, how to model multiple parties and roles, contact and payment instructions, auditability, consent, data minimisation, retention, deletion, and privacy.

**Method:** Primary or first-party sources only: standards specifications, official payment/API standards, Malaysian Personal Data Protection Commissioner material, and NIST controls. Repository inspection was used only to describe the current vocabulary and implementation shape. This is product/domain research, not legal advice.

## Executive finding

Yes: a record should reference a real `Party` entity rather than treating the other side as a name field. The application now uses a profile-scoped `parties` directory and role-bearing relationship tables. Records link to parties through `record_parties`; individual obligations link through `obligation_parties`; and movements can link payer or payee parties through `financial_transaction_parties`.

That shape supports arrangements involving a company and its contact person, two joint debtors, a guarantor, a witness, a payer acting for somebody else, a beneficiary different from the creditor, or multiple people in a team. Each relationship can say what the party is responsible for, when that role applied, and whether the party is a person, organisation, group, estate, or an unverified placeholder.

The recommended direction is:

1. Make `Party` the durable identity record, with individual, organisation, group/household, estate/trust, and unidentified-party variants.
2. Use a role-bearing record-party relationship instead of a singular record link.
3. Add obligation-level party roles for cases where different obligations in one record involve different people or where the same person has different responsibilities.
4. Keep contacts, organisation members/representatives, and payment destinations as separate, repeatable, permissioned entities.
5. Treat party, role, contact, payment-destination, consent, and evidence changes as auditable events.
6. Do not force a full contact profile at record creation: allow a minimally identified and explicitly unverified party, then let the user enrich it later.

This preserves the application’s existing distinction between a `Record` (the arrangement), an `Obligation` (one subject owed), movements/events, and evidence, while giving the arrangement a truthful set of participants.

## What the repository currently says

The existing vocabulary is already close to the right conceptual boundary:

- `Record` is the arrangement and may contain multiple obligations.
- `Obligation` is a single money, asset, service, or action commitment.
- Evidence can belong to the record, obligation, movement, or fulfilment event.
- The application already distinguishes payment instructions from financial transactions.

The current party domain is structural rather than cosmetic:

- `parties` is profile-scoped and stores kind, preferred/legal names, aliases, identifiers, status, verification, and provenance.
- `record_parties`, `obligation_parties`, and `financial_transaction_parties` preserve many-to-many participation with explicit roles.
- `PartyContact`, `PartyAddress`, and `PartyPaymentDestination` are repeatable, permissioned, and independently verifiable.
- API resources, exports, dashboards, plans, reminders, notifications, and audit events consume the same party relationships.
- `ObligationPaymentInstruction` records the selected, versioned destination for an obligation without making it the same thing as a completed payment.

Therefore, the product description is: **a record is an arrangement, a party is a reusable real-world entity, and each relationship states how that party participates.**

The wider application audit adds four important constraints:

- `RecordPolicy::view` and `DocumentPolicy::view` authorise access from the profile role, but do not enforce the record's `sensitivity` value. The current private/shared distinction is therefore presented in the UI but is not a complete access boundary.
- Communication composition selects a message-safe contact from the party’s repeatable contact points, rather than assuming one email address.
- `RecordResource` and exports return structured parties and roles; dashboards, plans, reminders, notifications, and audit views use the same relationship model.
- Party-owned payment destinations are separate from obligation-specific instructions and are masked in ordinary displays.

## Evidence from standards and official guidance

### A party is more than a display name

The IETF vCard standard is deliberately broader than a single contact string. It represents individuals and other entities, including organisations and groups; it supports structured names and addresses, multiple telephone numbers, multiple email addresses, roles, organisation membership, related entities, and other communication properties. The standard also distinguishes an organisation from a group: an organisation is one entity, while a group can represent a collection of members. [IETF RFC 6350, vCard Format Specification](https://www.rfc-editor.org/rfc/rfc6350/)

This supports keeping identity and contact details as a reusable entity, while allowing many contact methods and organisation/group structures. It does not mean the debt app should implement vCard wholesale; it is evidence that a contact model should not collapse a person or organisation into one text field.

The HL7 FHIR specification provides a useful, deliberately labelled analogy for domain modelling. `RelatedPerson` has a relationship type and repeatable contact details; `Organization` supports organisation hierarchy and multiple contacts; FHIR `Account` models one or more guarantor parties and gives each guarantor a period during which responsibility applies. [FHIR RelatedPerson](https://hl7.org/fhir/relatedperson-definitions.html), [FHIR Organization](https://hl7.org/fhir/organization.html), and [FHIR Account definitions](https://www.hl7.org/fhir/R5/account-definitions.html)

FHIR is not a finance requirement for this product. The useful design lesson is the separation of **party**, **relationship**, **contact point**, and **time-bounded responsibility**.

### Payment roles are not the same as one generic party role

ISO 20022 payment material distinguishes the initiating party, debtor, ultimate debtor, creditor, and ultimate creditor, and explicitly describes situations where those roles are played by the same actor or by different actors. [ISO 20022 Payments maintenance material](https://www.iso20022.org/message/mdr/12666/download/111) The payment model therefore supports a real-world case such as a head office initiating a payment for a subsidiary, or an account owner paying a beneficiary on behalf of somebody else.

For this application, the implication is that `debtor`, `creditor`, `payer`, `payee`, and `beneficiary` should be explicit roles. They must not be inferred from one generic party label. In particular:

- `obligor`/`debtor` describes who is expected to pay, return, or perform;
- `creditor`/`beneficiary` describes who is entitled to receive, obtain, or accept fulfilment;
- `payer` and `payee` describe the parties on a particular payment movement;
- `guarantor` describes a party that accepts responsibility if another payment route fails;
- `witness`, `representative`, `contact`, and `personal assistant` describe supporting or operational roles, not necessarily economic ownership.

The role should be attached at the narrowest level where it is true. A party may be a general contact for a record, a beneficiary for one obligation, and a payer for one movement.

### Payment instructions need a beneficiary/account model

The Open Banking Standards payment-initiation guidance treats beneficiary information as a structured set of details. Depending on country and currency, it may include account-holder name, account number, local routing code, IBAN, SWIFT/BIC, bank name, and bank address. [Open Banking Standards, Payment Initiation Services parameters and considerations](https://standards.openbanking.org.uk/customer-experience-guidelines/appendices/pis-parameters-and-considerations/v3-1-1/)

The same guidance describes saving account details for future payments or refunds only where the customer has requested that service and given consent. [Open Banking Standards, account selection at the ASPSP](https://standards.openbanking.org.uk/customer-experience-guidelines/payment-initiation-services/single-domestic-payments-acc-selection-aspsp/v3-1-2/)

For the debt app, a payment destination should consequently be:

- owned by or associated with a party;
- selectable for a particular obligation or payment;
- currency- and country-aware;
- versioned, with validity and verification state;
- masked in ordinary displays;
- encrypted or tokenised at rest where it contains account identifiers; and
- separate from the act of initiating or confirming a payment.

The last two are recommended product security controls inferred from the sensitivity and reuse of the data; they should be validated against the payment provider and applicable financial/security requirements before production.

### Auditability should cover relationships, not only balances

NIST SP 800-53 Revision 5.1 provides an authoritative control catalogue covering audit events, audit-record content, timestamps, audit review, and protection of audit information. It also includes an enhancement to limit personally identifiable information in audit records. [NIST SP 800-53 Revision 5.1](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final)

The debt app should apply that principle to party changes as well as ledger changes. It must be possible to answer: who added this party, who changed the role, which payment destination was selected at the time, who verified it, when consent was recorded or revoked, and which evidence supported the assertion. A current party profile alone cannot answer those historical questions.

## Recommended domain model

### 1. `Party`: the reusable identity boundary

Create a first-class `Party` concept, initially scoped to the profile/workspace to prevent accidental cross-profile disclosure. It should have:

- stable opaque identifier;
- `kind`: `individual`, `organization`, `group`, `estate_or_trust`, or `unidentified`;
- preferred/display name and optional legal name;
- aliases or former names where useful for matching, without treating a match as proof of identity;
- optional identifiers with type, issuer, verification state, and restricted visibility;
- active/archived state rather than destructive deletion by default; and
- provenance: how the party was entered, by whom, and when.

An `unidentified` party is important. A user may know that money, an item, or an action is outstanding but not yet have enough information to establish the other party. The application should record that uncertainty honestly and make enrichment possible later. It should not require the user to invent an email address, phone number, or legal identity.

Do not automatically merge parties merely because names look similar. Offer a deliberate merge or link workflow with an audit event and a way to undo the presentation-level merge while retaining the source history.

### 2. `RecordParty`: many parties with explicit record roles

Use the role-bearing `record_parties` relationship for record-level participation:

| Field | Purpose |
| --- | --- |
| `record_id` / `party_id` | Link the arrangement to a real party |
| `role` | Creditor, debtor, beneficiary, guarantor, witness, representative, contact, custodian, and so on |
| `is_primary` | Identify the main party for compact summaries without making it the only party |
| `responsibility_scope` | Explain whether the role covers the whole record or only selected obligations |
| `valid_from` / `valid_to` | Preserve changes in representatives, guarantors, and contacts over time |
| `status` | Proposed, active, ended, disputed, or unverified |
| `notes` / `source` | Explain how the relationship was established |

The role catalogue should be controlled and extensible. The UI can offer friendly labels, but the stored role must be unambiguous and API-safe.

### 3. `ObligationParty`: attach responsibility where it actually applies

A mixed record can contain a money obligation owed to a bank, an asset obligation involving a custodian, and an action obligation witnessed by another person. Record-level parties alone are not enough.

Add an obligation-party relationship for roles that apply to one obligation. It should support:

- `obligor` and `beneficiary` as the primary economic/fulfilment roles;
- optional guarantor, custodian, recipient, witness, or representative roles;
- a party being involved without being financially liable;
- an explicit share basis for genuinely joint obligations: `joint`, `several`, percentage, fixed amount/quantity, or unspecified; and
- a required explanation when multiple parties would otherwise create an ambiguous liability split.

The obligation’s authoritative balance should remain one native exposure unless the user explicitly records a party allocation. The app must not add a balance twice merely because two people are linked to the same obligation. Profile totals should be grouped by currency and obligation, while per-party views should state whether they are showing total exposure, an attributed share, or merely participation.

At the movement level, allow `payer` and `payee` references when they differ from the obligation’s parties. A payment made by a spouse, assistant, employer, or parent should not silently rewrite who was originally obligated.

### 4. Contacts, organisations, and assistants

Use repeatable contact records rather than one email and one phone on the party:

- `party_contact_methods`: email, phone, messaging handle, website, or other method;
- purpose/label: personal, work, payment, emergency, legal, billing, or preferred;
- verification state and verified-at timestamp;
- preferred flag and valid period; and
- visibility scope, such as private, shared with profile collaborators, or safe to include in an outgoing message.

Use repeatable addresses with type and validity rather than one unstructured JSON field when the UI needs to search, verify, or select an address.

Represent a team or organisation as a party. Represent the people inside it as separate parties joined through a party relationship/membership model with title, role, organisation unit, delegation scope, and validity period. A personal assistant should normally be a related person with a `contact` or `representative` role; the assistant should not automatically become the beneficiary or debtor. An organisation’s public contact point and the named human responsible for a specific arrangement should be selectable separately, consistent with the distinction between general organisation contacts and purpose-specific contacts in the FHIR organisation model. [FHIR Organization](https://hl7.org/fhir/organization.html)

### 5. Payment destinations and instructions

Split payment data into two concepts:

- `PartyPaymentDestination`: a reusable destination owned by or provided for a party, such as a bank account, DuitNow identifier, e-wallet handle, cheque details, or cash instructions;
- `ObligationPaymentInstruction`: the version selected for one obligation or one payment workflow, retaining the payee/beneficiary role, currency, reference text, provider, and a snapshot of what the user was shown.

Store provider tokens rather than raw account data where a provider supports a secure vault. Otherwise encrypt sensitive identifiers, expose only masked values, restrict access by role, and log reads as well as writes. Do not put account numbers into ordinary notifications, audit messages, URLs, or broad collaborator payloads.

Payment instructions should include the account-holder name, payment method, supported currency, country-specific routing fields, reference instructions, verification status, and effective/superseded timestamps. A destination is not proof that a payment happened; confirmed financial movements remain the authoritative payment evidence.

## Privacy, consent, retention, and deletion

### Malaysia baseline

If the product operates in commercial transactions in Malaysia, the Personal Data Protection Act 2010 (Act 709) is the relevant starting point; the official Commissioner describes the Act as regulating processing of personal data in commercial transactions and protecting data subjects. [JPDP, Personal Data Protection Act 2010](https://www.pdp.gov.my/ppdpv1/en/akta/pdp-act-2010-en/)

The Malaysian Commissioner’s official material describes seven principles: general, notice and choice, disclosure, security, retention, data integrity, and access. [JPDP, Principles of Personal Data Protection](https://www.pdp.gov.my/ppdpv1/en/principles-of-personal-data-protection/) The official standard says reasonable steps should protect data from loss, misuse, unauthorised access, disclosure, alteration, or destruction; personal data should be permanently deleted when it is no longer required; and it should be accurate, complete, not misleading, and up to date. [JPDP, Personal Data Protection Standard 2015](https://www.pdp.gov.my/ppdpv1/en/personal-data-protection-standard-2015/)

That matters here because a user may enter another living person’s name, phone number, address, email, identity evidence, bank details, or relationship information even though that person has no account. The product should treat party data as personal data from the moment it is collected, not as harmless notes.

### Consent and notice must be separate product flows

The JPDP’s privacy-notice guidance says a notice should explain the types and purposes of processing and available choices, and specifically warns that a privacy notice should not be used to obtain a blanket consent; consent should be managed and recorded properly. [JPDP, Guide to Prepare PDP Notice](https://www.pdp.gov.my/ppdpv1/wp-content/uploads/2024/07/Panduan-Penyediaan-Notis-PDP-2022-compressed.pdf)

Recommended product behaviour:

- show a concise notice before collecting optional contact, identity, or payment details;
- record consent or another approved processing basis separately by purpose and scope;
- distinguish private note-taking from sharing with collaborators, sending reminders, using a provider, or initiating a payment;
- do not imply that entering a party creates an account for that person or notifies them;
- provide a way to correct inaccurate party details;
- make an invitation or disclosure explicit, especially where a collaborator or provider will receive party information; and
- keep notification content minimal by default.

The official JPDP data-subject-rights material also describes rights and expectations around notice, access, correction, purpose limitation, and disclosure. [JPDP, Data Subject Rights](https://www.pdp.gov.my/ppdpv1/en/data-subject-rights/)

### Data minimisation and purpose limitation

Collect the smallest useful party profile at each stage:

- record creation: display name/label, party kind, and the role needed for the obligation;
- contacting: add a method only when the user actually needs it;
- payment: add one destination only when the user needs payment guidance or execution;
- verification: ask for stronger identity/evidence only when the user has a concrete reason;
- sharing: expose only the role, contact, or payment detail required by the recipient’s permission.

Do not use phone numbers, addresses, bank details, identity documents, or relationship data for unrelated analytics or marketing without a separately justified basis. The JPDP’s 2025 privacy-notice quick guide also emphasises explaining what is collected, why it is needed, and how choices can be exercised. [JPDP, A Quick Guide to Privacy Notice](https://www.pdp.gov.my/ppdpv1/wp-content/uploads/2025/01/A-Quick-Guide-to-PRIVACY-NOTICE.pdf)

### Retention and deletion design

Do not promise a single “delete everything immediately” rule. Define retention by data class and purpose, for example:

| Data class | Recommended policy direction |
| --- | --- |
| Unused contact method or payment destination | Delete or permanently destroy when no longer needed; retain only a minimal audit reference if necessary |
| Active party profile | Retain while an active record, sharing relationship, reminder, or payment workflow needs it |
| Settled record and evidence | Retain for a documented dispute/accounting/legal purpose, then delete or anonymise according to the published policy |
| Ledger, audit, consent, and payment execution history | Preserve the minimum integrity evidence needed to explain past actions; restrict access and avoid copying secrets into it |
| Backups and provider copies | Define expiry, deletion propagation, and legal-hold exceptions explicitly |

When a party asks for correction or deletion, the application must distinguish personal contact data that can be removed from the minimum facts needed to preserve an auditable financial history. A deleted party may become a redacted historical participant rather than disappearing from the ledger. This is a product design recommendation; the final policy must be checked against applicable Malaysian law, contractual duties, limitation periods, tax/accounting requirements, and any other jurisdictions served.

The Malaysian Commissioner has also published a Data Protection By Design guideline and a DPIA guideline. These are good reasons to run a documented privacy impact assessment before adding party imports, OCR, bank connections, payment execution, broad sharing, or emergency/heir access. [JPDP, Data Protection By Design Guideline](https://www.pdp.gov.my/ppdpv1/en/akta/data-protection-by-design-guideline-dpbd/), [JPDP, Data Protection Impact Assessment Guideline](https://www.pdp.gov.my/ppdpv1/en/akta/data-protection-impact-assessment-guideline-dpia/)

If the product later serves people in the European Economic Area, assess the GDPR separately. Its Article 5 principles include lawfulness/transparency, purpose limitation, data minimisation, accuracy, storage limitation, and integrity/confidentiality; Articles 7 and 17 address consent conditions and erasure. [EU GDPR, Regulation (EU) 2016/679](https://eur-lex.europa.eu/eli/reg/2016/679/oj) This is a cross-jurisdictional benchmark, not a conclusion that the GDPR applies to every current deployment.

## Recommended product plan

### Phase 1 — Correct the core language and relationships

- Use `Party` consistently in the product vocabulary, with role-specific labels in the record screen.
- Make the record screen show a party list with roles, not one undifferentiated name.
- Add record-party and obligation-party relationships with `is_primary`, role, status, validity, and notes.
- Require a clear primary obligor/beneficiary interpretation for money calculations, while allowing additional parties without inventing a split.
- Support individual, organisation, group, estate/trust, and unidentified party kinds.

### Phase 2 — Make party details useful but optional

- Add party detail pages with repeatable contact methods, addresses, aliases, and verification state.
- Add organisation membership/representative relationships for teams, departments, personal assistants, and agents.
- Add party-level evidence links for identity or authority evidence, without duplicating evidence that belongs to an obligation or movement.
- Add deliberate duplicate detection and merge review; never silently merge on display name.

### Phase 3 — Make payment guidance safe and reusable

- Introduce party-owned payment destinations and link selected, versioned instructions to obligations.
- Support country/currency-specific fields and payment references without pretending all destinations share one schema.
- Mask and protect account identifiers; use provider tokens where possible.
- Keep payment instruction creation separate from payment authorisation and execution.
- Capture who verified a destination, when, by what evidence, and when it was superseded.

### Phase 4 — Make privacy visible and enforceable

- Add a short privacy notice at the point of party-data collection and maintain versioned notice records.
- Add purpose-scoped consent/authorisation records for sharing, reminders, provider access, saved payment destinations, and emergency access.
- Add party-data access, correction, export, redaction, and deletion workflows.
- Publish retention periods by data class and explain legal-hold, backup, provider, and audit exceptions.
- Run a DPIA before importing contacts, OCR-ing identity/payment evidence, connecting banks, enabling automatic payments, or exposing data to heirs/emergency contacts.

### Phase 5 — Align every surface

- Return structured `party` and `roles` objects in the API; clients should not parse a single display string.
- Ensure dashboards, plans, reminders, communications, exports, audit logs, notifications, and evidence views all apply the same party and role permissions.
- Make movement-level payer/payee references available to API consumers.
- Keep secrets and unnecessary party details out of default notification payloads and URLs.
- Add scenario tests for one person, organisation plus representative, multiple joint parties, guarantor, witness, assistant, payer-on-behalf-of, unidentified party later enriched, party correction, party merge, consent revocation, and deletion/redaction.

## Final recommendation

Proceed with the first-class party model already established here. Do not collapse it back into a single name field or a single direct record reference; that would leave the application unable to represent the real relationship graph behind a complicated debt.

The most durable boundary is:

**Party** — who or what exists;  
**Relationship/role** — how that party participates in a record, obligation, or movement;  
**Contact/payment destination** — how to reach or pay them;  
**Evidence/consent/audit** — why the application believes it, who may use it, and what changed;  
**Record/obligation/ledger** — what is owed and how the outstanding subject changes.

This gives the product the flexibility the user is asking for without turning every debt record into an uncontrolled CRM. It also keeps the crucial truth that a name, a contact method, a bank account, a beneficiary role, and proof of a debt are different facts with different permissions and lifetimes.

## Sources

All external sources used in this note are first-party or primary sources:

1. [IETF RFC 6350 — vCard Format Specification](https://www.rfc-editor.org/rfc/rfc6350/)
2. [HL7 FHIR R5 — RelatedPerson](https://hl7.org/fhir/relatedperson-definitions.html)
3. [HL7 FHIR R5 — Organization](https://hl7.org/fhir/organization.html)
4. [HL7 FHIR R5 — Account definitions](https://www.hl7.org/fhir/R5/account-definitions.html)
5. [ISO 20022 — Payments maintenance material](https://www.iso20022.org/message/mdr/12666/download/111)
6. [Open Banking Standards — Payment Initiation Services parameters and considerations](https://standards.openbanking.org.uk/customer-experience-guidelines/appendices/pis-parameters-and-considerations/v3-1-1/)
7. [Open Banking Standards — Account selection at the ASPSP](https://standards.openbanking.org.uk/customer-experience-guidelines/payment-initiation-services/single-domestic-payments-acc-selection-aspsp/v3-1-2/)
8. [NIST — SP 800-53 Revision 5.1](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final)
9. [Malaysia Personal Data Protection Commissioner — Personal Data Protection Act 2010](https://www.pdp.gov.my/ppdpv1/en/akta/pdp-act-2010-en/)
10. [Malaysia Personal Data Protection Commissioner — Principles of Personal Data Protection](https://www.pdp.gov.my/ppdpv1/en/principles-of-personal-data-protection/)
11. [Malaysia Personal Data Protection Commissioner — Personal Data Protection Standard 2015](https://www.pdp.gov.my/ppdpv1/en/personal-data-protection-standard-2015/)
12. [Malaysia Personal Data Protection Commissioner — Guide to Prepare PDP Notice](https://www.pdp.gov.my/ppdpv1/wp-content/uploads/2024/07/Panduan-Penyediaan-Notis-PDP-2022-compressed.pdf)
13. [Malaysia Personal Data Protection Commissioner — Data Subject Rights](https://www.pdp.gov.my/ppdpv1/en/data-subject-rights/)
14. [Malaysia Personal Data Protection Commissioner — A Quick Guide to Privacy Notice](https://www.pdp.gov.my/ppdpv1/wp-content/uploads/2025/01/A-Quick-Guide-to-PRIVACY-NOTICE.pdf)
15. [Malaysia Personal Data Protection Commissioner — Data Protection By Design Guideline](https://www.pdp.gov.my/ppdpv1/en/akta/data-protection-by-design-guideline-dpbd/)
16. [Malaysia Personal Data Protection Commissioner — Data Protection Impact Assessment Guideline](https://www.pdp.gov.my/ppdpv1/en/akta/data-protection-impact-assessment-guideline-dpia/)
17. [European Union — Regulation (EU) 2016/679 (GDPR)](https://eur-lex.europa.eu/eli/reg/2016/679/oj)
