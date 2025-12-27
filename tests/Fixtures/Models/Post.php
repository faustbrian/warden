<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Models;

use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Test fixture model for proposition testing.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @property int    $id
 * @property string $title
 * @property int    $user_id
 */
final class Post extends Model
{
    use HasFactory;
    use HasVariablePrimaryKey;

    public $timestamps = false;

    protected $guarded = [];
}
