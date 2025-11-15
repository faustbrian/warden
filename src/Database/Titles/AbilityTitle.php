<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Titles;

use Cline\Warden\Database\Ability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use function assert;
use function basename;
use function str_replace;

/**
 * Generates human-readable titles for ability models.
 *
 * This class analyzes ability configurations and produces descriptive titles
 * based on the ability's scope, target entity, and ownership constraints. It
 * handles wildcard abilities, model-specific permissions, and various permission
 * patterns to create user-friendly descriptions for display in admin interfaces.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AbilityTitle extends Title
{
    /**
     * Create a new ability title generator for the given ability model.
     *
     * Analyzes the ability's configuration (name, subject_type, subject_id, only_owned)
     * to determine the appropriate human-readable title. Handles multiple permission
     * patterns including wildcards, simple abilities, model-specific permissions,
     * and ownership-based restrictions.
     *
     * @param Model $ability The ability model to generate a title for
     *
     * @phpstan-param Model&Ability $ability
     */
    public function __construct(Model $ability)
    {
        if ($this->isWildcardAbility($ability)) {
            $this->title = $this->getWildcardAbilityTitle($ability);
        } elseif ($this->isRestrictedWildcardAbility($ability)) {
            $this->title = 'All simple abilities';
        } elseif ($this->isSimpleAbility($ability)) {
            $this->title = $this->humanize($ability->name);
        } elseif ($this->isRestrictedOwnershipAbility($ability)) {
            $this->title = $this->humanize($ability->name.' everything owned');
        } elseif ($this->isGeneralManagementAbility($ability)) {
            $this->title = $this->getBlanketModelAbilityTitle($ability);
        } elseif ($this->isBlanketModelAbility($ability)) {
            $this->title = $this->getBlanketModelAbilityTitle($ability, $ability->name);
        } elseif ($this->isSpecificModelAbility($ability)) {
            $this->title = $this->getSpecificModelAbilityTitle($ability);
        } elseif ($this->isGlobalActionAbility($ability)) {
            $this->title = $this->humanize($ability->name.' everything');
        }
    }

    /**
     * Check if the ability grants all permissions on all entities.
     *
     * Matches abilities with name='*' and subject_type='*', representing
     * unrestricted access to all actions on all models in the system.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a wildcard ability
     */
    private function isWildcardAbility(Model $ability): bool
    {
        return $ability->getAttribute('name') === '*' && $ability->getAttribute('subject_type') === '*';
    }

    /**
     * Check if the ability grants all simple (non-model) permissions.
     *
     * Matches abilities with name='*' and subject_type=null, representing
     * access to all simple abilities that aren't tied to specific models.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a restricted wildcard ability
     */
    private function isRestrictedWildcardAbility(Model $ability): bool
    {
        return $ability->name === '*' && null === $ability->subject_type;
    }

    /**
     * Check if the ability is a simple ability not tied to any model.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if subject_type is null
     */
    private function isSimpleAbility(Model $ability): bool
    {
        return null === $ability->subject_type;
    }

    /**
     * Check if the ability is restricted to owned entities for a specific action.
     *
     * Matches abilities with only_owned=true, a specific action name (not '*'),
     * and subject_type='*', allowing the action only on entities owned by the user.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a restricted ownership ability
     */
    private function isRestrictedOwnershipAbility(Model $ability): bool
    {
        return $ability->only_owned && $ability->name !== '*' && $ability->subject_type === '*';
    }

    /**
     * Check if the ability grants all actions on all instances of a model type.
     *
     * Matches abilities with name='*' and a specific subject_type (not '*' or null),
     * but no subject_id, representing full management permissions for a model type.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a general management ability
     */
    private function isGeneralManagementAbility(Model $ability): bool
    {
        return $ability->name === '*'
            && $ability->subject_type !== '*'
            && null !== $ability->subject_type
            && null === $ability->subject_id;
    }

    /**
     * Check if the ability grants a specific action on all instances of a model type.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a blanket model ability
     */
    private function isBlanketModelAbility(Model $ability): bool
    {
        return $ability->name !== '*'
            && $ability->subject_type !== '*'
            && null !== $ability->subject_type
            && null === $ability->subject_id;
    }

    /**
     * Check if the ability targets a specific model instance.
     *
     * Matches abilities with both subject_type and subject_id set, representing
     * permissions scoped to a single model instance by its ID.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this targets a specific model instance
     */
    private function isSpecificModelAbility(Model $ability): bool
    {
        return $ability->subject_type !== '*'
            && null !== $ability->subject_type
            && null !== $ability->subject_id;
    }

    /**
     * Check if the ability allows a specific action on all models globally.
     *
     * @param Model $ability The ability to check
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return bool True if this is a global action ability
     */
    private function isGlobalActionAbility(Model $ability): bool
    {
        return $ability->name !== '*'
            && $ability->subject_type === '*'
            && null === $ability->subject_id;
    }

    /**
     * Generate the title for a wildcard ability.
     *
     * Returns different titles based on ownership constraints: "Manage everything owned"
     * for ownership-restricted wildcards, or "All abilities" for unrestricted access.
     *
     * @param Model $ability The wildcard ability
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return string The generated title
     */
    private function getWildcardAbilityTitle(Model $ability): string
    {
        if ($ability->only_owned) {
            return 'Manage everything owned';
        }

        return 'All abilities';
    }

    /**
     * Generate a title for abilities that cover all instances of a model type.
     *
     * Creates titles like "Manage users" or "Delete posts" by combining the action
     * name with the pluralized model class name.
     *
     * @param Model  $ability The ability to generate a title for
     * @param string $name    The action name (defaults to 'manage')
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return string The generated title
     */
    private function getBlanketModelAbilityTitle(Model $ability, string $name = 'manage'): string
    {
        assert($ability->subject_type !== null);

        return $this->humanize($name.' '.$this->getPluralName($ability->subject_type));
    }

    /**
     * Generate a title for abilities targeting a specific model instance.
     *
     * Creates titles like "Manage User #5" or "Delete Post #42" by combining
     * the action name with the model class name and instance ID.
     *
     * @param Model $ability The ability to generate a title for
     *
     * @phpstan-param Model&Ability $ability
     *
     * @return string The generated title
     */
    private function getSpecificModelAbilityTitle(Model $ability): string
    {
        $name = $ability->name === '*' ? 'manage' : $ability->name;
        assert($ability->subject_type !== null);

        return $this->humanize(
            $name.' '.$this->basename($ability->subject_type).' #'.$ability->subject_id,
        );
    }

    /**
     * Extract and pluralize the base class name from a fully-qualified class name.
     *
     * @param  string $class The fully-qualified class name
     * @return string The pluralized base class name
     */
    private function getPluralName(string $class): string
    {
        return $this->pluralize($this->basename($class));
    }

    /**
     * Extract the base class name from a fully-qualified class name.
     *
     * Converts namespace separators to forward slashes and returns only
     * the final segment, stripping namespaces.
     *
     * @param  string $class The fully-qualified class name
     * @return string The base class name without namespace
     */
    private function basename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Pluralize a string value using Laravel's pluralization rules.
     *
     * @param  string $value The string to pluralize
     * @return string The pluralized string
     */
    private function pluralize(string $value): string
    {
        return Str::plural($value, 2);
    }
}
