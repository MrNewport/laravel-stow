# Stow feature index

| Public API → return | Rules/errors | Usage |
| --- | --- | --- |
| `new Basket(array|string $attributes = [])` | Default instance basket; UUID slug on creation. | [Quickstart](HANDBOOK.md#eloquent-quickstart) |
| `Basket::add(Stowable, int $qty = 1, array $options = []): BasketItem` | Saved subject/positive qty; allowlist → UnstowableObjectException; invalid qty/key → InvalidArgumentException. | [Quickstart](HANDBOOK.md#eloquent-quickstart) |
| `change(int|BasketItem, int $qty = 1, array $options = []): bool` | Absolute qty/replaced options; scoped missing line → ModelNotFoundException. | [Quickstart](HANDBOOK.md#eloquent-quickstart) |
| `remove(BasketItem|int): bool` | Scoped soft deletion; missing → ModelNotFoundException. | [Quickstart](HANDBOOK.md#eloquent-quickstart) |
| `merge(Basket): void`, `clone($toInstance = null): Basket` | Copy/add lines; source retained; host transaction/allowlist policy. | [Copy](HANDBOOK.md#instances-relationships-copy-and-cleanup) |
| `verifyStowability(string): bool` | Instance class allowlist; unknown instance unrestricted. | [Instances](HANDBOOK.md#instances-relationships-copy-and-cleanup) |
| `Basket::basketItems(): HasMany`, `items(): HasMany` | Line records, default soft-delete scope. | [Relations](HANDBOOK.md#instances-relationships-copy-and-cleanup) |
| `BasketItem::basket(): BelongsTo`, `stowable(): MorphTo` | Options array cast; quantity and morph keys. | [Relations](HANDBOOK.md#instances-relationships-copy-and-cleanup) |
| `Stowable::basketItems(): MorphMany`, `getKey()`; StowMethods trait | Inverse membership contract on host model. | [Quickstart](HANDBOOK.md#eloquent-quickstart) |
| BasketCreated/Deleting/DeletedEvent; BasketItemCreated/Updated/DeletedEvent | Public basket or basketItem payload; ordinary model timing. BasketDeletingListener deletes lines. | [Events](HANDBOOK.md#ownership-concurrency-and-events) |
| `createBrowserBasket({storageKey, allowedIds, maxItems=100, maxQuantity=99, storage, migrate})` → basket | Invalid config → TypeError; auto-loads. | [Browser](HANDBOOK.md#browser-basket-quickstart) |
| Browser `items()`, `load()`, `add(id, amount=1)`, `set(id, value)`, `remove(id)`, `clear()` → detached item list | Numeric floors/caps; unknown/invalid ignored; set0 removes; persisted version1 envelope. | [Browser](HANDBOOK.md#browser-basket-quickstart), [storage](HANDBOOK.md#browser-storage-migration-and-failure-recovery) |
| Browser `subscribe(listener)` → unsubscribe function; `persistent` → bool | Not called on subscribe; changes notify with items and persistence; invalid listener → TypeError. | [Browser](HANDBOOK.md#browser-basket-quickstart) |
| Storage/migrate adapter | Synchronous getItem/setItem; one-time legacy conversion, failure-preserving memory, explicit reload. | [Recovery](HANDBOOK.md#browser-storage-migration-and-failure-recovery) |
| StowProvider, stow-config, morph_key_type, instances | Discovery/migrations/events; key-type config never converts existing data. | [Install](HANDBOOK.md#install-in-laravel-12-or-13) |
| Ownership/locked column/concurrency/soft-delete retention | Host binding and common lock policy required; no checkout/pricing/server sync. | [Operations](HANDBOOK.md#ownership-concurrency-and-events), [recipes](RECIPES.md) |

Executable examples: [basket tests](../tests/Feature/BasketTest.php), [regression tests](../tests/Feature/BasketRegressionTest.php), [browser failures/migration](../tests/Browser/browser-basket.test.mjs). They exercise actual package calls independently of a UI.
