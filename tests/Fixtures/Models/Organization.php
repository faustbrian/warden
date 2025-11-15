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

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Organization extends Model
{
    use HasFactory;
    use Authorizable;
    use HasRolesAndAbilities;

    public $incrementing = false;

    protected $guarded = [];

    protected $primaryKey = 'ulid';

    protected $keyType = 'string';

    #[Override()]
    public function getKeyName()
    {
        return 'ulid';
    }

    #[Override()]
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            if (!$model->ulid) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }
}
