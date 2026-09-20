<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBasketItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $keyType = config('stow.morph_key_type', 'int');
        if (! in_array($keyType, ['int', 'uuid', 'ulid'], true)) {
            throw new InvalidArgumentException('stow.morph_key_type must be int, uuid, or ulid.');
        }

        Schema::create('basket_items', function (Blueprint $table) use ($keyType) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('basket_id')->constrained('baskets');
            match ($keyType) {
                'uuid' => $table->nullableUuidMorphs('stowable'),
                'ulid' => $table->nullableUlidMorphs('stowable'),
                default => $table->nullableNumericMorphs('stowable'),
            };
            $table->integer('quantity')->default(1);
            $table->json('options');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('basket_items');
    }
}
