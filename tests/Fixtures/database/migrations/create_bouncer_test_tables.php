<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Enums\MorphType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function ($table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('age')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounts', function ($table): void {
            $table->increments('id');

            // Use configured actor morph type
            $morphType = MorphType::tryFrom(config('warden.actor_morph_type', 'morph')) ?? MorphType::Morph;

            match ($morphType) {
                MorphType::ULID => $table->nullableUlidMorphs('actor'),
                MorphType::UUID => $table->nullableUuidMorphs('actor'),
                default => $table->nullableMorphs('actor'),
            };

            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('teams', function ($table): void {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('organizations', function ($table): void {
            $table->ulid('ulid')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('organizations');
        Schema::drop('teams');
        Schema::drop('users');
        Schema::drop('accounts');
    }
};
