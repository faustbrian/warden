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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

use function config;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Organization extends Model
{
    use HasFactory;
    use Authorizable;
    use HasRolesAndAbilities;

    protected $guarded = [];

    #[Override()]
    public function getIncrementing()
    {
        return config('warden.primary_key_type', 'id') === 'id';
    }

    #[Override()]
    public function getKeyType()
    {
        return config('warden.primary_key_type', 'id') === 'id' ? 'int' : 'string';
    }

    #[Override()]
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            if (config('warden.primary_key_type', 'id') === 'id') {
                return;
            }

            $keyName = $model->getKeyName();

            if (!$model->{$keyName}) {
                $model->{$keyName} = match (config('warden.primary_key_type', 'id')) {
                    'ulid' => Str::lower((string) Str::ulid()),
                    'uuid' => Str::lower((string) Str::uuid()),
                    default => null,
                };
            }
        });
    }
}
