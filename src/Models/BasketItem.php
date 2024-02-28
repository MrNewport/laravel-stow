<?php

namespace MrNewport\LaravelStow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MrNewport\LaravelStow\Events\BasketItemCreatedEvent;
use MrNewport\LaravelStow\Events\BasketItemDeletedEvent;
use MrNewport\LaravelStow\Events\BasketItemUpdatedEvent;

/**
 * BasketItem
 *
 * @property int $id
 * @property int $basket_id
 * @property int $quantity
 * @property string $stowable_type
 * @property mixed $stowable_id
 * @property array $options
 * @property Basket|null $basket
 * @property Model $stowable
 */
class BasketItem extends Model
{
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['stowable_type', 'stowable_id', 'options', 'quantity'];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => BasketItemCreatedEvent::class,
        'updated' => BasketItemUpdatedEvent::class,
        'deleted' => BasketItemDeletedEvent::class,
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * @return BelongsTo
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    /**
     * @return MorphTo
     */
    public function stowable(): MorphTo
    {
        return $this->morphTo();
    }
}
