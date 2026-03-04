<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
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
        Schema::table(Models::table('assigned_roles'), function (Blueprint $table): void {
            $table->timestamps();
        });

        Schema::table(Models::table('permissions'), function (Blueprint $table): void {
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Models::table('assigned_roles'), function (Blueprint $table): void {
            $table->dropTimestamps();
        });

        Schema::table(Models::table('permissions'), function (Blueprint $table): void {
            $table->dropTimestamps();
        });
    }
};
