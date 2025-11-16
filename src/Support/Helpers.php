<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Support;

use BackedEnum;
use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

use function array_all;
use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function iterator_to_array;
use function method_exists;
use function throw_unless;

/**
 * Utility helper functions for the Warden authorization package.
 *
 * Provides static helper methods for common operations including model extraction,
 * array manipulation, type detection, and authority mapping. These utilities are
 * used throughout the package to normalize inputs and perform common transformations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Helpers
{
    /**
     * Validate that the given logical operator is one of the allowed values.
     *
     * Ensures that query builder logical operators are restricted to 'and' or 'or'
     * to prevent SQL injection and maintain query integrity when dynamically
     * constructing authorization queries.
     *
     * @param string $operator The logical operator to validate (expected: 'and' or 'or')
     *
     * @throws InvalidArgumentException When the operator is not 'and' or 'or'
     */
    public static function ensureValidLogicalOperator(string $operator): void
    {
        throw_unless(in_array($operator, ['and', 'or'], true), InvalidArgumentException::class, $operator.' is an invalid logical operator');
    }

    /**
     * Extract a model instance and its associated keys from various input formats.
     *
     * Normalizes different input types into a consistent tuple of [model, keys] for
     * authorization checks. Handles string class names, model instances, and collections
     * of models, extracting their primary keys automatically. This enables flexible
     * authorization APIs that accept multiple input formats.
     *
     * @param  class-string<Model>|\Illuminate\Database\Eloquent\Collection<int, Model>|Model $model The model or model collection to extract from
     * @param  null|array<int|string>                                                         $keys  Optional explicit keys to use instead of extracting from model
     * @return null|array{0: Model, 1: array<int|string>|Collection<int, mixed>}              Tuple of model instance and keys, or null if extraction fails
     */
    public static function extractModelAndKeys($model, ?array $keys = null): ?array
    {
        if (null !== $keys) {
            if (is_string($model)) {
                /** @var Model */
                $model = new $model();
            }

            if (!$model instanceof Model) {
                return null;
            }

            return [$model, $keys];
        }

        if ($model instanceof Model) {
            /** @var array<int|string> $modelKeys */
            $modelKeys = [$model->getAttribute(Models::getModelKey($model))];

            return [$model, $modelKeys];
        }

        if ($model instanceof Collection) {
            /** @var Collection<int, mixed> $keys */
            $keys = $model->map(fn ($model) => $model->getAttribute(Models::getModelKey($model)));
            $firstModel = $model->first();

            if (!$firstModel instanceof Model) {
                return null;
            }

            return [$firstModel, $keys];
        }

        return null;
    }

    /**
     * Ensure all specified keys exist in the array with a default value.
     *
     * Fills in missing keys with the provided default value without overwriting
     * existing keys. Used to normalize grouped results where certain categories
     * might be absent, ensuring consistent array structure for downstream processing.
     *
     * @param  array<array-key, mixed> $array The array to fill
     * @param  mixed                   $value The default value to assign to missing keys
     * @param  iterable<array-key>     $keys  The keys that must exist in the array
     * @return array<array-key, mixed> The array with all specified keys present
     */
    public static function fillMissingKeys(array $array, $value, $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Categorize a mixed collection of models and identifiers by their type.
     *
     * Separates an iterable of models and identifiers into three distinct groups:
     * integer IDs, string identifiers (like UUIDs), and model instances. This
     * enables different processing strategies for each type when performing bulk
     * authorization operations, optimizing database queries and model resolution.
     *
     * @param iterable<int|Model|string> $models Mixed collection of models and identifiers
     *
     * @throws InvalidArgumentException When an element is not a model, integer, or string
     *
     * @return array{integers: array<int>, strings: array<string>, models: array<Model>} Grouped identifiers by type
     */
    public static function groupModelsAndIdentifiersByType($models): array
    {
        // Convert to array first to satisfy Collection constructor types
        $items = is_array($models) ? $models : iterator_to_array($models);

        /** @var array<string, array<int|Model|string>> */
        $groups = new Collection($items)->groupBy(function ($model): string {
            if (is_numeric($model)) {
                return 'integers';
            }

            if (is_string($model)) {
                return 'strings';
            }

            return 'models';
        })->map(fn ($items) => $items->all())->all();

        /** @var array{integers: array<int>, strings: array<string>, models: array<Model>} */
        return self::fillMissingKeys($groups, [], ['integers', 'strings', 'models']);
    }

    /**
     * Check if the given value is an associative array.
     *
     * An array is considered associative if it has non-sequential or non-numeric keys.
     * Arrays with sequential numeric keys starting at 0 are indexed arrays, not associative.
     * This distinction is important when serializing authorization data where the structure
     * affects how it should be processed.
     *
     * @param  mixed $array The value to check
     * @return bool  True if the value is an associative array, false otherwise
     */
    public static function isAssociativeArray($array): bool
    {
        if (!is_array($array)) {
            return false;
        }

        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    /**
     * Check if the given value is a numerically indexed array.
     *
     * An array is considered indexed if all keys are numeric (not necessarily sequential
     * or starting at 0). This differs from associative arrays which have string keys.
     * Used to determine appropriate serialization and processing strategies for
     * authorization data structures.
     *
     * @param  mixed $array The value to check
     * @return bool  True if the value is a numerically indexed array, false otherwise
     */
    public static function isIndexedArray($array): bool
    {
        if (!is_array($array)) {
            return false;
        }

        return array_all($array, fn ($value, $key): bool => is_numeric($key));
    }

    /**
     * Determine if the model is currently being soft deleted.
     *
     * Checks if the model uses Laravel's SoftDeletes trait and is performing a soft
     * delete operation (as opposed to a force delete). This affects how authorization
     * records should be handled: soft deletes preserve authorization relationships
     * while force deletes remove them permanently.
     *
     * Rather than checking for the SoftDeletes trait directly, this method checks
     * for the presence of the `isForceDeleting` method as a more reliable indicator.
     *
     * @param  Model $model The model instance to check
     * @return bool  True if the model is soft deleting, false if force deleting or trait not present
     */
    public static function isSoftDeleting(Model $model): bool
    {
        // Soft deleting models is controlled by adding the SoftDeletes trait
        // to the model. Instead of recursively looking for that trait, we
        // will check for the existence of the `isForceDeleting` method.
        if (!method_exists($model, 'isForceDeleting')) {
            return false;
        }

        return !$model->isForceDeleting();
    }

    /**
     * Extract the string value from a BackedEnum or return the value as-is.
     *
     * Converts BackedEnum instances to their underlying string value, allowing
     * seamless use of enums in authorization APIs without explicit ->value calls.
     * Non-enum values pass through unchanged.
     *
     * @param  mixed $value The value to normalize (BackedEnum or any other type)
     * @return mixed The enum's string value or the original value
     */
    public static function normalizeEnumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Normalize a value into an array format.
     *
     * Converts various input types into a consistent array format for processing.
     * Collections are converted to arrays, arrays are returned as-is, and scalar
     * values are wrapped in an array. This enables authorization methods to accept
     * flexible input formats while maintaining consistent internal processing.
     *
     * @param  mixed                   $value The value to convert (array, Collection, or scalar)
     * @return array<array-key, mixed> The normalized array representation
     */
    public static function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Collection) {
            return $value->all();
        }

        return [$value];
    }

    /**
     * Group authorities by their model class, mapping to their primary keys.
     *
     * Creates a map of model class names to arrays of primary keys, allowing efficient
     * bulk authorization queries grouped by model type. Non-model values (like integers
     * or strings) are assumed to be User identifiers and mapped to the User class.
     * This enables polymorphic authority relationships while optimizing database queries.
     *
     * @param  array<int|Model|string>                       $authorities Mixed array of model instances and identifiers
     * @return array<class-string<Model>, array<int|string>> Map of model classes to arrays of primary keys
     */
    public static function mapAuthorityByClass(array $authorities): array
    {
        $map = [];

        foreach ($authorities as $authority) {
            if ($authority instanceof Model) {
                $className = $authority::class;

                /** @var int|string $key */
                $key = $authority->getAttribute(Models::getModelKey($authority));
                $map[$className][] = $key;
            } else {
                $userClassName = Models::user()::class;
                $map[$userClassName][] = $authority;
            }
        }

        return $map;
    }

    /**
     * Split an iterable into two collections based on a predicate function.
     *
     * Separates items into two groups: those that satisfy the callback condition
     * (first collection) and those that don't (second collection). Preserves array
     * keys in both resulting collections. Used for separating authorization data
     * into valid and invalid groups for different processing paths.
     *
     * @param  iterable<array-key, mixed>                    $items    The items to partition
     * @param  callable(mixed, array-key): bool              $callback Predicate function returning true for first partition
     * @return Collection<int, Collection<array-key, mixed>> Collection containing two collections: [matching, non-matching]
     */
    public static function partition($items, callable $callback): Collection
    {
        /** @var Collection<array-key, mixed> $truthy */
        $truthy = new Collection();

        /** @var Collection<array-key, mixed> $falsy */
        $falsy = new Collection();

        foreach ($items as $key => $item) {
            if ($callback($item, $key)) {
                $truthy[$key] = $item;
            } else {
                $falsy[$key] = $item;
            }
        }

        return new Collection([$truthy, $falsy]);
    }
}
