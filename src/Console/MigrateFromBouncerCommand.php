<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Console;

use Cline\Warden\Migrators\BouncerMigrator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

use function assert;
use function is_string;
use function sprintf;

/**
 * Artisan command to migrate from Silber's Bouncer package.
 *
 * This command migrates roles, abilities, and assignments from Bouncer
 * to Warden's authorization system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MigrateFromBouncerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warden:bouncer
                            {--log-channel=migration : The log channel to use for migration output}
                            {--guard= : The guard name to use for migrated roles and abilities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate roles and abilities from Bouncer to Warden';

    /**
     * Execute the console command.
     */
    public function handle(Repository $config): int
    {
        $this->info('Starting migration from Bouncer to Warden...');

        $guard = $config->get('auth.defaults.guard');

        if ($guard === null || !is_string($guard)) {
            $this->error('Unable to determine default authentication guard.');

            return self::FAILURE;
        }

        $provider = $config->get(sprintf('auth.guards.%s.provider', $guard));

        if ($provider === null || !is_string($provider)) {
            $this->error('Unable to determine authentication provider.');

            return self::FAILURE;
        }

        $userModel = $config->get(sprintf('auth.providers.%s.model', $provider));

        if (!is_string($userModel)) {
            $this->error('Unable to determine user model.');

            return self::FAILURE;
        }

        $logChannel = $this->option('log-channel');
        assert(is_string($logChannel));

        $guardName = $this->option('guard');

        $migrator = new BouncerMigrator(
            $userModel,
            $logChannel,
            is_string($guardName) ? $guardName : null,
        );
        $migrator->migrate();

        $this->info('Migration completed successfully!');

        return self::SUCCESS;
    }
}
