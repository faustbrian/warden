<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Scope\Scope;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Facade;
use Override;

/**
 * Facade for accessing the ModelRegistry singleton.
 *
 * Provides static access to model and table configuration while maintaining
 * Octane compatibility through container-bound instance management.
 *
 * @method static Ability              ability(array<string, mixed> $attributes = [])
 * @method static string               classname(string $model)
 * @method static void                 enforceMorphKeyMap(array<class-string, string> $map)
 * @method static string               getModelKey(Model $model)
 * @method static bool                 isOwnedBy(Model $authority, Model $model)
 * @method static void                 morphKeyMap(array<class-string, string> $map)
 * @method static void                 ownedVia(Closure|string $model, Closure|string|null $attribute = null)
 * @method static Builder              query(string $table)
 * @method static void                 requireKeyMap()
 * @method static void                 reset()
 * @method static Role                 role(array<string, mixed> $attributes = [])
 * @method static Scope|ScopeInterface scope(?ScopeInterface $scope = null)
 * @method static void                 setAbilitiesModel(string $model)
 * @method static void                 setRolesModel(string $model)
 * @method static void                 setTables(array<string, string> $map)
 * @method static void                 setUsersModel(string $model)
 * @method static string               table(string $table)
 * @method static void                 updateMorphMap(?array<int, class-string> $classNames = null)
 * @method static Model                user(array<string, mixed> $attributes = [])
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ModelRegistry
 */
final class Models extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Returns the fully-qualified class name of the underlying service this
     * facade provides access to. Used by Laravel's facade system to resolve
     * the actual instance from the service container.
     *
     * @return string The service container binding key for the ModelRegistry
     */
    #[Override()]
    protected static function getFacadeAccessor(): string
    {
        return ModelRegistry::class;
    }
}
