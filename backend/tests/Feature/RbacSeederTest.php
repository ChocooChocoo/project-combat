<?php

namespace Tests\Feature;

use App\Models\GymRole;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_preserves_catalog_and_has_no_orphaned_permission_pivots(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $custom = Permission::create(['module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust',
            'permission_string' => 'inventory.stock.adjust', 'name' => 'Adjust stock']);
        Permission::where('permission_string', 'finance.payments.view')->update(['status' => 'inactive']);
        $this->seed();
        $count = GymRole::withoutGlobalScope('gym')->count();
        $this->seed();
        $this->assertSame($count, GymRole::withoutGlobalScope('gym')->count());
        $this->assertNotNull($custom->fresh());
        $this->assertSame('inactive', Permission::where('permission_string', 'finance.payments.view')->value('status'));
        $this->assertSame(0, DB::table('gym_role_permission')->leftJoin('gym_roles', 'gym_role_id', '=', 'gym_roles.id')->whereNull('gym_roles.id')->count());
    }
}
