<?php

use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Illuminate\Database\Schema\Blueprint;

it('migrates and rolls back on MySQL', function (string $keyType) {
    if (getenv('MYSQL_TEST_DATABASE') !== 'package_test') $this->markTestSkipped('Disposable MySQL runner only.');
    config(['database.default' => 'mysql', 'database.connections.mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
        'database' => 'package_test', 'username' => 'root', 'password' => getenv('MYSQL_TEST_PASSWORD'),
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ]]);
    DB::purge('mysql');
    config(['stow.morph_key_type' => $keyType]);
    Schema::create('users', fn (Blueprint $table) => $table->id());
    try {
        expect(Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]))->toBe(0);
        $column = collect(Schema::getColumns('basket_items'))->firstWhere('name', 'stowable_id');
        expect($column['type_name'])->toBe($keyType === 'int' ? 'bigint' : 'char');
        if ($keyType !== 'int') {
            expect($column['type'])->toBe($keyType === 'uuid' ? 'char(36)' : 'char(26)');
        }
        expect(Artisan::call('migrate:rollback', ['--database' => 'mysql', '--force' => true]))->toBe(0);
        expect(DB::table('migrations')->count())->toBe(0);
    } finally {
        Schema::dropIfExists('users');
        Schema::dropIfExists('migrations');
    }
})->with(['int', 'uuid', 'ulid']);
