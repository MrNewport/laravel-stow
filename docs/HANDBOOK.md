# Laravel Stow handbook

Stow stores membership, integer quantities and options in Eloquent baskets or a small browser adapter. It does not calculate prices or create checkouts. The Eloquent and browser APIs are separate backends; there is no implicit synchronization between them.

## Install in Laravel 12 or 13

```sh
composer create-project laravel/laravel collections-app '^12.0'
cd collections-app
composer require mrnewport/laravel-stow:^2.1
php artisan vendor:publish --tag=stow-config
```

Use `'^13.0'` for Laravel 13 and its supported PHP runtime. If Composer's registry has not indexed the tag, run `composer config repositories.laravel-stow vcs https://github.com/MrNewport/laravel-stow` and repeat the requirement. Laravel discovers StowProvider, which registers configuration, migrations and basket-deletion listeners. If discovery is disabled register `MrNewport\LaravelStow\Providers\StowProvider` manually.

Before the first `php artisan migrate`, set `stow.morph_key_type` to `int` (default), `uuid` or `ulid` to match saved Stowable subject keys. Basket/line IDs remain bigints. Changing config does not alter populated columns. Existing installations need a reviewed host migration/mapping; never roll back package tables to change key type. The browser adapter needs no PHP migrations and can also be used in a static frontend build.

## Eloquent quickstart

Implement Stowable and reuse StowMethods on a saved model:

```php
use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelStow\Interfaces\Stowable;
use MrNewport\LaravelStow\Traits\StowMethods;

class Product extends Model implements Stowable
{
    use StowMethods;
}
```

Eloquent already provides getKey(); StowMethods provides the required inverse basketItems MorphMany. A saved Product with a compatible key can now be stored:

```php
use MrNewport\LaravelStow\Models\Basket;

$basket = new Basket('cart');
$line = $basket->add($product, 2, ['finish' => 'matte']);
$basket->add($product, 1, ['finish' => 'matte']); // matching line now quantity 3
$basket->change($line, 4, ['finish' => 'matte']); // absolute quantity and options
$basket->remove($line); // soft-delete this basket's line
```

`new Basket` defaults instance to `basket`; it also accepts an Eloquent attributes array. Instance is mass assignable; options is array-cast, but the migration does not provide a basket options column. On first creation a UUID slug is generated. add() auto-persists an unsaved basket, requires a positive integer quantity and a nonnull saved subject key, verifies its allowed class and returns a fresh BasketItem. Exact subject morph identity and PHP loose-array equality of options identify an existing line. Distinct options produce distinct lines. Normalize allowed option keys/values in the host so equivalent domain options have a consistent representation.

change() accepts a line instance or integer line ID, scopes the lookup to this basket, sets absolute quantity and **replaces** options. Its default options are empty; pass existing options explicitly when retaining them. Nonpositive quantities fail with InvalidArgumentException; remove instead of setting zero. remove() similarly scopes to this basket and returns deletion success. A foreign/missing line produces ModelNotFoundException. These methods do not enforce a browser maxQuantity or a maximum number of Eloquent lines.

## Instances, relationships, copy and cleanup

`stow.instances` optionally maps instance names to permitted PHP model classes:

```php
return [
    'morph_key_type' => 'int',
    'instances' => [
        'cart' => [App\Models\Product::class],
        'favorites' => [App\Models\Product::class],
    ],
];
```

An absent instance allowlist is unrestricted among Stowable classes. A disallowed class raises UnstowableObjectException. `verifyStowability(string $className): bool` exposes that check; the legacy `basket.instances` fallback is consulted when the Stow key is absent. This is a class contract, not user ownership or tenant policy.

Basket `basketItems()` and `items()` both return HasMany BasketItem. Eager-load `basketItems.stowable` when rendering product details. BasketItem has fillable stowable_type/id/options/quantity, array-cast options, `basket(): BelongsTo`, `stowable(): MorphTo`, timestamps and SoftDeletes. Product `basketItems()` is the inverse MorphMany; it does not authorize browsing other users' baskets.

`$destination->merge($source): void` calls add for each source line and retains the source. Same subject/options quantities accumulate; self-merge does nothing. It uses the source's relationship data and current allowlist. Refresh/unset an already loaded source items relation before merging if another action changed its rows. Missing/deleted subjects require a host recovery policy.

`$copy = $basket->clone($toInstance = null): Basket` creates a new UUID-slug basket and copies line data (defaulting to the same instance). Replicated lines have new IDs. clone copies rather than repricing, authorizing or moving source data. It does not run add's destination allowlist check, so verify a requested destination instance in the host. Neither merge nor clone creates an order snapshot, clears the source, or wraps all work in its own application-level transaction.

Baskets and lines use soft deletion. BasketDeletingEvent invokes the supplied listener, which deletes its current lines individually. Restoring a basket does not automatically restore its lines; decide whether restoration is permitted. Bulk Eloquent query delete/update bypasses per-model events, so use model operations when cleanup/events are needed. Force deletion and retention requirements belong to the host; do not assume all tables have cascade foreign-key guarantees.

## Ownership, concurrency and events

A basket has no user/tenant ownership relation and its `locked` column is not enforced by mutation methods. Add a host binding (for example user/workspace + basket ID), resolve through that binding before every operation, and enforce any locked/revision policy yourself. A slug is a reference, not proof of permission.

