<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $tableNames = [
            'abilities' => 'bouncer_abilities',
            'roles' => 'bouncer_roles',
            'assigned_roles' => 'bouncer_assigned_roles',
            'permissions' => 'bouncer_permissions',
        ];

        Schema::create($tableNames['abilities'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('title')->nullable();
            $table->bigInteger('entity_id')->unsigned()->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('only_owned')->default(false);
            $table->json('options')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($tableNames['roles'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('title')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['name', 'scope'],
                'bouncer_roles_name_unique',
            );
        });

        Schema::create($tableNames['assigned_roles'], function (Blueprint $table) use ($tableNames): void {
            $table->bigIncrements('id');
            $table->bigInteger('role_id')->unsigned()->index();
            $table->bigInteger('entity_id')->unsigned();
            $table->string('entity_type');
            $table->bigInteger('restricted_to_id')->unsigned()->nullable();
            $table->string('restricted_to_type')->nullable();
            $table->integer('scope')->nullable()->index();

            $table->index(
                ['entity_id', 'entity_type', 'scope'],
                'bouncer_assigned_roles_entity_index',
            );

            $table->foreign('role_id')
                ->references('id')->on($tableNames['roles'])
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create($tableNames['permissions'], function (Blueprint $table) use ($tableNames): void {
            $table->bigIncrements('id');
            $table->bigInteger('ability_id')->unsigned()->index();
            $table->bigInteger('entity_id')->unsigned()->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('forbidden')->default(false);
            $table->integer('scope')->nullable()->index();

            $table->index(
                ['entity_id', 'entity_type', 'scope'],
                'bouncer_permissions_entity_index',
            );

            $table->foreign('ability_id')
                ->references('id')->on($tableNames['abilities'])
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('bouncer_permissions');
        Schema::drop('bouncer_assigned_roles');
        Schema::drop('bouncer_roles');
        Schema::drop('bouncer_abilities');
    }
};
