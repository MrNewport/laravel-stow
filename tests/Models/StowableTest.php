<?php

namespace MrNewport\LaravelStow\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelStow\Interfaces\Stowable;
use MrNewport\LaravelStow\Traits\StowMethods;

class StowableTest extends Model implements Stowable
{
    use StowMethods;

    /**
     * Return a basic key for the model.
     * @return mixed
     */
    public function getKey(): mixed
    {
        return 1;
    }
}
