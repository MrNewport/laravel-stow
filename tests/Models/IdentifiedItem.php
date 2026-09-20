<?php

namespace MrNewport\LaravelStow\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelStow\Interfaces\Stowable;
use MrNewport\LaravelStow\Traits\StowMethods;

class IdentifiedItem extends Model implements Stowable
{
    use StowMethods;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
