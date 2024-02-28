<?php

namespace MrNewport\LaravelStow\Listeners;

use MrNewport\LaravelStow\Events\BasketDeletingEvent;

class BasketDeletingListener
{
    public function handle(BasketDeletingEvent $event): void
    {
        $event->basket->basketItems->each(function ($item) {
            $item->delete();
        });
    }
}
