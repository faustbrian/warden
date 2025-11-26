<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Enums\MorphType;
use Cline\Warden\Enums\PrimaryKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('warden.primary_key_type', 'id')) ?? PrimaryKeyType::ID;
        $actorMorphType = MorphType::tryFrom(config('warden.actor_morph_type', 'morph')) ?? MorphType::Morph;
        $boundaryMorphType = MorphType::tryFrom(config('warden.boundary_morph_type', 'morph')) ?? MorphType::Morph;
        $subjectMorphType = MorphType::tryFrom(config('warden.subject_morph_type', 'morph')) ?? MorphType::Morph;

        Schema::create(Models::table('abilities'), function (Blueprint $table) use ($primaryKeyType, $subjectMorphType, $boundaryMorphType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('guard_name')->default('web');

            match ($subjectMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('subject'),
                MorphType::UUID => $table->nullableUuidMorphs('subject'),
                MorphType::Numeric => $table->nullableNumericMorphs('subject'),
                MorphType::Morph => $table->nullableMorphs('subject'),
            };

            match ($boundaryMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('boundary'),
                MorphType::UUID => $table->nullableUuidMorphs('boundary'),
                MorphType::Numeric => $table->nullableNumericMorphs('boundary'),
                MorphType::Morph => $table->nullableMorphs('boundary'),
            };

            $table->boolean('only_owned')->default(false);
            $table->json('options')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();
        });

        Schema::create(Models::table('roles'), function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('guard_name')->default('web');
            $table->integer('scope')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['name', 'guard_name', 'scope'],
                'roles_name_guard_unique',
            );
        });

        Schema::create(Models::table('assigned_roles'), function (Blueprint $table) use ($primaryKeyType, $actorMorphType, $boundaryMorphType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };

            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->foreignUlid('role_id')->index()->constrained(Models::table('roles'))->cascadeOnUpdate()->cascadeOnDelete(),
                PrimaryKeyType::UUID => $table->foreignUuid('role_id')->index()->constrained(Models::table('roles'))->cascadeOnUpdate()->cascadeOnDelete(),
                PrimaryKeyType::ID => $table->foreignId('role_id')->index()->constrained(Models::table('roles'))->cascadeOnUpdate()->cascadeOnDelete(),
            };

            match ($actorMorphType) {
                MorphType::ULID => $table->ulidMorphs('actor'),
                MorphType::UUID => $table->uuidMorphs('actor'),
                MorphType::Numeric => $table->numericMorphs('actor'),
                MorphType::Morph => $table->morphs('actor'),
            };

            match ($boundaryMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('boundary'),
                MorphType::UUID => $table->nullableUuidMorphs('boundary'),
                MorphType::Numeric => $table->nullableNumericMorphs('boundary'),
                MorphType::Morph => $table->nullableMorphs('boundary'),
            };

            match ($actorMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('restricted_to'),
                MorphType::UUID => $table->nullableUuidMorphs('restricted_to'),
                MorphType::Numeric => $table->nullableNumericMorphs('restricted_to'),
                MorphType::Morph => $table->nullableMorphs('restricted_to'),
            };

            $table->integer('scope')->nullable()->index();

            $table->index(
                ['actor_id', 'actor_type', 'scope'],
                'assigned_roles_actor_index',
            );
        });

        Schema::create(Models::table('permissions'), function (Blueprint $table) use ($primaryKeyType, $actorMorphType, $boundaryMorphType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };

            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->foreignUlid('ability_id')->index()->constrained(Models::table('abilities'))->cascadeOnUpdate()->cascadeOnDelete(),
                PrimaryKeyType::UUID => $table->foreignUuid('ability_id')->index()->constrained(Models::table('abilities'))->cascadeOnUpdate()->cascadeOnDelete(),
                PrimaryKeyType::ID => $table->foreignId('ability_id')->index()->constrained(Models::table('abilities'))->cascadeOnUpdate()->cascadeOnDelete(),
            };

            match ($actorMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('actor'),
                MorphType::UUID => $table->nullableUuidMorphs('actor'),
                MorphType::Numeric => $table->nullableNumericMorphs('actor'),
                MorphType::Morph => $table->nullableMorphs('actor'),
            };

            match ($boundaryMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('boundary'),
                MorphType::UUID => $table->nullableUuidMorphs('boundary'),
                MorphType::Numeric => $table->nullableNumericMorphs('boundary'),
                MorphType::Morph => $table->nullableMorphs('boundary'),
            };

            $table->boolean('forbidden')->default(false);
            $table->integer('scope')->nullable()->index();

            $table->index(
                ['actor_id', 'actor_type', 'scope'],
                'permissions_actor_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop(Models::table('permissions'));
        Schema::drop(Models::table('assigned_roles'));
        Schema::drop(Models::table('roles'));
        Schema::drop(Models::table('abilities'));
    }
};
