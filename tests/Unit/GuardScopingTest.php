<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Warden;
use Tests\Fixtures\Enums\GuardEnum;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::query()->create();
});

describe('Warden::guard() scoped instances', function (): void {
    test('creates guard-scoped instance', function (): void {
        $webWarden = Warden::guard('web');
        $apiWarden = Warden::guard('api');

        expect($webWarden->getGuardName())->toBe('web');
        expect($apiWarden->getGuardName())->toBe('api');
    });

    test('creates guard-scoped instance with BackedEnum', function (): void {
        $webWarden = Warden::guard(GuardEnum::Web);
        $apiWarden = Warden::guard(GuardEnum::Api);
        $rpcWarden = Warden::guard(GuardEnum::Rpc);

        expect($webWarden->getGuardName())->toBe('web');
        expect($apiWarden->getGuardName())->toBe('api');
        expect($rpcWarden->getGuardName())->toBe('rpc');
    });

    test('uses config default when guard not specified', function (): void {
        config(['warden.guard' => 'web']);

        $warden = app(Warden::class);

        expect($warden->getGuardName())->toBe('web');
    });
});

describe('Guard-scoped roles', function (): void {
    test('creates roles with guard_name', function (): void {
        Warden::guard('web')->allow('admin')->to('manage-posts');
        Warden::guard('api')->allow('admin')->to('read-data');

        $webAdmin = Models::role()->where('name', 'admin')->where('guard_name', 'web')->first();
        $apiAdmin = Models::role()->where('name', 'admin')->where('guard_name', 'api')->first();

        expect($webAdmin)->not->toBeNull();
        expect($apiAdmin)->not->toBeNull();
        expect($webAdmin->guard_name)->toBe('web');
        expect($apiAdmin->guard_name)->toBe('api');
    });

    test('assigns roles scoped to guard', function (): void {
        Warden::guard('web')->assign('editor')->to($this->user);
        Warden::guard('api')->assign('editor')->to($this->user);

        $webEditor = Models::role()->where('name', 'editor')->where('guard_name', 'web')->first();
        $apiEditor = Models::role()->where('name', 'editor')->where('guard_name', 'api')->first();

        expect($webEditor)->not->toBeNull();
        expect($apiEditor)->not->toBeNull();
        expect($this->user->roles()->count())->toBe(2);
    });

    test('roles with same name but different guards are separate', function (): void {
        Warden::guard('web')->assign('viewer')->to($this->user);

        $webViewer = Models::role()->where('name', 'viewer')->where('guard_name', 'web')->first();
        $apiViewer = Models::role()->where('name', 'viewer')->where('guard_name', 'api')->first();

        expect($webViewer)->not->toBeNull();
        expect($apiViewer)->toBeNull();
    });
});

describe('Guard-scoped abilities', function (): void {
    test('creates abilities with guard_name', function (): void {
        Warden::guard('web')->allow($this->user)->to('edit-posts');
        Warden::guard('api')->allow($this->user)->to('read-data');

        $webAbility = Models::ability()->where('name', 'edit-posts')->where('guard_name', 'web')->first();
        $apiAbility = Models::ability()->where('name', 'read-data')->where('guard_name', 'api')->first();

        expect($webAbility)->not->toBeNull();
        expect($apiAbility)->not->toBeNull();
        expect($webAbility->guard_name)->toBe('web');
        expect($apiAbility->guard_name)->toBe('api');
    });

    test('abilities with same name but different guards are separate', function (): void {
        Warden::guard('web')->allow($this->user)->to('delete-posts');
        Warden::guard('api')->allow($this->user)->to('delete-posts');

        $webAbility = Models::ability()->where('name', 'delete-posts')->where('guard_name', 'web')->first();
        $apiAbility = Models::ability()->where('name', 'delete-posts')->where('guard_name', 'api')->first();

        expect($webAbility)->not->toBeNull();
        expect($apiAbility)->not->toBeNull();
        expect($webAbility->id)->not->toBe($apiAbility->id);
    });
});

