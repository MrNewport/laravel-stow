<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MrNewport\LaravelStow\Models\Basket;
use MrNewport\LaravelStow\Tests\Models\IdentifiedItem;

it('persists and resolves configured model keys through basket mutations', function (string $type, string $id) {
    Schema::drop('basket_items');
    config(['stow.morph_key_type' => $type]);
    (new CreateBasketItemsTable)->up();
    Schema::create('identified_items', function (Blueprint $table) use ($type) {
        match ($type) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->unsignedBigInteger('id')->primary(),
        };
        $table->string('name');
        $table->timestamps();
    });
    $item = IdentifiedItem::create(['id' => $id, 'name' => 'Saved design']);
    $basket = new Basket('saved-builds');
    $line = $basket->add($item, 2, ['finish' => 'natural']);
    expect((string) $line->stowable_id)->toBe($id)
        ->and($line->stowable->name)->toBe('Saved design')
        ->and($item->basketItems()->count())->toBe(1);
    $basket->add($item, 1, ['finish' => 'natural']);
    expect($line->fresh()->quantity)->toBe(3);
    $basket->change($line, 4, ['finish' => 'natural']);
    expect($line->fresh()->quantity)->toBe(4);
    $basket->remove($line);
    expect($basket->basketItems()->count())->toBe(0);
})->with([
    ['int', '12'],
    ['uuid', 'af7d85ca-a99e-490d-8f37-448864c85db4'],
    ['ulid', '01K5JMK5AQPPWMS0CJTX4FT8QF'],
]);

it('rejects invalid key configuration before creating the items table', function () {
    Schema::drop('basket_items');
    config(['stow.morph_key_type' => 'typo']);
    expect(fn () => (new CreateBasketItemsTable)->up())->toThrow(InvalidArgumentException::class);
    expect(Schema::hasTable('basket_items'))->toBeFalse();
});

it('does not silently change keys after the initial migration', function () {
    config(['stow.morph_key_type' => 'uuid']);
    $this->artisan('migrate', ['--database' => 'testbench'])->assertSuccessful();
    expect(Schema::getColumnType('basket_items', 'stowable_id'))->toBe('integer');
});
