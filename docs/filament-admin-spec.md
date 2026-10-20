# Filament 5 Staff Backend — Settled Spec (orchestrator + Luna converged)

Decision source: Luna unanchored blueprint (/tmp/filament-first-pass-out.txt) +
convergence Decision A (/tmp/filament-converge-out.txt). This file is binding.

## Shape
- ONE staff/operator panel: Filament, panel ID `admin`, path `/admin`.
  NOT FinancialProfile-tenanted. The existing Livewire app remains the
  customer workspace; do NOT build a customer /manage panel.
- Cross-profile surface is deliberately small: FinancialProfile directory
  (safe summary fields only), SupportCases, SupportAccessGrants,
  SupportAuditEntries, Staff access assignments.
- Detailed customer data (records, obligations, movements, parties, members,
  invites, imports, attachments) is visible ONLY for one profile bound to an
  active, approved, unexpired SupportAccessGrant on an open SupportCase.
  No global financial search/totals/exports. No secrets/hashes/payment details
  unmasked ever (masked presenter only; reveal stays customer-side action).

## Access (fail closed, all required)
- Panel ID exactly `admin`; verified email (add MustVerifyEmail to User);
  one of the seeded staff roles; fresh MFA challenge passed this session.
  Customer Owner/Editor/Viewer membership grants NOTHING here.
- Roles/permissions (web guard, code-managed seeds, no wildcards, disable any
  plugin super-admin bypass):
  support_agent: profile.lookup, profile.viewSummary, supportCase.viewAny/view/create/update,
  supportAccess.request, profileRecord.view, profileMember.view, profileInvite.revoke
  support_lead: agent + supportAccess.approve, supportAccess.revoke, supportAudit.viewAny
  support_auditor: supportAudit.viewAny
  security_admin: staffAccess.viewAny, staffAccess.assign
- Packages (owner override, supersedes the earlier tagged-version decision):
  use the AIArmada `dev-main` line, not release tags — direct requirements
  `aiarmada/commerce-support: dev-main`, `aiarmada/authz: dev-main`, and
  `aiarmada/filament-authz: dev-main`; keep `filament/filament` on stable
  `^5.8.1`. The dev-main AIArmada set aligns on Spatie Permission `^8.3`.
  Orchestrator dry-run on 2026-10-19 resolved commerce-support `768dc55`,
  authz `ccb31cc`, and filament-authz `da39860`; the implementation must quote
  the exact commits actually locked at install time. Verify every
  Filament/Authz API against INSTALLED vendor source, not memory, and do not
  let Authz impersonation or wildcard/super-admin behavior bypass support
  cases, access grants, domain policies, or audit logging.
- MFA: fresh challenge required. Prefer bridging existing Fortify TOTP if the
  installed Filament 5 custom-provider API cleanly supports it; otherwise
  Filament native TOTP MFA restricted to staff panel. Either way: enrollment
  required for staff roles, confirmed_at alone never suffices, recovery codes
  handled per chosen provider. State chosen path in handover.

## Support workflow domain (new, genuinely new tables = new migrations)
- SupportCase: profile_id, opened_by, subject, reason, status open/closed,
  closed_at/by. SupportAccessGrant: case_id, requested_by, approved_by
  (MUST differ from requester), status pending/approved/revoked/expired,
  starts_at, expires_at (max +1 hour), revoked_at/by.
  SupportAuditEntry: immutable (no updated_at, no edit/delete anywhere):
  actor, case_id nullable, profile_id nullable, action, subject morph nullable,
  metadata jsonb (redacted summaries only), created_at.
- Domain actions (not Filament-inline writes): OpenSupportCase, CloseSupportCase,
  RequestSupportAccess, ApproveSupportAccess, RevokeSupportAccess,
  staff-aware RevokeProfileInvite (requires active grant; invite gets
  revoked_at column EDITED into existing invite migration; isUsable rejects revoked).
  Every staff view-of-profile-data and mutation writes SupportAuditEntry
  (list/detail views of case-bound profile data log at minimum case open,
  grant approve/revoke, profile-data access events per resource view, invite revoke).
- NO ledger/financial mutations from /admin in this ship. Read-only case-scoped
  operational views + invite revoke only.

## Panel inventory
- Dashboard widgets: open cases, pending grant requests, grants expiring <15m,
  recent audit entries (redacted), counts only (no money totals across profiles).
- Resources: SupportCases (CRUD constrained to actions), SupportAccessGrants
  (request/approve/revoke actions), SupportAuditEntries (read-only),
  FinancialProfiles directory (read-only summary: name, owner name/email,
  base currency, timezone, members count, records count, created_at; NO balances),
  Staff users (read-only list + assign/revoke seeded roles action, security_admin only;
  never show password/2FA/token fields), filament-authz role/permission resources
  restricted to security_admin (or hidden if plugin cannot be scoped safely — state which).
- Case workspace page: for an open case with ACTIVE grant, tabs/sections showing
  that ONE profile's records (with obligations+movements read-only), parties
  (masked destinations, contacts), members, invites (+revoke action), imports list.
  Without active grant: case metadata only, zero customer data. Guessed URLs 404/403.
- Global search: cases + profile directory by name/owner email only; never record
  titles cross-profile outside a grant.

## Seeding / provisioning
- Roles+permissions seeder idempotent, run from DatabaseSeeder.
  NO seeded staff account in production paths. Local/testing only: give
  test@example.com support_lead (+security_admin for Chrome test of role assign)
  so dev can exercise flows; gate by app()->environment('local','testing').
- Make pre-existing unconditional demo fixture safe: it already only seeds
  test@example.com/password — keep, but staff assignment only in local/testing.

## Tests required
Panel access matrix (guest, unverified, customer-only, each staff role, wrong panel),
fresh-MFA gate, case/grant lifecycle (self-approve denied, expiry, revoke, closed case denies data),
profile data invisible without grant (list/detail/direct URL/search/widgets), invite revoke under
grant + revoked invite cannot be accepted, audit entries written + immutable (no update/delete routes),
role assignment only security_admin + only seeded roles, secrets absent from rendered pages
(token_hash, terms hashes, details_encrypted raw, 2FA fields). Existing 227 suite stays green.

## Chrome gate (after build)
Luna live Chrome MCP: staff login+MFA, open case, request+approve grant as two
different staff users if feasible (else document), view case profile data, revoke grant ->
data disappears, audit visible to lead/auditor, customer test@example.com without staff role denied /admin.
