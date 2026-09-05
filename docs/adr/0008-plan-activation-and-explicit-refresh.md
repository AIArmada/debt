# Plan activation and explicit refresh

## Context

People may need to compare more than one repayment approach for the same budget period. A new movement can also make a previously saved plan inaccurate while another plan is active. Silently rewriting or activating an outdated plan would hide a planning decision and could make its proposed payments inconsistent with the current ledger.

## Decision

Repayment plans are versioned proposals with an explicit lifecycle. Only one plan for a profile, budget period, and planning currency may be active. Generating another plan pauses the current active plan. A confirmed movement that affects a plan's allocation currency marks that plan for review, whether it is active or paused. A paused plan without review changes may be activated directly. A plan needing review must show a current-balance preview and, after explicit confirmation, create a new active plan version; the stale source becomes superseded and any active alternative is paused. Confirmed payments already made in the budget period are carried into the new version's progress without changing transaction links.

## Consequences

- Users can keep and switch between multiple planning approaches without losing history.
- The ledger remains the source of truth; no movement is silently changed by planning actions.
- Stale plans are visible and actionable, but reactivation requires an intentional refresh.
- Plan history contains additional versions, and the UI must make the active plan and review state obvious.
