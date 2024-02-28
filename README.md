
# Laravel Stow Package

## Overview
Laravel Stow is an unofficial laravel package designed to handle the management of baskets and basket items within a Laravel application. It provides a flexible and extensible solution for handling shopping carts, wishlists, and other similar use cases. This package intentionally avoids pricing and checkout functionality for granularity purposes, focusing solely on the management of items within baskets. If you are looking for a qty based pricelist functionality, keep your eye out for mrnewport/price, my upcoming package.


## Installation
To integrate the Laravel Stow package into your Laravel project, execute:
```bash
composer require mrnewport/laravel-stow
```
Then, publish and migrate the database tables:
```bash
php artisan vendor:publish --provider="MrNewport\LaravelStow\StowProvider"
php artisan migrate
```


## Configuration
Define basket instances and restrictions in `config/stow.php` to ensure type safety and integrity:
```php
return [
    'instances' => [
        'cart' => [Product::class, Item::class],
        // Additional instances...
    ],
];
```
Instances not listed will remain unrestricted, accepting any `Stowable` models.


### Creating Baskets
Instantiate a basket, specifying an optional type (instance name):
```php
$cart = new Basket('cart'); // restricted to Product and Item (see stow.php config)
$wishlist = new Basket(); // Unrestricted, any item implementing Stowable interface
```


### Adding Items to Baskets
Add Stowable interface to any models to allow them to be added to baskets:
```php
class Product implements Stowable
{
    // ...
}
```

Add items respecting instance restrictions and options:
```php
$product = new Product();
$cart->add($product, 1, ['size' => 'M', 'color' => 'blue']);

$item = new Item(); // Assume this class implements Stowable as well
$cart->add($item, 2);

// Service has not been explicitly allowed in the 'cart' instance (see stow.php config)
$service = new Service();
$cart->add($service); // Throws UnstowableObjectException

$wishlist->add($service); // instances without restrictions can accept any Stowable
```

Items that are already in the basket will have their quantities incremented only when the item exists with identical options.
```php
$basketItem1 = $cart->add($product); // new product added because options don't match, quantity in cart is 1
$basketItem2->add($product, 3, ['size' => 'M', 'color' => 'blue']); // product with same options already in cart, quantity incremented and is now 3
```


### Updating Basket Items
Update item details within the basket, this requires the BasketItem or its ID:
```php
$cart->change($basketItem, 3, ['size' => 'L']); // Update quantity and options
```


### Removing Items from Baskets
Remove items from the basket by BasketItem or ID:
```php
$cart->remove($basketItem);
```

### Retrieving Basket Items
Retrieve items from the basket:
```php
$basketItems = $cart->basketItems->with('stowable')->get();

// group items by stowable type
$grouped = $basketItems->groupBy('stowable_type');

// filter items by stowable class
$filtered = $basketItems->filter(function($item) {
    return $item->stowable instanceof Product;
});

// iterate over items
foreach($basketItems as $basketItem) {
    $stowable = $basketItem->stowable;
    // ...
}
```

### Merging Baskets
Merge the contents of one basket into another:
```php
$wishlist->merge($cart);
```

### Clone Baskets
Clone a basket and its associated items:
```php
$clone = $wishlist->clone();
```


### Deleting Baskets
Delete a basket and its associated items:
```php
$cart->delete();
```


## Detailed Events Overview
Here's a look at the events, the objects they provide, and how they can be used:

### 1. BasketCreatedEvent
- **Object Included**: `$basket` (The newly created basket instance)
- **Usage**: You can use this event to perform actions after a new basket has been created, such as logging, custom notifications, or integrating with other systems.

### 2. BasketUpdatedEvent
- **Object Included**: `$basket` (The updated basket instance)
- **Usage**: This event allows you to respond to updates on a basket, enabling actions like auditing, triggering business workflows, or updating related data.

### 3. BasketDeletedEvent
- **Object Included**: `$basket` (The deleted basket instance)
- **Usage**: Important for cleaning up related resources or performing actions post-deletion. The built-in listener uses this to remove associated basket items, but you can extend functionality to suit other needs.

### 4. BasketItemCreatedEvent
- **Object Included**: `$basketItem` (The newly added basket item instance)
- **Usage**: Useful for tracking additions to the basket, updating inventory, or triggering recommendations based on new basket contents.

### 5. BasketItemUpdatedEvent
- **Object Included**: `$basketItem` (The updated basket item instance)
- **Usage**: Leverage this event to handle changes in basket items, such as adjusting stock levels, recalculating basket totals, or applying additional business rules.

### 6. BasketItemDeletedEvent
- **Object Included**: `$basketItem` (The removed basket item instance)
- **Usage**: This event can be used for actions similar to BasketItemUpdatedEvent, but specifically in response to item deletions.

## Creating and Registering Custom Listeners
To create a custom listener for any of these events, generate a listener using the Artisan command:

```bash
php artisan make:listener YourCustomListener --event=BasketUpdatedEvent
```

In your custom listener, you can access the event object and its properties:

```php
namespace App\Listeners;

use MrNewport\LaravelStow\Events\BasketUpdatedEvent;

class YourCustomListener
{
    public function handle(BasketUpdatedEvent $event)
    {
        $basket = $event->basket; // Access the updated basket
        // Implement your custom logic here
    }
}
```

Register your listener in the `EventServiceProvider`:

```php
protected $listen = [
    'MrNewport\LaravelStow\Events\BasketUpdatedEvent' => [
        'App\Listeners\YourCustomListener',
    ],
];
```

## Best Practices and Possible Pitfalls
- Ensure correct instance configuration to prevent unintended item types.
- Utilize events for syncing basket states across the application.
- Monitor performance when using large baskets, and consider caching strategies.
