# PromiseTracker

This application currently has one owning capability: the promise ledger, including
party records, profiles, evidence, reminders, imports, support cases and staff actions.
They share the same profile/record aggregate, so they stay one feature until a real
public boundary is worth introducing.

Entrypoints adapt transport; concrete Actions implement local use cases.
Add Api contracts/values only when another feature needs a public boundary.
Read feature.json for durable resource ownership before changing writes.
Keep Models internal. Named Actions/Persistence classes listed in feature.json may write owned state.
If overriding FeatureServiceProvider::boot(), call parent::boot().
Run both architecture:boundaries and architecture:types plus affected behavior tests.
