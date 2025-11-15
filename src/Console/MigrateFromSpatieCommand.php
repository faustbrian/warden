<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Console;

use Cline\Warden\Migrators\SpatieMigrator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

use function assert;
use function is_string;
use function sprintf;

/**
 * Artisan command to migrate from Spatie's Laravel Permission package.
 *
 * This command migrates roles, permissions, and assignments from Spatie's
 * laravel-permission package to Warden's authorization system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MigrateFromSpatieCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warden:spatie
                            {--log-channel=migration : The log channel to use for migration output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate roles and permissions from Spatie Laravel Permission to Warden';

    /**
     * Execute the console command.
     */
    public function handle(Repository $config): int
    {
        $this->info('Starting migration from Spatie Laravel Permission to Warden...');

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

        $migrator = new SpatieMigrator($userModel, $logChannel);
        $migrator->migrate();

        $this->info('Migration completed successfully!');

        return self::SUCCESS;
    }
}
