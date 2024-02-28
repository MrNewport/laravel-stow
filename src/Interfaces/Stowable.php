<?php

namespace MrNewport\LaravelStow\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphTo;

interface Stowable
{
    public function basketItems(): MorphTo;

    public function getKey(): mixed;
}