For database mutations, lock the authorized basket row on its connection before a read/modify/write operation. This serializes cooperating writers, because Stow add performs a read followed by increment/insert rather than a unique atomic upsert. merge/clone and host ownership creation should participate in the same outer transaction. A new shared basket needs host uniqueness/idempotency before its row exists. Every writer must use the same lock policy; a transaction alone does not prevent lost updates. Validate quantities and options and recheck inventory/price at the domain boundary.

Model events: BasketCreatedEvent, BasketDeletingEvent, BasketDeletedEvent carry `basket`; BasketItemCreatedEvent, BasketItemUpdatedEvent, BasketItemDeletedEvent carry `basketItem`. They are ordinary model events, not an after-commit durable outbox. Rollback does not retract external work already done by a synchronous listener. Use host after-commit workers or a transactional outbox for reliable external effects. Respect observer return values in your application error handling; do not assume every model veto is elevated into an exception by this API.

## Browser basket quickstart

Import the installed module through your application bundler; from resources/js/app.js:

```js
import { createBrowserBasket } from '../../vendor/mrnewport/laravel-stow/resources/js/browser-basket.mjs';

const basket = createBrowserBasket({
  storageKey: 'shop.saved.v1',
  allowedIds: ['lamp', 'chair'],
  maxItems: 24,
  maxQuantity: 9,
});
const unsubscribe = basket.subscribe((items, { persistent }) => {
  renderSavedItems(items); // application function: render escaped catalog content
  showPersistenceNotice(!persistent);
});
basket.add('lamp', 2);
basket.set('lamp', 3);
console.log(basket.items()); // [{ id: 'lamp', quantity: 3 }]
basket.remove('lamp');
basket.clear();
unsubscribe();
```

Construction automatically loads storage. Use items() for initial rendering because subscribe does not immediately call the listener. Every returned items array contains detached simple records. allowedIds must be an Array or Set of nonempty strings; storageKey nonblank; maxItems (default 100) and maxQuantity (default 99) positive safe integers; storage/migrate must be functions. Invalid configuration or subscribe listener throws TypeError.

`add(id, amount = 1)`, `set(id, value)`, remove and clear return current items. Unknown IDs and invalid amounts are ignored. Runtime amounts must be finite numbers; fractions floor to integers, values clamp to maxQuantity and below zero to zero. add zero is a no-op; set zero removes. Existing quantities saturate at the cap. New membership beyond maxItems is ignored. This contract is unsuitable for fractional weight/length drafts; keep those quantities in the host or use another explicit contract.

## Browser storage, migration and failure recovery

storage is a synchronous factory returning getItem/setItem. Default is `() => globalThis.localStorage`; inject a Map-backed adapter for page-only state:

```js
const memory = new Map();
const basket = createBrowserBasket({
  storageKey: 'planner.session', allowedIds: ['tomato', 'lettuce'],
  storage: () => ({ getItem: key => memory.get(key) ?? null,
    setItem: (key, value) => memory.set(key, value) }),
});
```

The storage envelope is `{version:1,items:[{id,quantity}]}`. Loaded quantities must already be positive safe integers. Normalization drops unknown/malformed rows, caps quantities, merges duplicate IDs, and limits unique membership. Valid load writes back normalized data. Denied access, corrupt JSON or unsupported envelopes do not throw into the UI; persistent becomes false. A failed write retains the newest in-memory state and marks it pending; load() retries that write before reading storage, so stale persisted content does not overwrite a newer unsaved mutation. A later successful write makes persistent true. The flag reports success through the chosen adapter; a Map adapter does not imply disk durability.

When the new key is absent, optional `migrate(storageAdapter)` may return legacy rows:

```js
migrate: store => {
  const ids = JSON.parse(store.getItem('old.saved.ids') ?? '[]');
  return Array.isArray(ids) ? ids.map(id => ({id, quantity: 1})) : [];
}
```

Migration is attempted at most once for the current basket instance; errors yield empty migration data. Loaded/migrated rows pass the same normalization. Clear persists an empty versioned envelope so a reload does not revive legacy entries. Do not delete legacy data before the new write succeeds; the package does not delete it for you. Use a versioned storage key and authorize any later server import independently. No cross-tab subscription/merge, server sync, conflict version or checkout API is supplied. If you listen for browser storage events, decide how host conflicts are resolved before calling load().

## Serious application recipes

[RECIPES.md](RECIPES.md) provides an owned saved-build basket, a memory-only produce selection with server handoff, and browser legacy migration. [FEATURES.md](FEATURES.md) lists every public API and event. Package tests are `composer test`; browser contract tests are `node --test tests/Browser/browser-basket.test.mjs` (see package scripts). Before upgrading test saved options, morph keys/maps, active ownership bindings, stale concurrent writers, deletion restoration and failed browser storage.

## Composition

Use Priceable for actual quantity/user/scope price resolution only when the domain needs it; keep immutable accepted prices/currency/tax/order snapshots in the application. NestedTerms can supply catalog taxonomy, but Stow does not inspect tags. Flow/Workflow can manage a real approval process after a basket becomes a domain proposal; a basket alone is not a workflow. No optional package is required for either Stow backend.
