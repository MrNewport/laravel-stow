<?php

namespace MrNewport\LaravelStow\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelStow\Interfaces\Stowable;
use MrNewport\LaravelStow\Traits\StowMethods;

class StowableTest extends Model implements Stowable
{
    use StowMethods;

    // Laravel's morph queries read the owner-key attribute, as a saved model supplies it.
    protected $attributes = ['id' => 1];

    /**
     * Return a basic key for the model.
     * @return mixed
     */
    public function getKey(): mixed
    {
        return 1;
    }
}
