# Laravel Stow

Current release: **2.1.0**. Supports Laravel 12 (PHP 8.2+) and Laravel 13 (PHP 8.3+). Earlier Laravel applications should keep their previous major release.

Stow manages named collections of model references, quantities and variant options: carts, quotes, wishlists, favorites and saved configurations. Pricing, availability, authorization, orders and payment processing belong to the application or a complementary package such as Laravel Priceable.

## Install

```sh
composer require mrnewport/laravel-stow:^2.1
php artisan vendor:publish --tag=stow-config
# Configure the model key type BEFORE the initial migration.
php artisan migrate
```

Laravel discovers `MrNewport\LaravelStow\Providers\StowProvider` automatically. The package loads its migrations; do not copy them into the application. GitHub tags are published first. Until Packagist indexes a release, add `https://github.com/MrNewport/laravel-stow` as a Composer VCS repository.

## Model keys and instance restrictions

```php
// config/stow.php
return [
    'morph_key_type' => 'uuid', // int (default), uuid, or ulid
    'instances' => [
        'cart' => [App\Models\GuitarBuild::class],
        'saved-builds' => [App\Models\GuitarBuild::class],
    ],
];
```

Choose the key type used by the referenced models before creating `basket_items`. Basket and basket-item IDs remain integers. All models stored in this table must have compatible key types. A missing setting defaults to integer keys. Invalid values fail before the items table is created. Changing configuration after migration does **not** convert an existing column: plan and review an application data migration if existing records need a different key type.

Configured instances accept only the listed model classes. An instance absent from configuration accepts any `Stowable` model. The older `basket.instances` configuration remains a fallback.

## Eloquent usage

```php
use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelStow\Interfaces\Stowable;
use MrNewport\LaravelStow\Models\Basket;
use MrNewport\LaravelStow\Traits\StowMethods;

class Product extends Model implements Stowable
{
    use StowMethods;
}

$product = Product::findOrFail($productId);
$cart = Basket::create(['instance' => 'cart']);
$line = $cart->add($product, 2, ['finish' => 'natural']);
$cart->add($product, 1, ['finish' => 'natural']); // same line, quantity 3
$cart->add($product, 1, ['finish' => 'black']);   // distinct options, new line
$cart->change($line, 4, ['finish' => 'natural']);
$items = $cart->basketItems()->with('stowable')->get();
$cart->remove($line); // change() requires positive quantities
```

An item must already have a key. `StowMethods` supplies its inverse `MorphMany` relationship. Eloquent's inherited `getKey()` satisfies the interface; no wrapper method is needed. Laravel morph-map aliases are respected. `Basket::items()` is an alias for the basket's `HasMany` lines. Both `new Basket('wishlist')` and normal Eloquent attribute construction work; adding to an unsaved basket saves it.

```php
$copy = $cart->clone('quote'); // copies lines into a new basket
$wishlist->merge($cart);     // adds quantities; retains the source cart
$cart->delete();             // soft-deletes basket and its lines
```

`change()` and `remove()` scope line IDs to the current basket. Applications still own basket access control: resolve ownership from the authenticated user or a trusted anonymous session, never accept an arbitrary basket ID without authorization. Add an ownership column/relation or use an application basket subclass with explicit table/foreign-key names as appropriate.

Stow does not serialize concurrent requests itself. Wrap mutations in an application transaction and lock the owner/basket row; use session blocking where appropriate. Enforce application quantity and capacity limits inside that lock. A schema column named `locked` is present for application use; Stow does not enforce it automatically. Clone/merge also require application transaction boundaries when all-or-nothing behavior is needed.

## Events

Basket created, deleting and deleted events and item created, updated and deleted events expose their respective model. A `BasketUpdatedEvent` class exists but is not automatically dispatched by the current basket model. The bundled deleting listener removes basket lines. Use Laravel event listeners for application behavior; verify the relevant event is dispatched by the operation being observed. Application side effects should run after the surrounding transaction commits.

## Upgrades and verification

Version 2.1 adds configurable UUID/ULID morph columns for fresh installations, removes an incompatible return-type requirement from `Stowable::getKey()`, and includes the optional browser adapter documented below. Existing database schemas and valid basket method calls remain unchanged. Version 2.0 corrected Eloquent construction, inverse relations, morph aliases, option matching and basket-scoped mutations. From 1.x, update custom `basketItems()` implementations to return `MorphMany`.

```sh
composer test
node --test tests/Browser/*.test.mjs
```

