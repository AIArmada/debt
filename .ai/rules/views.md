---
paths:
  - 'app/**,routes/**,resources/views/**,tests/**'
---

# Views

## Do not add backward-compatibility shims
Implement the current promise-tracker contracts directly. Do not add legacy route aliases, old namespace wrappers, dual legacy behavior, or compatibility adapters; remove obsolete surfaces instead.
