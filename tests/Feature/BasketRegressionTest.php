<?php

namespace MrNewport\LaravelStow\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use MrNewport\LaravelStow\Models\Basket;
use MrNewport\LaravelStow\Tests\Models\StowableTest;
use MrNewport\LaravelStow\Tests\TestCase;

class BasketRegressionTest extends TestCase
{
    public function test_eloquent_creation_hydration_events_and_cloning(): void
    {
        $basket = Basket::create(['instance' => 'quote']);
        $basket->add(new StowableTest(), 2, ['size' => 'S']);
        $basket->add(new StowableTest(), 3, ['size' => 'L']);
        $basket->add(new StowableTest(), 4, ['size' => 'L']);
        $this->assertSame('quote', $basket->fresh()->instance);
        $this->assertSame([2, 7], $basket->items()->orderBy('id')->pluck('quantity')->all());
        $copy = $basket->clone('order');
        $this->assertSame('order', $copy->instance);
        $this->assertNotSame($basket->slug, $copy->slug);
        $this->assertSame([2, 7], $copy->basketItems()->orderBy('id')->pluck('quantity')->all());
        $copy->delete();
        $this->assertSame(2, $basket->basketItems()->count());
        $this->assertSame(0, $copy->basketItems()->count());
    }

    public function test_cannot_change_or_remove_another_baskets_line(): void
    {
        $owner = new Basket();
        $line = $owner->add(new StowableTest());
        $other = Basket::create(['instance' => 'other']);
        foreach (['change', 'remove'] as $method) {
            try {
                $other->$method($line);
                $this->fail('Foreign basket line was accessible.');
            } catch (ModelNotFoundException) {
                $this->assertSame(1, $line->fresh()->quantity);
            }
        }
    }

    public function test_respects_morph_aliases(): void
    {
        Relation::morphMap(['product' => StowableTest::class]);
        try {
            $basket = new Basket();
            $line = $basket->add(new StowableTest());
            $this->assertSame('product', $line->stowable_type);
            $this->assertSame(2, $basket->add(new StowableTest())->quantity);
            $this->assertSame(1, (new StowableTest())->forceFill(['id' => 1])->basketItems()->count());
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_rejects_nonpositive_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Basket())->add(new StowableTest(), 0);
    }
}
