<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Illuminate\Auth\Access\Gate;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Cache\Store;

/**
 * Factory for creating configured Warden instances.
 *
 * Provides a fluent interface for constructing Warden instances with custom
 * configuration including cache stores, clipboards, gates, and user models.
 * Handles the complex initialization and wiring of Warden's components.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Factory
{
    /**
     * Custom cache store for clipboard caching.
     */
    private ?Store $cache = null;

    /**
     * Custom clipboard implementation.
     */
    private ?ClipboardInterface $clipboard = null;

    /**
     * Custom gate instance for authorization.
     */
    private ?GateContract $gate = null;

    /**
     * Whether to register clipboard in service container.
     */
    private bool $registerAtContainer = true;

    /**
     * Whether to register guard callbacks at the gate.
     */
    private bool $registerAtGate = true;

    /**
     * Create a new Warden factory instance.
     *
     * @param null|object $user The user model instance to use for gate resolution, or null
     *                          to use Laravel's default authentication user. When null, the
     *                          gate will resolve the user from Laravel's authentication system.
     *                          Can be set to a specific user instance for testing or custom
     *                          authorization contexts where a non-authenticated user is needed.
     */
    public function __construct(
        private ?object $user = null,
    ) {}

    /**
     * Build and return a fully configured Warden instance.
     *
     * Constructs the Warden instance with all configured components (gate, guard,
     * clipboard) and optionally registers the guard with the gate and the clipboard
     * with the service container based on configuration flags.
     *
     * @return Warden The configured Warden instance
     */
    public function create(): Warden
    {
        $gate = $this->getGate();
        $guard = $this->getGuard();

        $bouncer = new Warden($guard)->setGate($gate);

        if ($this->registerAtGate) {
            $guard->registerAt($gate);
        }

        if ($this->registerAtContainer) {
            $bouncer->registerClipboardAtContainer();
        }

        return $bouncer;
    }

    /**
     * Set a custom cache store for the clipboard.
     *
     * @param  Store $cache The cache store to use for caching permission checks
     * @return $this
     */
    public function withCache(Store $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Set a custom clipboard implementation.
     *
     * Allows injection of custom clipboard implementations for specialized
     * permission checking or storage strategies.
     *
     * @param  ClipboardInterface $clipboard The clipboard instance to use
     * @return $this
     */
    public function withClipboard(ClipboardInterface $clipboard): self
    {
        $this->clipboard = $clipboard;

        return $this;
    }

    /**
     * Set a custom gate instance for authorization.
     *
     * @param  GateContract $gate The gate instance to use for authorization checks
     * @return $this
     */
    public function withGate(GateContract $gate): self
    {
        $this->gate = $gate;

        return $this;
    }

    /**
     * Set the user model for gate authorization checks.
     *
     * @param  null|object $user The user model instance or null for default auth user
     * @return $this
     */
    public function withUser(?object $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Configure whether to register the clipboard in the service container.
     *
     * When enabled, the clipboard instance will be bound in the container as
     * ClipboardInterface, making it available for dependency injection.
     *
     * @param  bool  $bool Whether to register the clipboard (default: true)
     * @return $this
     */
    public function registerClipboardAtContainer(bool $bool = true): self
    {
        $this->registerAtContainer = $bool;

        return $this;
    }

    /**
     * Configure whether to register the guard with the gate.
     *
     * When enabled, the guard's before/after callbacks will be registered with
     * the gate to intercept authorization checks.
     *
     * @param  bool  $bool Whether to register at the gate (default: true)
     * @return $this
     */
    public function registerAtGate(bool $bool = true): self
    {
        $this->registerAtGate = $bool;

        return $this;
    }

    /**
     * Create a guard instance with the configured clipboard.
     *
     * @return Guard The configured guard instance
     */
    private function getGuard(): Guard
    {
        return new Guard($this->getClipboard());
    }

    /**
     * Get or create the clipboard instance.
     *
     * Returns the custom clipboard if set, otherwise creates a new CachedClipboard
     * with the configured (or default) cache store.
     *
     * @return CachedClipboard|ClipboardInterface The clipboard instance
     */
    private function getClipboard(): ClipboardInterface|CachedClipboard
    {
        return $this->clipboard ?: new CachedClipboard($this->getCacheStore());
    }

    /**
     * Get or create the cache store.
     *
     * Returns the custom cache if set, otherwise creates a new ArrayStore for
     * in-memory caching.
     *
     * @return Store The cache store instance
     */
    private function getCacheStore(): Store
    {
        return $this->cache ?: new ArrayStore();
    }

    /**
     * Get or create the gate instance.
     *
     * Returns the custom gate if set, otherwise creates a new Gate with the
     * configured user resolver.
     *
     * @return Gate|GateContract The gate instance
     */
    private function getGate(): GateContract|Gate
    {
        if ($this->gate instanceof GateContract) {
            return $this->gate;
        }

        return new Gate(Container::getInstance(), fn (): ?object => $this->user);
    }
}
