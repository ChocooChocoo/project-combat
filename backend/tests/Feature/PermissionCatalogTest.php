<?php

namespace Tests\Feature;

use App\Enums\Module;
use App\Support\PermissionCatalog;
use InvalidArgumentException;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_catalog_covers_every_module(): void
    {
        $this->assertEqualsCanonicalizing(array_column(Module::cases(), 'value'), array_keys(config('permissions.catalog')));
    }

    public function test_catalog_has_51_unique_exact_keys(): void
    {
        $all = PermissionCatalog::all();
        $this->assertCount(51, $all);
        $this->assertSame($all, array_values(array_unique($all)));
        foreach ($all as $key) {
            $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+\.[a-z_]+$/', $key);
        }
    }

    public function test_legacy_levels_expand_only_known_standard_actions(): void
    {
        config(['permissions.catalog.training.sessions' => ['view', 'manage', 'export']]);
        $expanded = PermissionCatalog::expandLevels(['training' => 'manage', 'members' => null]);
        $this->assertContains('training.sessions.view', $expanded);
        $this->assertContains('training.sessions.manage', $expanded);
        $this->assertContains('training.check_ins.manage', $expanded);
        $this->assertNotContains('training.sessions.export', $expanded);
        $this->assertNotContains('training.bookings.approve', $expanded);
        $this->assertEmpty(array_filter($expanded, fn ($key) => str_starts_with($key, 'members.')));
    }

    public function test_unknown_modules_and_levels_expand_to_nothing(): void
    {
        $this->assertSame([], PermissionCatalog::expandLevels(['nope' => 'view', 'training' => 'boss', 'training.sessions' => 'manage']));
    }

    public function test_unknown_or_reordered_key_is_refused(): void
    {
        PermissionCatalog::assertKnown('finance.payments.view');
        $this->expectException(InvalidArgumentException::class);
        PermissionCatalog::assertKnown('payments.finance.view');
    }

    public function test_label_reads_as_plain_words(): void
    {
        $this->assertSame('Manage check ins', PermissionCatalog::label('check_ins', 'manage'));
    }
}
