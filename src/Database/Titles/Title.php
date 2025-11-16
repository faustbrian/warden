<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Titles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use function preg_replace;
use function str_replace;
use function ucfirst;

/**
 * Base class for generating human-readable titles from model data.
 *
 * This abstract class provides common functionality for converting model
 * attributes (particularly names) into user-friendly display titles. Subclasses
 * implement model-specific title generation logic while inheriting the shared
 * humanization utilities.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Title
{
    /**
     * The generated human-readable title.
     */
    protected string $title = '';

    /**
     * Create a new title instance for the given model.
     *
     * Factory method that instantiates the appropriate title class and passes
     * the model to its constructor for title generation.
     *
     * @param  Model  $model The model to generate a title for
     * @return static A new title instance with the generated title
     *
     * @phpstan-return static
     */
    public static function from(Model $model): static
    {
        /** @phpstan-ignore-next-line Safe usage in factory pattern */
        return new static($model);
    }

    /**
     * Retrieve the generated title as a string.
     *
     * @return string The human-readable title
     */
    public function toString(): string
    {
        return $this->title;
    }

    /**
     * Convert a string into a human-readable format.
     *
     * Applies multiple transformations to create user-friendly titles:
     * 1. Preserves spaces by temporarily converting them to underscores
     * 2. Converts the string to snake_case
     * 3. Replaces dashes and underscores with spaces
     * 4. Adds spacing before hash/pound symbols
     * 5. Capitalizes the first letter
     *
     * This handles Laravel's inflector behavior across different versions while
     * ensuring consistent, readable output for display purposes.
     *
     * @param  string $value The raw string to humanize
     * @return string The humanized string suitable for display
     */
    protected function humanize(string $value): string
    {
        // Older versions of Laravel's inflector strip out spaces
        // in the original string, so we'll first swap out all
        // spaces with underscores, then convert them back.
        $value = str_replace(' ', '_', $value);

        // First we'll convert the string to snake case. Then we'll
        // convert all dashes and underscores to spaces. Finally,
        // we'll add a space before a pound (Laravel doesn't).
        $value = Str::snake($value);

        $value = preg_replace('~(?:-|_)+~', ' ', $value);

        $value = preg_replace('~([^ ])(?:#)+~', '$1 #', (string) $value);

        return ucfirst((string) $value);
    }
}