describe('Guard isolation', function (): void {
    test('web guard does not see api guard roles', function (): void {
        Warden::guard('api')->assign('api-admin')->to($this->user);

        $webRoles = Models::role()->forGuard('web')->get();
        $apiRoles = Models::role()->forGuard('api')->get();

        expect($webRoles)->toHaveCount(0);
        expect($apiRoles)->toHaveCount(1);
        expect($apiRoles->first()->name)->toBe('api-admin');
    });

    test('web guard does not see api guard abilities', function (): void {
        Warden::guard('api')->allow($this->user)->to('api-call');

        $webAbilities = Models::ability()->forGuard('web')->get();
        $apiAbilities = Models::ability()->forGuard('api')->get();

        expect($webAbilities)->toHaveCount(0);
        expect($apiAbilities)->toHaveCount(1);
        expect($apiAbilities->first()->name)->toBe('api-call');
    });

    test('rpc guard is isolated from web and api', function (): void {
        Warden::guard('web')->assign('web-admin')->to($this->user);
        Warden::guard('api')->assign('api-admin')->to($this->user);
        Warden::guard('rpc')->assign('rpc-admin')->to($this->user);

        expect(Models::role()->forGuard('web')->count())->toBe(1);
        expect(Models::role()->forGuard('api')->count())->toBe(1);
        expect(Models::role()->forGuard('rpc')->count())->toBe(1);
        expect(Models::role()->count())->toBe(3);
    });
});

describe('Forbidding with guards', function (): void {
    test('forbids abilities scoped to guard', function (): void {
        Warden::guard('web')->forbid($this->user)->to('delete-posts');
        Warden::guard('api')->allow($this->user)->to('delete-posts');

        $webAbility = Models::ability()->where('name', 'delete-posts')->where('guard_name', 'web')->first();
        $apiAbility = Models::ability()->where('name', 'delete-posts')->where('guard_name', 'api')->first();

        $webPermission = $this->user->abilities()->wherePivot('ability_id', $webAbility->id)->first();
        $apiPermission = $this->user->abilities()->wherePivot('ability_id', $apiAbility->id)->first();

        expect($webPermission->pivot->forbidden)->toBeTruthy();
        expect($apiPermission->pivot->forbidden)->toBeFalsy();
    });
});

describe('Default guard behavior', function (): void {
    test('uses web guard by default', function (): void {
        config(['warden.guard' => 'web']);

        $warden = app(Warden::class);
        $warden->assign('default-admin')->to($this->user);

        $role = Models::role()->where('name', 'default-admin')->first();

        expect($role->guard_name)->toBe('web');
    });

    test('respects config override', function (): void {
        config(['warden.guard' => 'api']);

        $warden = app(Warden::class);
        $warden->assign('config-admin')->to($this->user);

        $role = Models::role()->where('name', 'config-admin')->first();

        expect($role->guard_name)->toBe('api');
    });
});

describe('Role queries with guard filtering', function (): void {
    test('getKeysByName filters by guard', function (): void {
        $webRole = Models::role()->create(['name' => 'editor', 'guard_name' => 'web']);
        $apiRole = Models::role()->create(['name' => 'editor', 'guard_name' => 'api']);

        $roleModel = Models::role();
        $webKeys = $roleModel->getKeysByName(['editor'], 'web');
        $apiKeys = $roleModel->getKeysByName(['editor'], 'api');

        expect($webKeys)->toContain($webRole->id);
        expect($webKeys)->not->toContain($apiRole->id);
        expect($apiKeys)->toContain($apiRole->id);
        expect($apiKeys)->not->toContain($webRole->id);
    });

    test('getNamesByKey filters by guard', function (): void {
        $webRole = Models::role()->create(['name' => 'viewer', 'guard_name' => 'web']);
        $apiRole = Models::role()->create(['name' => 'viewer', 'guard_name' => 'api']);

        $roleModel = Models::role();
        $webNames = $roleModel->getNamesByKey([$webRole->id, $apiRole->id], 'web');
        $apiNames = $roleModel->getNamesByKey([$webRole->id, $apiRole->id], 'api');

        expect($webNames)->toContain('viewer');
        expect($webNames)->toHaveCount(1);
        expect($apiNames)->toContain('viewer');
        expect($apiNames)->toHaveCount(1);
    });
});
