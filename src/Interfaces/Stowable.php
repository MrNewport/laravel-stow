<?php

namespace MrNewport\LaravelStow\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Stowable
{
    public function basketItems(): MorphMany;

    /** @return mixed */
    public function getKey();
}
