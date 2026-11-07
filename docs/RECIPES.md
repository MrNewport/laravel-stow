# Stow recipes

## Owned saved builds and cart separation

Use two Basket instances, `saved-builds` and `cart`, and host-owned relations that bind each basket ID to the authenticated workspace. A configuration/build model implements Stowable and StowMethods; choose UUID morph columns before migrations if builds use UUIDs.

```php
$line = DB::transaction(function () use ($workspace, $basketId, $buildId) {
    $basket = $workspace->baskets()->whereKey($basketId)->lockForUpdate()->firstOrFail();
    Gate::authorize('change', $basket);
    if ($basket->instance !== 'cart' || $basket->locked) {
        throw new DomainException('Basket cannot be changed.');
    }
    $build = $workspace->builds()->whereKey($buildId)->firstOrFail();
    return $basket->add($build, 1, ['revision' => (string) $build->revision]);
});
```

The workspace relations and policies are host code; Basket has none by default. A reviewed build revision in options distinguishes a changed configuration. Removing the cart line leaves the independent saved-build basket intact. On checkout, revalidate the build's availability, recalculate its quote in the host pricing service and persist an immutable order snapshot. Do not derive ownership or chargeable price from posted options. Serialize every writer on the same basket row, including guest merges. A host receipt/unique request key handles replay.

## Memory-only produce selection

Inject a Map-backed storage adapter and allow only current produce catalog IDs. Use integer counts where each quantity is one bundle. Render editable selections from items(), subscribe for updates, and send only IDs/counts to an authorized server preparation endpoint when the user continues. The server revalidates catalog membership, count, capacity and current offer and persists its own draft. Page-memory state disappears when the page closes; that is a useful explicit product contract, not a storage failure.

```js
const draft = createBrowserBasket({
  storageKey: 'produce.page', allowedIds: catalog.map(item => item.id),
  maxQuantity: 9, maxItems: 20, storage: () => memoryAdapter,
});
draft.add('tomato', 2);
const payload = {items: draft.items()};
```

Create memoryAdapter as shown in the handbook. Keep fractional kilogram/liter drafts in a separate decimal-aware input contract: the browser basket floors numbers. Stow does not schedule deliveries or charge subscriptions. Cadence can calculate host-owned recurring dates while the application retains billing, skips, shipping and capacity.

## Retain saved browser data through adoption

Use a new versioned storageKey and map the previous ID array in migrate(). Feed only current catalog IDs to allowedIds; removed products are discarded during normalization. Exercise reload, denied storage, malformed legacy JSON, duplicate rows, caps, clear and return navigation. Surface persistent=false as an unobtrusive “changes are kept in this page” notice appropriate to the product. Do not claim a successful server save from that flag.

Keep old keys until rollout is verified; clear writes an empty new envelope and must not resurrect the legacy list. The package normalizes membership and retains failed-write memory state. The host chooses accessible buttons/notices, catalog labels, migration policy and any server sync. Use [browser contract tests](../tests/Browser/browser-basket.test.mjs) as executable examples of storage failures and recovery.
