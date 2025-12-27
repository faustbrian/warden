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
        Schema::create('posts', function (Blueprint $table): void {
            $keyType = config('warden.primary_key_type', 'id');

            match ($keyType) {
                'ulid' => $table->ulid('id')->primary(),
                'uuid' => $table->uuid('id')->primary(),
                default => $table->id(),
            };

            match ($keyType) {
                'ulid' => $table->ulid('user_id'),
                'uuid' => $table->uuid('user_id'),
                default => $table->foreignId('user_id'),
            };

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
