<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Models;

use Cline\Warden\Database\Concerns\Authorizable;
use Cline\Warden\Database\HasRolesAndAbilities;
use Cline\Warden\Exceptions\NewUniqueIdCalledException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;

use function config;

/**
 * User model with soft deletes for Spatie migration tests.
 * Uses bigint ID for legacy compatibility, with UUID/ULID for Warden foreign keys.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SoftDeletesSpatieUser extends Model
{
    use HasFactory;
    use Authorizable;
    use HasRolesAndAbilities;
    use SoftDeletes;

    protected $table = 'spatie_users';

    protected $guarded = [];

    #[Override()]
    public function uniqueIds(): array
    {
        $actorMorphType = config('warden.actor_morph_type', 'string');

        if ($actorMorphType === 'uuid') {
            return ['uuid'];
        }

        if ($actorMorphType === 'ulid') {
            return ['ulid'];
        }

        return [];
    }

    public function newUniqueId(): string
    {
        $actorMorphType = config('warden.actor_morph_type', 'string');

        return match ($actorMorphType) {
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            default => throw NewUniqueIdCalledException::forNumericMorphType(),
        };
    }

    #[Override()]
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID/ULID on creation based on morph type
        self::creating(function (self $model): void {
            $actorMorphType = config('warden.actor_morph_type', 'string');

            if ($actorMorphType === 'uuid' && empty($model->uuid)) {
                $model->uuid = $model->newUniqueId();
            } elseif ($actorMorphType === 'ulid' && empty($model->ulid)) {
                $model->ulid = $model->newUniqueId();
            }
        });
    }
}
