<?php

namespace MrNewport\LaravelStow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MrNewport\LaravelStow\Events\BasketCreatedEvent;
use MrNewport\LaravelStow\Events\BasketDeletedEvent;
use MrNewport\LaravelStow\Events\BasketDeletingEvent;
use MrNewport\LaravelStow\Exceptions\UnstowableObjectException;
use MrNewport\LaravelStow\Interfaces\Stowable;

/**
 * Basket
 *
 * @property int $id
 * @property string $instance
 * @property string $slug
 * @property array $options
 * @property Collection::BasketItem $basketItems
 */
class Basket extends Model
{
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['instance'];

    /**
     * @var array<string, string>
     */
    protected $casts = ['options' => 'array'];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => BasketCreatedEvent::class,
        'deleted' => BasketDeletedEvent::class,
        'deleting' => BasketDeletingEvent::class,
    ];

    public function __construct(string $instance = 'basket')
    {
        $this->attributes['instance'] = $instance;
        $this->attributes['slug'] = Str::random(40);
        // change slug if exists
        while (static::where('slug', $this->attributes['slug'])->count() > 0) {
            $this->attributes['slug'] = Str::random(40);
        }
    }

    /*
    *   TODO: Add Exceptions and make them reportable if necessary https://laravel.com/docs/9.x/errors#reporting-exceptions
    */

    /**
     * Basket Items
     *
     * @return HasMany Basket Items with this basket as their parent
     */
    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }

    /**
     * Items
     * The polymorphic stowable items in the basket with their quantities, and options
     * through the BasketItem pivot model
     * @return MorphMany polymorphic stowable items in the basket
     */
    public function items(): MorphMany
    {
        return $this->morphMany(BasketItem::class, 'stowable');
    }


    /**
     * Add to basket
     *
     * @param  Stowable  $item  object
     * @param  int  $qty  Quantity of stowable
     * @param  array  $options  List of options that make this product unique
     * @return BasketItem Model that is created or found/modified
     *
     * @throws UnstowableObjectException
     */
    public function add(Stowable $item, int $qty = 1, array $options = []): BasketItem
    {
        $this->verifyStowability($item::class);

        // Add basket to DB if not added
        if (!$this->getKey()) {
            $this->push();
        }

        $basketItem = $this->basketItems()->whereMorphedTo('stowable', $item)->first();

        if ($basketItem && $basketItem->options == $options) {
            $basketItem->quantity += $qty;
            $basketItem->save();
        } else {
            $basketItem = new BasketItem();
            $basketItem->options = $options;
            $basketItem->quantity = $qty;
            $basketItem->stowable_type = $item::class;
            $basketItem->stowable_id = $item->getKey();
            $this->basketItems()->save($basketItem);
        }

        return $basketItem->fresh();
    }

    /**
     * Update item in basket
     *
     * @param  BasketItem|int  $basketItem  BasketItem object or id
     * @param  int  $qty  Quantity to set
     * @param  array  $options  List of options that make this product unique
     * @return bool update successful
     */
    public function change(int|BasketItem $basketItem, int $qty = 1, array $options = []): bool
    {
        /** @var BasketItem $basketItem */
        $basketItem = $basketItem instanceof BasketItem
            ? $basketItem
            : BasketItem::findOrFail($basketItem);
        $basketItem->quantity = $qty;
        $basketItem->options = $options;

        return $basketItem->update();
    }

    /**
     * Remove from basket
     *
     * @return bool deletion successful
     *
     * @throws ModelNotFoundException
     */
    public function remove(BasketItem|int $basketItem): bool
    {
        return ($basketItem instanceof BasketItem)
            ? $basketItem->delete()
            : BasketItem::findOrFail($basketItem)->delete();
    }

    /**
     * Merge a basket with this basket
     *
     * @param  Basket  $toMerge  object to merge with this
     *
     * @throws UnstowableObjectException
     */
    public function merge(Basket $toMerge): void
    {
        if (!$toMerge->id == $this->id) {
            if ($toMerge->basketItems()->count() > 0) {
                $toMergeItems = $toMerge->items;
                foreach ($toMergeItems as $item) {
                    $this->add($item->stowable, $item->quantity, $item->options);
                }
            }
            $this->save();
        }
    }

    /**
     * Clone a basket with a unique uri
     *
     * @return Basket clone of this basket
     */
    public function clone($toInstance = null): Basket
    {
        $new = new Basket($toInstance ?? $this->instance);
        $new->push();
        $new->basketItems()->sync($this->basketItems);

        return $new;
    }

    /**
     * Verify the item type is within the allowed types for this instance
     *
     * @param  string  $className  class name to check compatibility
     * @return bool whether item is stowable with current config
     *
     * @throws UnstowableObjectException
     */
    public function verifyStowability(string $className): bool
    {
        $config = config("basket.instances.$this->instance");
        if (isset($config) && !in_array($className, $config)) {
            throw new UnstowableObjectException("$className not allowed in basket instance \"$this->instance\".");
        }

        return true;
    }
}
