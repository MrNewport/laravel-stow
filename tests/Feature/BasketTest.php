<?php

namespace MrNewport\LaravelStow\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use MrNewport\LaravelStow\Tests\TestCase;
use TypeError;
use MrNewport\LaravelStow\Events\BasketCreatedEvent;
use MrNewport\LaravelStow\Events\BasketDeletedEvent;
use MrNewport\LaravelStow\Events\BasketItemCreatedEvent;
use MrNewport\LaravelStow\Events\BasketItemDeletedEvent;
use MrNewport\LaravelStow\Events\BasketItemUpdatedEvent;
use MrNewport\LaravelStow\Exceptions\UnstowableObjectException;
use MrNewport\LaravelStow\Models\Basket;
use MrNewport\LaravelStow\Tests\Models\StowableTest;
use MrNewport\LaravelStow\Tests\Models\UnstowableTest;

class BasketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A test to ensure that objects implementing Stowable
     * are capable of being added to the basket.
     *
     * @return void
     */
    public function testStowableObjectsAreStowable()
    {
        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->add($stowableObject);
        $this->assertEquals(1, $basket->basketItems()->count());
    }

    /**
     * A test to ensure that objects not implementing Stowable
     * are not capable of being added to the basket.
     *
     * @return void
     */
    public function testUnstowableObjectsUnstowable()
    {
        $unstowableObject = new UnstowableTest();
        $basket = new Basket();
        $this->expectException(TypeError::class);
        $basket->add($unstowableObject);
    }

    /**
     * A test to ensure that objects not within instance restrictions
     * are not capable of being added to the basket, even when implementing
     * Stowable.
     *
     * @return void
     */
    public function testUnconfiguredInstancesRestricted()
    {
        $stowableObject = new StowableTest();
        Config::set('basket.instances.basket', [Model::class]);
        $basket = new Basket();
        $this->expectException(UnstowableObjectException::class);
        $basket->add($stowableObject);
    }

    /**
     * A test to ensure that objects whose class are within instance restrictions
     * are capable of being added to the basket.
     *
     * @return void
     */
    public function testConfiguredInstancesStowable()
    {
        $stowableObject = new StowableTest();
        Config::set('basket.instances.basket', [$stowableObject::class]);
        $basket = new Basket();
        $basket->add($stowableObject);
        $this->assertEquals(1, $basket->basketItems()->count());
    }

    /**
     * A test to ensure that even objects whose class are within instance restrictions
     * are not capable of being added to the basket if they do not implement Stowable.
     *
     * @return void
     */
    public function testConfiguredInstancesUnstowableWhenNotImplementingStowable()
    {
        $unstowableObject = new UnstowableTest();
        Config::set('basket.instances.basket', [$unstowableObject::class]);
        $basket = new Basket();
        $this->expectException(TypeError::class);
        $basket->add($unstowableObject);
    }

    /**
     * A test to ensure that the same item added twice increments item quantity
     * and does not add as a new item
     *
     * @return void
     */
    public function testDuplicateIncrementsQuantity()
    {
        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->add($stowableObject);
        $basketItem = $basket->add($stowableObject, 2);
        $this->assertEquals(3, $basketItem->quantity);
    }

    /**
     * A test to ensure that the same item, added twice, with different options
     * does not increment item quantity, and instead adds as a new item
     *
     * @return void
     */
    public function testSameItemDifferentOptionsAddsAsNew()
    {
        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->add($stowableObject, options: ['loves' => 'cats']);
        $basket->add($stowableObject, options: ['loves' => 'dogs']);
        $this->assertEquals(2, $basket->basketItems()->count());
    }

    /**
     * A test to ensure that items are properly deleted using the remove function
     * items will be removed whether the BasketItem or the BasketItem->id is passed
     *
     * @return void
     */
    public function testBasketItemRemoval()
    {
        $stowableObject = new StowableTest();
        $basket = new Basket();

        $basketItem = $basket->add($stowableObject);
        $basket->remove($basketItem);
        $this->assertEquals(0, $basket->basketItems()->count());

        $basketItem = $basket->add($stowableObject);
        $basket->remove($basketItem->id);
        $this->assertEquals(0, $basket->basketItems()->count());
    }

    /**
     * A test to ensure that basket creation emits appropriate event
     *
     * @return void
     */
    public function testBasketCreatedEventEmitted()
    {
        Event::fake(BasketCreatedEvent::class);

        $basket = new Basket();
        $basket->push();

        Event::assertDispatched(BasketCreatedEvent::class);
    }

    /**
     * A test to ensure that basket creation emits appropriate event
     *
     * @return void
     */
    public function testBasketDeletedEventEmitted()
    {
        Event::fake(BasketDeletedEvent::class);

        $basket = new Basket();
        $basket->push();
        $basket->delete();

        Event::assertDispatched(BasketDeletedEvent::class);
    }

    /**
     * A test to ensure that basket item add emits appropriate event
     *
     * @return void
     */
    public function testBasketItemCreatedEventEmittedThroughBasketAdd()
    {
        Event::fake(BasketItemCreatedEvent::class);

        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->push();
        $basketItem = $basket->add($stowableObject);

        Event::assertDispatched(BasketItemCreatedEvent::class);
    }

    /**
     * A test to ensure that basket item change emits appropriate event
     *
     * @return void
     */
    public function testBasketItemUpdatedEventEmittedThroughBasketChange()
    {
        Event::fake(BasketItemUpdatedEvent::class);

        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->push();
        $basketItem = $basket->add($stowableObject);
        $basket->change($basketItem, 6, ['cat' => 'burglar']);

        Event::assertDispatched(BasketItemUpdatedEvent::class);
    }

    /**
     * A test to ensure that basket item remove emits appropriate event
     *
     * @return void
     */
    public function testBasketItemDeletedEventEmittedThroughBasketRemove()
    {
        Event::fake(BasketItemDeletedEvent::class);

        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->push();
        $basketItem = $basket->add($stowableObject);
        $basket->remove($basketItem);

        Event::assertDispatched(BasketItemDeletedEvent::class);
    }

    /**
     * A test to ensure that Basket::deleting emits appropriate event
     *
     * @return void
     */
    public function testBasketItemDeletedEventEmittedThroughBasketDeleting()
    {
        Event::fake(BasketItemDeletedEvent::class);

        $stowableObject = new StowableTest();
        $basket = new Basket();
        $basket->push();
        $basketItem = $basket->add($stowableObject);
        $basket->delete();

        $this->assertTrue($basketItem->fresh()->trashed());

        Event::assertDispatched(BasketItemDeletedEvent::class);
    }

    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
    }
}
