<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Enums\MorphType;
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
        // Create shared users table with ULID/UUID support for Warden tests
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table): void {
                $keyType = config('warden.primary_key_type', 'id');

                match ($keyType) {
                    'ulid' => $table->ulid('id')->primary(),
                    'uuid' => $table->uuid('id')->primary(),
                    default => $table->id(),
                };

                $table->string('name')->nullable();
                $table->integer('age')->nullable();
                $table->integer('account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create spatie_users table with auto-increment IDs for Spatie migration tests
        if (!Schema::hasTable('spatie_users')) {
            Schema::create('spatie_users', function ($table): void {
                $table->id();

                // Add UUID/ULID column for Warden foreign key mapping based on actor_morph_type
                $actorMorphType = MorphType::tryFrom(config('warden.actor_morph_type', 'morph')) ?? MorphType::Morph;
                match ($actorMorphType) {
                    MorphType::UUID => $table->uuid('uuid')->nullable()->unique(),
                    MorphType::ULID => $table->ulid('ulid')->nullable()->unique(),
                    default => null,
                };

                $table->string('name')->nullable();
                $table->integer('age')->nullable();
                $table->integer('account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create bouncer_users table with auto-increment IDs for Bouncer migration tests
        if (!Schema::hasTable('bouncer_users')) {
            Schema::create('bouncer_users', function ($table): void {
                $table->id();

                // Add UUID/ULID column for Warden foreign key mapping based on actor_morph_type
                $actorMorphType = MorphType::tryFrom(config('warden.actor_morph_type', 'morph')) ?? MorphType::Morph;
                match ($actorMorphType) {
                    MorphType::UUID => $table->uuid('uuid')->nullable()->unique(),
                    MorphType::ULID => $table->ulid('ulid')->nullable()->unique(),
                    default => null,
                };

                $table->string('name')->nullable();
                $table->integer('age')->nullable();
                $table->integer('account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table): void {
                $keyType = config('warden.primary_key_type', 'id');

                match ($keyType) {
                    'ulid' => $table->ulid('id')->primary(),
                    'uuid' => $table->uuid('id')->primary(),
                    default => $table->id(),
                };

                $morphType = MorphType::tryFrom(config('warden.actor_morph_type', 'morph')) ?? MorphType::Morph;
                match ($morphType) {
                    MorphType::ULID => $table->nullableUlidMorphs('actor'),
                    MorphType::UUID => $table->nullableUuidMorphs('actor'),
                    default => $table->nullableNumericMorphs('actor'),
                };
                $table->string('name')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('teams')) {
            Schema::create('teams', function ($table): void {
                $keyType = config('warden.primary_key_type', 'id');

                match ($keyType) {
                    'ulid' => $table->ulid('id')->primary(),
                    'uuid' => $table->uuid('id')->primary(),
                    default => $table->id(),
                };

                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function ($table): void {
                $keyType = config('warden.primary_key_type', 'id');

                match ($keyType) {
                    'ulid' => $table->ulid('id')->primary(),
                    'uuid' => $table->uuid('id')->primary(),
                    default => $table->id(),
                };

                $table->string('name');
                $table->timestamps();
            });
        }

        $tableNames = [
            'permissions' => 'spatie_permissions',
            'roles' => 'spatie_roles',
            'model_has_permissions' => 'spatie_model_has_permissions',
            'model_has_roles' => 'spatie_model_has_roles',
            'role_has_permissions' => 'spatie_role_has_permissions',
        ];

        $columnNames = [
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
        ];

        $pivotRole = $columnNames['role_pivot_key'];
        $pivotPermission = $columnNames['permission_pivot_key'];

        Schema::create($tableNames['permissions'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'spatie_model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->primary(
                [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                'spatie_model_has_permissions_permission_model_type_primary',
            );
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole): void {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'spatie_model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary(
                [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                'spatie_model_has_roles_role_model_type_primary',
            );
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'spatie_role_has_permissions_permission_id_role_id_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('spatie_role_has_permissions');
        Schema::drop('spatie_model_has_roles');
        Schema::drop('spatie_model_has_permissions');
        Schema::drop('spatie_roles');
        Schema::drop('spatie_permissions');
    }
};