CI exercises supported PHP/Laravel combinations, real integer/UUID/ULID Eloquent references, MySQL migrations, clean Laravel consumers and browser storage behavior. Package test dependencies stay in `require-dev`.

## Optional browser basket adapter

The PHP API above persists Eloquent baskets. The separate, dependency-free ES module at `resources/js/browser-basket.mjs` manages **client-only** bags, favorites, and wish lists. Import it through your application's bundler; it does not run automatically, synchronize with Eloquent, calculate prices, perform checkout, or make network requests.

```js
import { createBrowserBasket } from '../../vendor/mrnewport/laravel-stow/resources/js/browser-basket.mjs';

const bag = createBrowserBasket({
    storageKey: 'store.bag.v2',
    allowedIds: catalog.map((product) => product.slug),
    maxItems: 24,
    maxQuantity: 9,
    storage: () => window.localStorage,
    migrate(storage) {
        const legacy = JSON.parse(storage.getItem('store.bag.v1') || '[]');
        return Array.isArray(legacy)
            ? legacy.map((line) => ({ id: line.slug, quantity: line.quantity }))
            : [];
    },
});

bag.add('example-pipe', 2);
bag.set('example-pipe', 3);
const unsubscribe = bag.subscribe((items, { persistent }) => {
    // Feed fresh snapshots into your framework's state; show a memory-only notice
    // when persistent is false. This callback does not run immediately.
});
```

`allowedIds` is copied from an array or Set of catalog string identities. `maxItems` and `maxQuantity` must be positive safe integers and default to 100 and 99. For favorites use `maxQuantity: 1`. The factory immediately loads stored data and returns:

| Member | Behavior |
| --- | --- |
| `items()` | Fresh array of `{id, quantity}` copies |
| `add(id, quantity = 1)` | Combine matching identities, capped at the quantity limit |
| `set(id, quantity)` | Replace quantity; zero removes |
| `remove(id)` / `clear()` | Remove one line or all lines |
| `load()` | Reload storage, or first retry an outstanding failed write |
| `subscribe(listener)` | Receive independent snapshots and `{persistent}` when state or persistence changes; returns unsubscribe |
| `persistent` | Whether the last storage attempt succeeded |

Mutators and `load()` return snapshots. Unknown identities, nonnumeric/nonfinite mutation quantities, and new lines beyond capacity are ignored. Finite mutation quantities are floored and clamped from zero to the quantity limit; negative `set` removes and zero `add` does nothing. Hydration is stricter: persisted or migrated quantities must already be positive safe integers; strings and fractions are ignored. Duplicate identities combine, unknown identities disappear, and non-data fields such as prices are discarded.

### Storage adapters and migration

`storage` is an injectable function returning an object with synchronous `getItem(key)` and `setItem(key, string)` methods. The default resolves `globalThis.localStorage` lazily. `() => window.sessionStorage`, a custom synchronous adapter, or a simple Map-backed adapter also works:

```js
const entries = new Map();
const memoryStorage = {
    getItem: (key) => entries.get(key) ?? null,
    setItem: (key, value) => entries.set(key, value),
};
const favorites = createBrowserBasket({
    storageKey: 'store.favorites.v1',
    allowedIds: catalog.map((product) => product.slug),
    maxQuantity: 1,
    storage: () => memoryStorage,
});
```

For an injected memory adapter, `persistent` means adapter operations succeeded, not survival across a page reload. Asynchronous adapters are not supported. The module does not listen for cross-tab storage events; applications can call `load()` when appropriate. Concurrent tabs use last-write-wins storage and do not merge automatically.

Canonical storage is `{version: 1, items: [{id, quantity}]}`. Optional `migrate(adapter)` is called only when the canonical key is absent and returns a legacy array of lines; migration exceptions or invalid results become an empty canonical basket. A malformed or unsupported canonical envelope never triggers legacy migration: it leaves current memory intact and sets `persistent` false until a successful write/load.

`clear()` writes an empty canonical envelope rather than removing the key, so stale legacy data cannot reappear. Storage denial or quota failures retain usable memory with `persistent: false`. Failed writes remain pending; `load()` retries that memory snapshot before reading old stored data. Memory cannot survive a full page reload when the browser refuses all persistence. A later successful write restores `persistent: true`.

Consumers own product presentation, authorization for any eventual server mutation, validation of real prices/stock, and any explicit server synchronization. Browser data is untrusted input.
