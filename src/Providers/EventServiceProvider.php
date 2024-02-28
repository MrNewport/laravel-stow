<?php

namespace MrNewport\LaravelStow\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use MrNewport\LaravelStow\Events\BasketDeletingEvent;
use MrNewport\LaravelStow\Listeners\BasketDeletingListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        BasketDeletingEvent::class => [
            BasketDeletingListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
