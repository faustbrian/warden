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
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Account extends Model
{
    use HasFactory;
    use Authorizable;
    use HasRolesAndAbilities;

    protected $guarded = [];

    /**
     * Get the actor that owns the account (polymorphic relation).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
