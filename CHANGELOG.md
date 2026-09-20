# Changelog

## 2.1.0 — 2026-09-19

- Optional `stow.morph_key_type` supports integer, UUID and ULID references on fresh migrations without converting existing tables.
- `Stowable::getKey()` now accepts Eloquent’s inherited method without a fatal return-type conflict.
- Optional browser basket adapter provides reusable local membership, quantity limits and recoverable persistence.
- Corrected installation/API examples and documented ownership/concurrency responsibilities.

## 2.0.0 — 2026-09-12

Laravel 12/13 runtime dependencies and current test harness. Fixed Eloquent construction/hydration and model events, inverse basket relations, morph aliases, variant matching, merge and clone behavior. Basket mutations scope item IDs to the current basket; quantities must be positive. Configuration now uses stow.instances with a legacy basket.instances fallback.
