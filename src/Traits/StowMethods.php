<?php

namespace MrNewport\LaravelStow\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use MrNewport\LaravelStow\Models\BasketItem;

trait StowMethods
{
    /**
     * @return MorphMany
     */
    public function basketItems(): MorphMany
    {
        return $this->morphMany(BasketItem::class, 'stowable');
    }
}
