<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\StockItem;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\StockLedger;
use Illuminate\Validation\ValidationException;
use LogicException;

trait StockSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $employee;

    protected function setUpStockGym(): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->employee = User::factory()->create(['first_name' => 'Ella', 'last_name' => 'Santos']);
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gym);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function giveRole(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }

    protected function employeeWithRole(array $permissions): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Employee);
        $this->giveRole($user, $permissions, "Stock role {$user->id}");

        return $user;
    }

    protected function itemData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Boxing gloves', 'category' => 'protective_gear',
            'unit' => 'pair', 'location' => 'Equipment cage', 'min_level' => null,
        ], $overrides);
    }

    protected function openItem(array $overrides = [], int $quantity = 10): StockItem
    {
        return app(StockLedger::class)->open($this->gym, $this->itemData($overrides), $quantity, $this->owner);
    }

    protected function openItemFor(Gym $gym, User $actor, array $overrides = [], int $quantity = 10): StockItem
    {
        app(CurrentGym::class)->set($gym);

        return app(StockLedger::class)->open($gym, $this->itemData($overrides), $quantity, $actor);
    }

    protected function assertCountsMatch(StockItem $item): void
    {
        app(StockLedger::class)->assertQuantityMatchesMovements($item);
        $this->addToAssertionCount(1);
    }

    protected function assertValidationFails(string $key, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());

            return;
        }

        $this->fail("Expected a ValidationException on [{$key}].");
    }

    protected function assertLogicFails(callable $callback): void
    {
        try {
            $callback();
        } catch (LogicException $e) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a LogicException.');
    }
}
