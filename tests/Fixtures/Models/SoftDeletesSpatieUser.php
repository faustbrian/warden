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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Legacy user model with soft deletes for Spatie migration tests.
 * Always uses bigint ID to match Spatie's legacy table structure.
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
}
