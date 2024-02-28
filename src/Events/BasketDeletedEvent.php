<?php

namespace MrNewport\LaravelStow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MrNewport\LaravelStow\Models\Basket;

class BasketDeletedEvent
{
    use Dispatchable, SerializesModels;

    public Basket $basket;

    public function __construct(Basket $basket)
    {
        $this->basket = $basket;
    }
}
