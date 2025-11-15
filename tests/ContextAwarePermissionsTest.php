<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\Models\Team;
use Tests\Fixtures\Models\User;

describe('Context-Aware Permissions', function (): void {
    describe('Happy Paths', function (): void {
        test('grants context-aware ability scoped to specific team', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team = Team::query()->create(['name' => 'Test Team']);

            // Act
            $warden->allow($user)->within($team)->to('view-invoices');

            // Assert
            $abilities = $user->abilities()->get();
            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->name)->toEqual('view-invoices');
            expect($abilities->first()->pivot->context_id)->toEqual($team->getKey());
            expect($abilities->first()->pivot->context_type)->toEqual($team->getMorphClass());
        });

        test('grants context-aware role scoped to specific team', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team = Team::query()->create(['name' => 'Test Team']);

            // Act
            $warden->assign('admin')->within($team)->to($user);

            // Assert
            $roles = $user->roles()->get();
            expect($roles)->toHaveCount(1);
            expect($roles->first()->pivot->context_id)->toEqual($team->getKey());
            expect($roles->first()->pivot->context_type)->toEqual($team->getMorphClass());
        });

        test('forbids context-aware ability within specific team context', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team = Team::query()->create(['name' => 'Test Team']);

            // Act
            $warden->forbid($user)->within($team)->to('delete-invoices');

            // Assert
            $abilities = $user->abilities()->wherePivot('forbidden', true)->get();
            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->name)->toEqual('delete-invoices');
            expect($abilities->first()->pivot->context_id)->toEqual($team->getKey());
            expect($abilities->first()->pivot->context_type)->toEqual($team->getMorphClass());
        });

        test('maintains independence between global and context-aware permissions', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team1 = Team::query()->create(['name' => 'Team 1']);
            $team2 = Team::query()->create(['name' => 'Team 2']);

            // Act
            $warden->allow($user)->to('view-invoices');
            $warden->allow($user)->within($team1)->to('edit-invoices');
            $warden->allow($user)->within($team2)->to('delete-invoices');

            // Assert
            $abilities = $user->abilities()->get();
            expect($abilities)->toHaveCount(3);

            $globalAbility = $abilities->firstWhere('pivot.context_id', null);
            $team1Ability = $abilities->firstWhere('pivot.context_id', $team1->getKey());
            $team2Ability = $abilities->firstWhere('pivot.context_id', $team2->getKey());

            expect($globalAbility)->toBeInstanceOf(Model::class);
            expect($team1Ability)->toBeInstanceOf(Model::class);
            expect($team2Ability)->toBeInstanceOf(Model::class);

            expect($globalAbility->name)->toEqual('view-invoices');
            expect($team1Ability->name)->toEqual('edit-invoices');
            expect($team2Ability->name)->toEqual('delete-invoices');
        });

        test('grants context-aware ability with model class constraint', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team = Team::query()->create(['name' => 'Test Team']);

            // Act
            $warden->allow($user)->within($team)->to('edit', User::class);

            // Assert
            $abilities = $user->abilities()->get();
            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->pivot->context_id)->toEqual($team->getKey());
            expect($abilities->first()->pivot->context_type)->toEqual($team->getMorphClass());
            expect($abilities->first()->name)->toEqual('edit');
            expect($abilities->first()->subject_type)->toEqual(User::class);
            expect($abilities->first()->subject_id)->toBeNull();
        });

        test('grants context-aware ability with specific model instance constraint', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user = User::query()->create();
            $team = Team::query()->create(['name' => 'Test Team']);
            $invoice = User::query()->create(['name' => 'Invoice Model']);

            // Act
            $warden->allow($user)->within($team)->to('view', $invoice);

            // Assert
            $abilities = $user->abilities()->get();
            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->pivot->context_id)->toEqual($team->getKey());
            expect($abilities->first()->pivot->context_type)->toEqual($team->getMorphClass());
            expect($abilities->first()->subject_id)->toEqual($invoice->getKey());
        });
    });
});
